<?php

namespace Drupal\simple_conreg;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to register members.
 */
class SimpleConregRegistrationForm extends FormBase {

  use AutowireTrait;

  /**
   * Constructs a new EmailExampleGetFormPage.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The currently logged in user.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Drupal renderer.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $emailValidator
   *   The email validator service.
   */
  final public function __construct(
    protected AccountProxyInterface $currentUser,
    protected MailManagerInterface $mail_manager,
    protected RendererInterface $renderer,
    protected  EmailValidatorInterface $emailValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_register';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $return = '') {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);
    $form_state->set('return', $return);

    // Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $memberPrices = [];

    // Fetch event name from Event table.
    if (count($event = SimpleConregEventStorage::load(['eid' => $eid])) < 3) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = [
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      ];
      return $form;
    }

    // Get config for event.
    $config = SimpleConregConfig::getConfig($eid);

    if ($event['is_open'] == 0) {
      // Event not configured. Display error.
      $message = $config->get('closed_message_text');
      $form['simple_conreg_event'] = [
        '#markup' => $message,
        '#prefix' => '<div class="closed-message">',
        '#suffix' => '</div>',
      ];
      return $form;
    }

    if (empty($config->get('payments.system'))) {
      // Event not configured. Display error.
      $form['simple_conreg_event'] = [
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      ];
      return $form;
    }

    $defaultType = $config->get('member_type_default');
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $memberClasses = SimpleConregOptions::memberClasses($eid, $config);
    $symbol = $config->get('payments.symbol');
    $countryOptions = SimpleConregOptions::memberCountries($eid, $config);
    $defaultCountry = $config->get('reference.default_country');
    // If geoPlugin enabled in configuration, lookup country.
    if ($config->get('reference.geoplugin')) {
      $userCountry = SimpleConregCountry::getUserCountry();
      if (!empty($userCountry)) {
        $defaultCountry = $userCountry;
      }
    }

    $lead_member = NULL;
    $first_user_email = NULL;
    $paidMembers = [];
    $unpaidMembers = [];
    // Check if user logged in and should be first member.
    if ($this->currentUser->isAuthenticated() && $return != 'fantable') {
      $email = $this->currentUser->getEmail();
      if (!empty($email)) {
        // User is logged in and has an email, check if existing member.
        $lead_member = Member::loadMemberByEmail($eid, $email);
        if (!$lead_member) {
          $first_user_email = $email;
        }
        $memberGroup = Member::loadMemberGroupByEmail($eid, $email);
        $mapFn = fn($member) => $member->badge_name;
        $paidMembers = array_map($mapFn, array_filter($memberGroup, fn($member) => $member->is_paid));
        $unpaidMembers = array_map($mapFn, array_filter($memberGroup, fn($member) => !$member->is_paid));
      }
    }
    $lead_mid = $lead_member?->mid;

    [$addOnOptions, $addOnPrices] = SimpleConregOptions::memberAddons($eid, $config);
    // Check if discounts enabled.
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $discountEnabled = $config->get('discount.enable');
    $discountFreeEvery = $config->get('discount.free_every');

    // Get thenumber of members on the form.
    $memberQty = $form_values['global']['member_quantity'] ?? 1;

    // Calculate price for all members.
    [$fullPrice,
      $discountPrice,
      $totalPrice,
      $totalPriceMinusFree,
      $memberPrices,
    ] = $this->getAllMemberPrices(
      $config,
      $form_values,
      $memberQty,
      $types->types,
      $addOnPrices,
      $symbol,
      $discountEnabled,
      $discountFreeEvery,
    );

    $form = [
      '#tree' => TRUE,
      '#cache' => [
        'tags' => ['event:' . $eid . ':registration'],
        'max-age' => Cache::PERMANENT,
      ],
      '#prefix' => '<div id="regform">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => [
          'simple_conreg/conreg_form',
          'simple_conreg/conreg_fieldoptions',
          'simple_conreg/conreg_disable_on_click',
        ],
        'drupalSettings' => [],
      ],
    ];

    if ($paidMembers && $return != 'fantable') {
      $url = Url::fromRoute('simple_conreg_portal', ['eid' => $eid]);
      $form['paid_members_registered'] = [
        '#prefix' => '<div class="registration-form-paid-members">',
        '#suffix' => '</div>',
        '#markup' => $this->t(
          '<strong>Please note:</strong> You already have registered the following members: %members. For more details, please go to the <a href="@member-portal-page">member portal</a>.',
          ['%members' => implode(', ', $paidMembers), '@member-portal-page' => $url->toString()]
        )
      ];
    }

    if ($unpaidMembers && $return != 'fantable') {
      $url = Url::fromRoute('simple_conreg_portal', ['eid' => $eid]);
      $form['unpaid_members_registered'] = [
        '#prefix' => '<div class="registration-form-unpaid-members">',
        '#suffix' => '</div>',
        '#markup' => $this->t(
          '<strong>Warning:</strong> You have started registration for the following: %unpaid_members. However, you have not completed payment. To complete, please go to the <a href="@member-portal-page">member portal</a>.',
          ['%unpaid_members' => implode(', ', $unpaidMembers), '@member-portal-page' => $url->toString()]
        )
      ];
    }

    $form['intro'] = [
      '#prefix' => '<div class="registration-form-intro">',
      '#suffix' => '</div>',
      '#markup' => $config->get('registration_intro'),
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How many members?'),
    ];

    $qtyVals = range(1, 6);
    $qtyOptions = array_combine($qtyVals, $qtyVals);
    $form['global']['member_quantity'] = [
      '#type' => 'select',
      '#title' => $this->t('Select number of members to register'),
      '#options' => $qtyOptions,
      '#default_value' => 1,
      '#attributes' => ['class' => ['edit-member-quantity']],
      '#ajax' => [
        'wrapper' => 'regform',
        'callback' => [$this, 'updateMemberQuantityCallback'],
        'event' => 'change',
      ],
    ];

    $form['members'] = [
      '#prefix' => '<div id="members">',
      '#suffix' => '</div>',
    ];

    $optionCallbacks = [];
    // Array to store type for each member.
    $curMemberTypes = [];
    $curMemberDays = [];
    $prevMemberDays = $form_state->get('member_days');
    // Array to store class for each member.
    $selectedClasses = [];
    // Get the previous classes to compare.
    $prevSelectedClasses = $form_state->get('member_classes');
    if (is_null($prevSelectedClasses)) {
      $prevSelectedClasses = [];
    }
    $selectedClassChanged = FALSE;

    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Get the member class for the current member type,
      // or if none defined, get the default member class.
      $memberType = $form_values['members']['member' . $cnt]['type'] ?? $defaultType;
      $curMemberTypes[$cnt] = $memberType;
      $curMemberClassRef = $types->types[$memberType]->memberClass ?? array_key_first($memberClasses->classes);
      $selectedClasses[$cnt] = $curMemberClassRef;
      $curMemberClass = $memberClasses->classes[$curMemberClassRef];

      if (!isset($prevSelectedClasses[$cnt])) {
        $prevSelectedClasses[$cnt] = '';
      }
      if (isset($selectedClasses) && isset($selectedClasses[$cnt]) && $selectedClasses[$cnt] != $prevSelectedClasses[$cnt]) {
        $selectedClassChanged = TRUE;
      }

      $form['members']['member' . $cnt] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Member @number', ['@number' => $cnt]),
      ];

      $firstname_max_length = $curMemberClass->max_length->first_name;
      $form['members']['member' . $cnt]['first_name'] = [
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->first_name,
        '#size' => 29,
        '#maxlength' => $firstname_max_length ?: 128,
        '#attributes' => [
          'id' => "edit-members-member$cnt-first-name",
          'class' => ['edit-members-first-name'],
        ],
        '#required' => ($curMemberClass->mandatory->first_name ? TRUE : FALSE),
      ];

      $lastname_max_length = $curMemberClass->max_length->last_name;
      $form['members']['member' . $cnt]['last_name'] = [
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->last_name,
        '#size' => 29,
        '#maxlength' => $lastname_max_length ?: 128,
        '#attributes' => [
          'id' => "edit-members-member$cnt-last-name",
          'class' => ['edit-members-last-name'],
        ],
        '#required' => ($curMemberClass->mandatory->last_name ? TRUE : FALSE),
      ];

      $form['members']['member' . $cnt]['name_description'] = [
        '#type' => 'markup',
        '#markup' => $curMemberClass->fields->name_description,
        '#prefix' => '<div class="description">',
        '#suffix' => '</div>',
      ];

      if ($first_user_email && $cnt == 1) {
        $form['members']['member' . $cnt]['email'] = [
          '#type' =>  'hidden',
          '#value' => $first_user_email,
        ];
        $form['members']['member' . $cnt]['email_label'] = [
          '#prefix' => '<div class="form-item__label">',
          '#suffix' => '</div>',
          '#markup' => $this->t('Email'),
        ];
        $form['members']['member' . $cnt]['email_display'] = [
          '#prefix' => '<div id="edit-members-memver$cnt-email">',
          '#suffix' => '</div>',
          '#markup' => $first_user_email,
        ];
      }
      else {
        $form['members']['member' . $cnt]['email'] = [
          '#type' => 'email',
          '#title' => $curMemberClass->fields->email,
        ];
        if (!$paidMembers && $cnt == 1) {
          $form['members']['member' . $cnt]['email']['#required'] = TRUE;
          $form['members']['member' . $cnt]['email']['#description'] = $this->t('Email address required for first member.');
        }
        else {
          $form['members']['member' . $cnt]['email']['#description'] = $this->t('If you do not provide an email, you will have to get convention updates from the first member.');
        }
      }

      // Member type: If not returning to fan table/reg desk, and not logged in
      // with existing membership, then first member must be an adult.
      $tags = ['event:' . $eid . ':types'];
      if ($config->get('payments.show_remaining') ?? FALSE) {
        $tags[] = 'event:' . $eid . ':remaining';
      }
      $form['members']['member' . $cnt]['type'] = [
        '#type' => 'select',
        '#title' => $curMemberClass->fields->membership_type,
        '#description' => $curMemberClass->fields->membership_type_description,
        '#options' => ((empty($return) && empty($lead_mid) && $cnt == 1) ? $types->firstOptions : $types->publicOptions),
        '#required' => TRUE,
        '#attributes' => ['class' => ['edit-member-type']],
        '#ajax' => [
          'wrapper' => 'regform',
          'callback' => [$this, 'updateMemberPriceCallback'],
          'event' => 'change',
        ],
        '#cache' => [
          'tags' => $tags,
          'max-age' => Cache::PERMANENT,
        ],
      ];
      if (!empty($defaultType)) {
        $form['members']['member' . $cnt]['type']['#default_value'] = $defaultType;
      }

      $form['members']['member' . $cnt]['dayOptions'] = [
        '#prefix' => '<div id="memberDayOptions' . $cnt . '">',
        '#suffix' => '</div>',
      ];
      // Get the current member type.
      // If none selected, take the first entry in the options array.
      if (!empty($form_values['members']['member' . $cnt]['type'])) {
        $currentType = $form_values['members']['member' . $cnt]['type'];
        // If current member type has day options, display.
        if (isset($types->types[$currentType]) && isset($types->types[$currentType]->dayOptions) && count($types->types[$currentType]->dayOptions)) {
          // Track that the member has days set.
          $curMemberDays[$cnt] = TRUE;
          // If day options available, we need to give them Ajax callbacks,
          // can't do partial form update, so treat like member class change.
          $selectedClassChanged = TRUE;
          // Checkboxes for days.
          $form['members']['member' . $cnt]['dayOptions']['days'] = [
            '#type' => 'checkboxes',
            '#title' => $curMemberClass->fields->membership_days,
            '#description' => $curMemberClass->fields->membership_days_description,
            '#options' => $types->types[$currentType]->dayOptions,
            '#attributes' => [
              'class' => ['edit-members-days'],
            ],
            '#ajax' => [
              'wrapper' => 'regform',
              'callback' => [$this, 'updateMemberPriceCallback'],
              'event' => 'change',
            ],
          ];
        }
        else {
          // Current member doesn't have days set.
          $curMemberDays[$cnt] = FALSE;
          // If current member type has no days, but previous did, refresh form.
          if ($prevMemberDays[$cnt] ?? FALSE) {
            $selectedClassChanged = TRUE;
          }
        }
      }

      // Get member add-on details.
      $addon = $form_values['members']['member' . $cnt]['add_on'] ?? [];
      $form['members']['member' . $cnt]['add_on'] = SimpleConregAddons::getAddon(
        $config,
        $addon,
        $addOnOptions,
        $cnt,
        [$this, 'updateMemberPriceCallback'],
        $form_state
      );

      $form['members']['member' . $cnt]['price_minus_free_amt'] = [
        '#type' => 'hidden',
        '#value' => $memberPrices[$cnt]->priceMinusFree,
        '#attributes' => [
          'id' => "edit-member$cnt-price-minus-free-amt",
        ],
      ];

      // Display price for selected member type and add-ons.
      $form['members']['member' . $cnt]['price'] = [
        '#prefix' => '<div id="memberPrice' . $cnt . '">',
        '#suffix' => '</div>',
        '#markup' => $memberPrices[$cnt]->priceMessage,
      ];

      // Add badge name max to Drupal Settings for JavaScript to use.
      $badgename_max_length = $curMemberClass->max_length->badge_name;
      if (empty($badgename_max_length)) {
        $badgename_max_length = 128;
      }
      $form['#attached']['drupalSettings']['simple_conreg'] = ['badge_name_max' => $badgename_max_length];

      $firstName = $form_values['members']['member' . $cnt]['first_name'] ?? '';
      $lastName = $form_values['members']['member' . $cnt]['last_name'] ?? '';
      $form['members']['member' . $cnt]['badge_name_option'] = [
        '#type' => 'radios',
        '#title' => $curMemberClass->fields->badge_name_option,
        '#description' => $curMemberClass->fields->badge_name_description,
        '#options' => SimpleConregOptions::badgeNameOptionsForName($eid, $firstName, $lastName, $badgename_max_length, $config),
        '#default_value' => $config->get('badge_name_default'),
        '#required' => TRUE,
        '#attributes' => [
          'class' => [
            'edit-members-badge-name-option',
            "edit-members-member$cnt-badge-name-option",
          ],
        ],
      ];

      $form['members']['member' . $cnt]['badge_name'] = [
        '#prefix' => '<div id="memberBadgeName' . $cnt . '" class="edit-members-badge-name-container">',
        '#suffix' => '</div>',
      ];

      // Check if "other" selected for badge name option.
      $form['members']['member' . $cnt]['badge_name']['other'] = [
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->badge_name,
        '#maxlength' => $badgename_max_length,
        '#attributes' => [
          'id' => "edit-members-member$cnt-badge-name",
          'class' => ['edit-members-badge-name-other'],
        ],
      ];

      if (!empty($curMemberClass->fields->display)) {
        $form['members']['member' . $cnt]['display'] = [
          '#type' => 'select',
          '#title' => $curMemberClass->fields->display,
          '#description' => $curMemberClass->fields->display_description,
          '#options' => SimpleConregOptions::display(),
          '#default_value' => $config->get('display_options.default'),
          '#required' => TRUE,
        ];
      }
      else {
        $form['members']['member' . $cnt]['display'] = [
          '#prefix' => '<div id="memberDisplayMessage' . $cnt . '">',
          '#suffix' => '</div>',
          '#markup' => $curMemberClass->fields->display_description,
        ];
      }

      if (!empty($curMemberClass->fields->communication_method)) {
        $form['members']['member' . $cnt]['communication_method'] = [
          '#type' => 'select',
          '#title' => $curMemberClass->fields->communication_method,
          '#description' => $curMemberClass->fields->communication_method_description,
          '#options' => SimpleConregOptions::communicationMethod($eid, $config, TRUE),
          '#default_value' => $config->get('communications_method.default'),
          '#required' => TRUE,
        ];
      }

      if ($cnt > 1 && !empty($curMemberClass->fields->same_address)) {
        $form['members']['member' . $cnt]['same_address'] = [
          '#type' => 'checkbox',
          '#title' => $curMemberClass->fields->same_address,
          '#ajax' => [
            'callback' => [$this, 'updateMemberAddressCallback'],
            'event' => 'change',
          ],
        ];
      }

      $form['members']['member' . $cnt]['address'] = [
        '#prefix' => '<div id="memberAddress' . $cnt . '">',
        '#suffix' => '</div>',
      ];

      if (empty($form_values['members']['member' . $cnt]['same_address'])) {
        $same = FALSE;
      }
      else {
        $same = $form_values['members']['member' . $cnt]['same_address'];
      }

      // Always show address for member 1, and for other members
      // if "same" box isn't checked.
      if ($cnt == 1 || !$same) {
        if (!empty($curMemberClass->fields->street)) {
          $form['members']['member' . $cnt]['address']['street'] = [
            '#type' => 'textfield',
            '#title' => $curMemberClass->fields->street,
            '#required' => ($curMemberClass->mandatory->street ? TRUE : FALSE),
          ];
        }

        if (!empty($curMemberClass->fields->street2)) {
          $form['members']['member' . $cnt]['address']['street2'] = [
            '#type' => 'textfield',
            '#title' => $curMemberClass->fields->street2,
            '#required' => ($curMemberClass->mandatory->street2 ? TRUE : FALSE),
          ];
        }

        if (!empty($curMemberClass->fields->city)) {
          $form['members']['member' . $cnt]['address']['city'] = [
            '#type' => 'textfield',
            '#title' => $curMemberClass->fields->city,
            '#required' => ($curMemberClass->mandatory->city ? TRUE : FALSE),
          ];
        }

        if (!empty($curMemberClass->fields->county)) {
          $form['members']['member' . $cnt]['address']['county'] = [
            '#type' => 'textfield',
            '#title' => $curMemberClass->fields->county,
            '#required' => ($curMemberClass->mandatory->county ? TRUE : FALSE),
          ];
        }

        if (!empty($curMemberClass->fields->postcode)) {
          $form['members']['member' . $cnt]['address']['postcode'] = [
            '#type' => 'textfield',
            '#title' => $curMemberClass->fields->postcode,
            '#required' => ($curMemberClass->mandatory->postcode ? TRUE : FALSE),
          ];
        }

        if (!empty($curMemberClass->fields->country)) {
          $form['members']['member' . $cnt]['address']['country'] = [
            '#type' => 'select',
            '#title' => $curMemberClass->fields->country,
            '#options' => $countryOptions,
            '#description' => $curMemberClass->fields->country_description,
            '#required' => ($curMemberClass->mandatory->country ? TRUE : FALSE),
          ];
          if (!empty($defaultCountry)) {
            $form['members']['member' . $cnt]['address']['country']['#default_value'] = $defaultCountry;
          }
        }
      }

      if (!empty($curMemberClass->fields->phone)) {
        $form['members']['member' . $cnt]['phone'] = [
          '#type' => 'tel',
          '#title' => $curMemberClass->fields->phone,
        ];
      }

      if (!empty($curMemberClass->fields->birth_date)) {
        $form['members']['member' . $cnt]['birth_date'] = [
          '#type' => 'date',
          '#title' => $curMemberClass->fields->birth_date,
          '#required' => ($curMemberClass->mandatory->birth_date ? TRUE : FALSE),
        ];
      }

      if (!empty($curMemberClass->fields->age)) {
        $ageOptions = [];
        $min = $curMemberClass->fields->age_min;
        $max = $curMemberClass->fields->age_max;
        for ($age = $min; $age <= $max; $age++) {
          $ageOptions[$age] = $age;
        }
        $form['members']['member' . $cnt]['age'] = [
          '#type' => 'select',
          '#title' => $curMemberClass->fields->age,
          '#options' => $ageOptions,
          '#required' => ($curMemberClass->mandatory->age ? TRUE : FALSE),
        ];
      }

      if (!empty($curMemberClass->extras->flag1)) {
        $form['members']['member' . $cnt]['extra_flag1'] = [
          '#type' => 'checkbox',
          '#title' => $curMemberClass->extras->flag1,
        ];
      }

      if (!empty($curMemberClass->extras->flag2)) {
        $form['members']['member' . $cnt]['extra_flag2'] = [
          '#type' => 'checkbox',
          '#title' => $curMemberClass->extras->flag2,
        ];
      }

      // Get field options from form state. If not set, get from config.
      $fieldOptions = $form_state->get('fieldOptions');
      if (is_null($fieldOptions)) {
        $fieldOptions = FieldOptions::getFieldOptions($eid);
      }
      // Add the field options to the form.
      $fieldOptions->addOptionFields($curMemberClassRef, $form['members']['member' . $cnt], NULL, FALSE, FALSE);
    }

    $form['global_options'] = [
      '#prefix' => '<div id="global-options">',
      '#suffix' => '</div>',
    ];

    // Add the field options to the form.
    $fieldOptions->addOptionFields(array_key_first($memberClasses->classes), $form['global_options'], NULL, TRUE, FALSE);

    $form['payment'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Total price'),
    ];

    // Get global add-on details.
    $form['payment']['global_add_on'] = SimpleConregAddons::getAddon(
      $config,
      $form_values['payment']['global_add_on'] ?? [],
      $addOnOptions,
      0,
      [$this, 'updateMemberPriceCallback'],
      $form_state
    );

    $form['payment']['price'] = [
      '#prefix' => '<div id="Pricing">',
      '#suffix' => '</div>',
    ];

    $form['payment']['price']['total_minus_free_amt'] = [
      '#type' => 'hidden',
      '#value' => $totalPriceMinusFree,
      '#attributes' => [
        'id' => "edit-total-minus-free-amt",
      ],
    ];

    if ($discountPrice > 0) {
      $form['payment']['price']['full_price'] = [
        '#prefix' => '<div id="fullPrice">',
        '#suffix' => '</div>',
        '#markup' => $this->t(
          'Amount before discount: @symbol@full',
          ['@symbol' => $symbol, '@full' => $fullPrice]
        ),
      ];
      $form['payment']['price']['discount_price'] = [
        '#prefix' => '<div id="discountPrice">',
        '#suffix' => '</div>',
        '#markup' => $this->t(
          'Total discount: @symbol@discount',
          ['@symbol' => $symbol, '@discount' => $discountPrice]
        ),
      ];
    }
    $form['payment']['price']['total_price'] = [
      '#prefix' => '<div id="total-price">',
      '#suffix' => '</div>',
      '#markup' => $this->t(
        'Total amount to pay: @symbol<span id="total-value">@total</span>',
        ['@symbol' => $symbol, '@total' => number_format($totalPrice, 2)]
      ),
    ];

    $form['#attached']['drupalSettings']['submit'] = [
      'payment' => $config->get('submit.payment'),
      'free' => $config->get('submit.free'),
    ];
    if (empty($totalPrice)) {
      $submitLabel = $config->get('submit.free');
    }
    else {
      $submitLabel = $config->get('submit.payment');
    }

    $form['payment']['submit'] = [
      '#type' => 'submit',
      '#value' => $submitLabel,
      '#attributes' => [
        'id' => 'edit-payment-submit',
        'class' => ['disable-on-click'],
      ],
      '#prefix' => '<div id="Submit">',
      '#suffix' => '</div>',
    ];

    $form_state->set('member_types', $curMemberTypes);
    $form_state->set('member_days', $curMemberDays);
    $form_state->set('member_classes', $selectedClasses);
    $form_state->set('selected_class_changed', $selectedClassChanged);
    $form_state->set('option_callbacks', $optionCallbacks);
    $form_state->set('total_price', $totalPrice);
    return $form;
  }

  /**
   * Callback function for "number of members" drop down.
   *
   * @param array $form
   *   The form to update.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  public function updateMemberQuantityCallback(array $form, FormStateInterface $form_state): array {
    // Form rebuilt with required number of members before callback.
    return $form;
  }

  /**
   * Callback for "member type" and "add-on" drop-downs. Replace price fields.
   *
   * @param array $form
   *   The form definition.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array|Drupal\Core\Ajax\AjaxResponse
   *   Updated form array or Ajax partial form replacements.
   */
  public function updateMemberPriceCallback(array $form, FormStateInterface $form_state): array|AjaxResponse {
    // Check if member class has changed, which will require a form refresh.
    $selectedClassChanged = $form_state->get('selected_class_changed');
    if ($selectedClassChanged) {
      // Get the triggering element.
      $trigger = $form_state->getTriggeringElement()['#name'];
      if (preg_match("/^members\[member(\d+)\]\[(\w+)\]/", $trigger, $matches)) {
        // If member type changed, return the whole form.
        if ($matches[2] == 'type') {
          return $form;
        }
      }
    }
    // Member class has not changed, so we only need to update the prices.
    $ajax_response = new AjaxResponse();
    // Calculate price for each member.
    $memberQty = $form_state->getValue(['global', 'member_quantity']);
    $addons = $form_state->get('addons') ?? [];

    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      foreach ($addons as $addOnId) {
        if (!empty($form['members']['member' . $cnt]['add_on'][$addOnId]['extra'])) {
          $id = '#member_addon_' . $addOnId . '_info_' . $cnt;
          $ajax_response->addCommand(new HtmlCommand($id, $this->renderer->render($form['members']['member' . $cnt]['add_on'][$addOnId]['extra']['info'])));
        }
      }
      $ajax_response->addCommand(new HtmlCommand('#memberPrice' . $cnt, $form['members']['member' . $cnt]['price']['#markup']));
    }
    // If global addon, return update that.
    foreach ($addons as $addOnId) {
      if (!empty($form['payment']['global_add_on'][$addOnId]['extra'])) {
        $id = '#global_addon_' . $addOnId . '_info';
        $ajax_response->addCommand(new HtmlCommand($id, $this->renderer->render($form['payment']['global_add_on'][$addOnId]['extra']['info'])));
      }
    }
    $ajax_response->addCommand(new HtmlCommand('#Pricing', $form['payment']['price']));
    $ajax_response->addCommand(new HtmlCommand('#Submit', $form['payment']['submit']));

    return $ajax_response;
  }

  /**
   * Callback for "badge name" radios. Show badge name field if required.
   *
   * @param array $form
   *   Form structure.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Ajax form replacement commands.
   */
  public function updateMemberBadgeNameCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    $ajax_response = new AjaxResponse();
    // Calculate price for each member.
    $memberQty = $form_state->getValue(['global', 'member_quantity']);
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      if (isset($form['members']['member' . $cnt]['badge_name'])) {
        $ajax_response->addCommand(new HtmlCommand('#memberBadgeName' . $cnt, $this->renderer->render($form['members']['member' . $cnt]['badge_name'])));
      }
      else {
        $ajax_response->addCommand(new HtmlCommand('#memberBadgeName' . $cnt, ""));
      }
    }

    return $ajax_response;
  }

  /**
   * Callback for "same as first member" checkbox. Replace address block.
   *
   * @param array $form
   *   The form definition.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of form.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   The Ajax commands to update the form.
   */
  public function updateMemberAddressCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    $ajax_response = new AjaxResponse();
    // Don't show address fields if "same as member 1 box" ticked.
    $memberQty = $form_state->getValue(['global', 'member_quantity']);
    // Only need to reshow from member 2 up.
    for ($cnt = 2; $cnt <= $memberQty; $cnt++) {
      $ajax_response->addCommand(new HtmlCommand('#memberAddress' . $cnt, $this->renderer->render($form['members']['member' . $cnt]['address'])));
    }

    return $ajax_response;
  }

  /**
   * Callback for option fields - add/remove detail field.
   *
   * @param array $form
   *   The form array.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Form state values.
   *
   * @return array
   *   Part of the form to replace.
   */
  public function updateMemberOptionFields(array $form, FormStateInterface $form_state): array {
    // Get the triggering element.
    $trigger = $form_state->getTriggeringElement()['#name'];
    // Get array of items to return, keyed by trigering element.
    $optionCallbacks = $form_state->get('option_callbacks');
    $callback = $optionCallbacks[$trigger];
    // Build the index of the element to return.
    switch ($callback[0]) {
      case 'group':
        return $form['members']['member' . $callback[1]][$callback[2]];

      case 'detail':
        return $form['members']['member' . $callback[1]][$callback[2]]['options']['container_' . $callback[3]];
    }
    return [];
  }

  /**
   * Function to validate fields as you type.
   *
   * @param array $form
   *   Form to check mandatory fields on.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Response containing modifications to form.
   */
  public function updateMandatoryValidationCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    $ajax_response = new AjaxResponse();
    // Check if all mandatory fields have values.
    $ajax_response->addCommand(new HtmlCommand('#mandatory', $form['payment']['mandatory']['#markup']));
    return $ajax_response;
  }

  /**
   * Recursively check that all mandatory fields have been populated.
   *
   * @param array $elements
   *   Fields to check.
   * @param array $values
   *   Populated values.
   *
   * @return bool
   *   True if mandatory fields populated.
   */
  public function checkMandatoryPopulated(array $elements, array $values): bool {
    foreach ($elements as $key => $val) {
      // Check each element of form array.
      if (is_array($val)) {
        // Only interested if element is a child array.
        if (substr($key, 0, 1) != '#') {
          // Only interested if string is not an attribute.
          if (array_key_exists('#required', $val) && $val['#required'] == TRUE) {
            // Entry is a required element. Check if key exists in form values.
            if (array_key_exists($key, $values)) {
              if (empty($values[$key])) {
                // Key present, but contains no value.
                return FALSE;
              }
            }
            else {
              // Key for required field not present.
              return FALSE;
            }
          }
          // Checked element, now check for any child elements.
          if (array_key_exists($key, $values)) {
            // Call recursively with child values.
            if (!$this->checkMandatoryPopulated($val, $values[$key])) {
              return FALSE;
            }
          }
          else {
            // Call recursively with empty array.
            if (!$this->checkMandatoryPopulated($val, [])) {
              return FALSE;
            }
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $eid = $form_state->get('eid');
    $form_values = $form_state->getValues();
    $memberQty = $form_values['global']['member_quantity'];
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Check that either first name or last name has been entered.
      if ((empty($form_values['members']['member' . $cnt]['first_name']) ||
        empty(trim($form_values['members']['member' . $cnt]['first_name']))) &&
        (empty($form_values['members']['member' . $cnt]['last_name']) ||
        empty(trim($form_values['members']['member' . $cnt]['last_name'])))
      ) {
        $form_state->setErrorByName('members][member' . $cnt . '][first_name', $this->t('You must enter either first name or last name.'));
        $form_state->setErrorByName('members][member' . $cnt . '][last_name');
      }
      // If first name selected for badge, first name must be entered.
      if (
        !isset($form_values['members']['member' . $cnt]['badge_name_option']) ||
        $form_values['members']['member' . $cnt]['badge_name_option'] == 'F' &&
        (empty($form_values['members']['member' . $cnt]['first_name']) ||
        empty(trim($form_values['members']['member' . $cnt]['first_name'])))
      ) {
        $form_state->setErrorByName('members][member' . $cnt . '][first_name', $this->t('You cannot choose first name for badge unless you enter a first name.'));
      }
      // If the "other" option has been chosen, a badge name must be entered.
      if (
        !isset($form_values['members']['member' . $cnt]['badge_name_option']) ||
        $form_values['members']['member' . $cnt]['badge_name_option'] == 'O' &&
        (empty($form_values['members']['member' . $cnt]['badge_name']['other']) ||
        empty(trim($form_values['members']['member' . $cnt]['badge_name']['other'])))
      ) {
        $form_state->setErrorByName('members][member' . $cnt . '][badge_name][other', $this->t('Please enter your badge name.'));
      }
      // Validate that email address is valid and unique for member.
      $email = $form_values['members']['member' . $cnt]['email'];
      if ($email) {
        if ($email && !$this->emailValidator->isValid($email)) {
          $form_state->setErrorByName('members][member' . $cnt . '][email', $this->t('Please enter a valid email address'));
        }
        // Check email isn't used for another member on same form.
        for ($emailCnt = 1; $emailCnt < $memberQty; $emailCnt++) {
          if ($cnt != $emailCnt && $email == $form_values['members']['member' . $emailCnt]['email']) {
            $form_state->setErrorByName('members][member' . $cnt . '][email', $this->t('Member %first has the same email address as member %second.', ['%first' => $cnt, '%second' => $emailCnt]));
          }
        }
        // Check a user hasn't already been registered with the same email.
        $member = Member::loadMemberByEmail($eid, $email);
        if ($member) {
          $form_state->setErrorByName('members][member' . $cnt . '][email', $this->t('A member has previously been registered with this address.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $eid = $form_state->get('eid');
    $event = SimpleConregEventStorage::load(['eid' => $eid]);
    $return = $form_state->get('return');
    $memberClasses = SimpleConregOptions::memberClasses($eid, $config);

    $form_values = $form_state->getValues();

    $config = SimpleConregConfig::getConfig($eid);
    $symbol = $config->get('payments.symbol');
    $discountEnabled = $config->get('discount.enable');
    $discountFreeEvery = $config->get('discount.free_every');
    $types = SimpleConregOptions::memberTypes($eid, $config);
    [, $addOnPrices] = SimpleConregOptions::memberAddons($eid, $config);

    // Find out number of members.
    $memberQty = $form_values['global']['member_quantity'];

    // Can't rely on price sent back from form, so recalculate.
    [,, $totalPrice,, $memberPrices] = $this->getAllMemberPrices(
      $config,
      $form_values,
      $memberQty,
      $types->types,
      $addOnPrices,
      $symbol,
      $discountEnabled,
      $discountFreeEvery);

    $lead_mid = NULL;
    if (empty($return)) {
      // Check if user logged in and should be first member.
      $email = $this->currentUser->getEmail();
      if (!empty($email)) {
        $lead_member = Member::loadMemberByEmail($eid, $email);
        $lead_mid = $lead_member?->mid;
      }
    }
    $memberIDs = [];

    // Set up parameters for confirmation email.
    $confirm_params = [];
    $confirm_params["eid"] = $eid;
    $confirm_params["quantity"] = $memberQty;
    $confirm_params["email"] = $form_values['members']['member1']['email'];
    $confirm_params["first"] = $form_values['members']['member1']['first_name'];
    $confirm_params["last"] = $form_values['members']['member1']['last_name'];
    $confirm_params["members"] = [];
    $confirm_params['from'] = $config->get('confirmation.from_name') . ' <' . $config->get('confirmation.from_email') . '>';

    $payment = new SimpleConregPayment();

    // Get field options from form state. If not set, get from config.
    $fieldOptions = $form_state->get('fieldOptions');
    if (is_null($fieldOptions)) {
      $fieldOptions = FieldOptions::getFieldOptions($eid);
    }

    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Look up the member type, and get the default badge type.
      $member_type = $form_values['members']['member' . $cnt]['type'];
      if (isset($types->types[$member_type]->badgeType)) {
        $badge_type = $types->types[$member_type]->badgeType;
      }
      else {
        // This shouldn't happen, but if no default badge type found,
        // hard code to A.
        $badge_type = 'A';
      }

      $memberClass = $types->types[$member_type]->memberClass ?? array_key_first($memberClasses->classes);
      $optionVals = [];
      // Process option fields to remove any modifications from form values.
      $fieldOptions->procesOptionFields($memberClass, $form_values['members']['member' . $cnt], 0, $optionVals);

      // Also process global options for each member.
      if (array_key_exists('global_options', $form_values)) {
        $fieldOptions->procesOptionFields($memberClass, $form_values['global_options'], 0, $optionVals);
      }

      $badgename_max_length = $memberClasses->classes[$memberClass]->max_length->badge_name;
      if (empty($badgename_max_length)) {
        $badgename_max_length = 128;
      }
      // Check whether to use name or "other" badge name...
      switch ($form_values['members']['member' . $cnt]['badge_name_option']) {
        case 'F':
          $badge_name = substr(trim($form_values['members']['member' . $cnt]['first_name']), 0, $badgename_max_length);
          break;

        case 'N':
          $badge_name = substr(trim($form_values['members']['member' . $cnt]['first_name']) . ' ' . trim($form_values['members']['member' . $cnt]['last_name']), 0, $badgename_max_length);
          break;

        case 'L':
          $badge_name = substr(trim($form_values['members']['member' . $cnt]['last_name']) . ', ' . trim($form_values['members']['member' . $cnt]['first_name']), 0, $badgename_max_length);
          break;

        case 'O':
          $badge_name = substr(trim($form_values['members']['member' . $cnt]['badge_name']['other']), 0, $badgename_max_length);
          break;
      }

      // If "same" checkbox ticked for member, use member 1 for address fields.
      if ($cnt == 1 || $form_values['members']['member' . $cnt]['same_address']) {
        $addressMember = 1;
      }
      else {
        $addressMember = $cnt;
      }
      // Assign random key for payment URL.
      $rand_key = mt_rand();
      // If no date, use NULL.
      if (isset($form_values['members']['member' . $cnt]['birth_date']) && preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $form_values['members']['member' . $cnt]['birth_date'])) {
        $birth_date = $form_values['members']['member' . $cnt]['birth_date'];
      }
      else {
        $birth_date = NULL;
      }

      // Save the submitted entry.
      $entry = [
        'eid' => $eid,
        'random_key' => $rand_key,
        'member_type' => $memberPrices[$cnt]->memberType,
        'days' => $memberPrices[$cnt]->days,
        'first_name' => $form_values['members']['member' . $cnt]['first_name'],
        'last_name' => $form_values['members']['member' . $cnt]['last_name'],
        'badge_name' => $badge_name,
        'badge_type' => $badge_type,
        'display' => $form_values['members']['member' . $cnt]['display'] ?? 'N',
        'communication_method' => $form_values['members']['member' . $cnt]['communication_method'] ?? '',
        'email' => $form_values['members']['member' . $cnt]['email'],
        'street' => $form_values['members']['member' . $addressMember]['address']['street'] ?? '',
        'street2' => $form_values['members']['member' . $addressMember]['address']['street2'] ?? '',
        'city' => $form_values['members']['member' . $addressMember]['address']['city'] ?? '',
        'county' => $form_values['members']['member' . $addressMember]['address']['county'] ?? '',
        'postcode' => $form_values['members']['member' . $addressMember]['address']['postcode'] ?? '',
        'country' => $form_values['members']['member' . $addressMember]['address']['country'] ?? '',
        'phone' => $form_values['members']['member' . $cnt]['phone'] ?? '',
        'birth_date' => $birth_date,
        'age' => $form_values['members']['member' . $cnt]['age'] ?? 0,
        'extra_flag1' => $form_values['members']['member' . $cnt]['extra_flag1'] ?? 0,
        'extra_flag2' => $form_values['members']['member' . $cnt]['extra_flag2'] ?? 0,
        'member_price' => $memberPrices[$cnt]->basePrice,
        'member_total' => $memberPrices[$cnt]->price,
        'add_on_price' => $memberPrices[$cnt]->addOnPrice,
        'payment_amount' => $totalPrice,
        'join_date' => time(),
        'update_date' => time(),
      ];
      if (!empty($lead_mid)) {
        $entry['lead_mid'] = $lead_mid;
      }
      // Add member details to parameters for email.
      $confirm_params["members"][$cnt] = $entry;

      // Create and save member.
      $member = Member::newMember($entry);
      $member->setOptions($optionVals);
      $result = $member->saveMember();

      if ($result) {
        // Store the member ID for use when saving add-ons.
        $memberIDs[$cnt] = $member->mid;
        // Add a payment line for the member.
        $payment->add(new SimpleConregPaymentLine(
          $result,
          'member',
          $this->t("Member registration to @event_name for @first_name @last_name",
            [
              '@event_name' => $event['event_name'],
              '@first_name' => $entry['first_name'],
              '@last_name' => $entry['last_name'],
            ]),
            $memberPrices[$cnt]->basePriceMinusFree,
          ));
        // Add confirmation.
        $this->messenger()->addMessage($this->t(
          'Thank you for registering @first_name @last_name.',
          [
            '@first_name' => $entry['first_name'],
            '@last_name' => $entry['last_name'],
          ]
        ));
      }
      if (empty($lead_mid)) {
        // For first member, make lead member.
        $lead_mid = $member->mid;
      }

      // Check Simplenews module loaded.
      if (\Drupal::moduleHandler()->moduleExists('simplenews')) {
        // Get Drupal SimpleNews subscription manager.
        /** @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface */
        $subscription_manager = \Drupal::service('simplenews.subscription_manager');
        // Simplenews is active, so check mailing lists to subscribed to.
        $simplenews_options = $config->get('simplenews.options');
        if (isset($simplenews_options)) {
          foreach ($simplenews_options as $newsletter_id => $options) {
            if ($options['active']) {
              // Get communications methods selected for newsletter.
              $communications_methods = $simplenews_options[$newsletter_id]['communications_methods'];
              // Check if member matches newsletter criteria.
              if (
                isset($entry['email']) &&
                $entry['email'] != '' &&
                isset($entry['communication_method']) &&
                isset($communications_methods[$entry['communication_method']]) &&
                $communications_methods[$entry['communication_method']]
              ) {
                // Subscribe member if criteria met.
                $subscription_manager->subscribe($entry['email'], $newsletter_id);
              }
            }
          }
        }
      }
    }

    // All members saved. Now save any add-ons.
    SimpleConregAddons::saveAddons($config, $form_values, $memberIDs, $payment);

    switch ($return) {
      case 'fantable':
        // If member add initiated from fan table screen, return there.
        $form_state->setRedirect(
          'simple_conreg_admin_fantable',
          ['eid' => $eid, 'lead_mid' => $lead_mid]);
        break;

      case 'portal':
        // If member add initiated from member portal screen, return there.
        $form_state->setRedirect('simple_conreg_portal', ['eid' => $eid]);
        break;

      default:
        // Save the payment (only if direct registration).
        $payid = $payment->save();
        // Redirect to payment form.
        $form_state->setRedirect(
          'simple_conreg_checkout',
          ['payid' => $payid, 'key' => $payment->randomKey]
        );
    }
  }

  /**
   * Callback for sorting member prices.
   */
  public function memberPriceCompare($a, $b) {
    if ($a->basePrice == $b->basePrice) {
      if ($a->memberNo == $b->memberNo) {
        // Should never actually happen.
        return 0;
      }
      return ($a->memberNo < $b->memberNo ? -1 : 1);
    }
    return ($a->basePrice > $b->basePrice ? -1 : 1);
  }

  /**
   * Method to calculate price of all members, and subtract any discounts.
   */
  public function getAllMemberPrices(
    ImmutableConfig $config,
    $form_values,
    $memberQty,
    $types,
    $addOnPrices,
    $symbol,
    $discountEnabled,
    $discountFreeEvery
  ) {
    $fullPrice = 0;
    $fullMinusFree = 0;
    $discountPrice = 0;
    $totalPrice = 0;
    $totalPriceMinusFree = 0;
    $memberPrices = [];
    $prices = [];
    $defaultType = $config->get('member_type_default');

    // First check for add-ons.
    [,
      $globalTotal,
      $globalMinusFree,
      $addOnMembers,
      $addOnMembersMinusFree,
    ] = SimpleConregAddons::getAllAddonPrices($config, $form_values);
    $fullPrice = $globalTotal;
    $fullMinusFree = $globalMinusFree;

    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Check member price.
      $memberPrices[$cnt] = $this->getMemberPrice($form_values, $cnt, $types, $addOnMembers[$cnt], $addOnMembersMinusFree[$cnt], $symbol, $defaultType);
      if ($memberPrices[$cnt]->basePrice > 0) {
        $prices[] = (object) [
          'memberNo' => $memberPrices[$cnt]->memberNo + ($addOnMembers[$cnt] ?? 0),
          'basePrice' => $memberPrices[$cnt]->basePrice,
        ];
      }
      $fullPrice += $memberPrices[$cnt]->price;
      $fullMinusFree += $memberPrices[$cnt]->priceMinusFree;
    }
    // Sort prices array in reverse order, but keep indexes.
    $cnt = 0;
    if ($discountEnabled && usort($prices, [$this, 'memberPriceCompare'])) {
      foreach ($prices as $curPrice) {
        $cnt++;
        // Check if discount applies (count divisible by number pre discount).
        if ($cnt % ($discountFreeEvery + 1) == 0) {
          $discountPrice += $curPrice->basePrice;
          // Take base price off member price (but leave add-ons).
          $memberPrices[$curPrice->memberNo]->price = $memberPrices[$curPrice->memberNo]->price - $curPrice->basePrice;
          $memberPrices[$curPrice->memberNo]->priceMinusFree = $memberPrices[$curPrice->memberNo]->priceMinusFree - $curPrice->basePrice;
          $memberPrices[$curPrice->memberNo]->basePriceMinusFree = $memberPrices[$curPrice->memberNo]->basePriceMinusFree - $curPrice->basePrice;
          // New message. Be sure to include add-on price if there is one.
          if ($memberPrices[$curPrice->memberNo]->price == 0) {
            $memberPrices[$curPrice->memberNo]->priceMessage = $this->t('Free member!');
          }
          else {
            $memberPrices[$curPrice->memberNo]->priceMessage = $this->t(
              'Free member! Price for add-on: @symbol<span id="@id">@price</span>',
              [
                '@symbol' => $symbol,
                '@id' => "member" . $curPrice->memberNo . "-value",
                '@price' => number_format($memberPrices[$curPrice->memberNo]->price, 2),
              ]);
          }
        }
      }
    }
    // Calculate total price with discounts.
    $totalPrice = $fullPrice - $discountPrice;
    $totalPriceMinusFree = $fullMinusFree - $discountPrice;

    return [
      $fullPrice,
      $discountPrice,
      $totalPrice,
      $totalPriceMinusFree,
      $memberPrices,
    ];
  }

  /**
   * Method to return the price of a member.
   */
  public function getMemberPrice(array $form_values, $memberNo, $types, $addOnPrice, $addOnMinusFree, $symbol, $defaultType) {
    $price = 0;
    // If type selected, look up value.
    $memberType = $form_values['members']['member' . $memberNo]['type'] ?? $defaultType;
    if (!empty($memberType)) {
      $price = $types[$memberType]->price;
    }

    $daysPrice = 0;
    $dayCodes = [];
    $dayNames = [];
    // Default days to none selected.
    $days = isset($types[$memberType]) ? trim($types[$memberType]->defaultDays) : '';
    $daysDesc = '';
    if (isset($types[$memberType]->days)) {
      // If day code = type code, whole weekend selected.
      if (isset($form_values['members']['member' . $memberNo]['dayOptions']['days'][$memberType]) && $form_values['members']['member' . $memberNo]['dayOptions']['days'][$memberType]) {
        $daysPrice = $price;
      }
      else {
        foreach ($types[$memberType]->days as $dayCode => $dayOptions) {
          if (isset($form_values['members']['member' . $memberNo]['dayOptions']['days'][$dayCode]) && $form_values['members']['member' . $memberNo]['dayOptions']['days'][$dayCode]) {
            $daysPrice += $dayOptions->price;
            $dayCodes[] = $dayCode;
            $dayNames[] = $dayOptions->name;
          }
        }
      }
      if ($daysPrice > 0 and $daysPrice < $price) {
        $price = $daysPrice;
        $days = implode('|', $dayCodes);
        $daysDesc = implode(', ', $dayNames);
      }
    }
    $basePrice = $price;

    // Make sure price can never be negative.
    if ($price < 0) {
      $price = 0;
    }

    $priceMessage = $this->t('Price for member #@number: @symbol<span id="@id">@price</span>', [
      '@number' => $memberNo,
      '@symbol' => $symbol,
      '@id' => "member$memberNo-value",
      '@price' => number_format($price + $addOnPrice, 2),
    ]);

    return (object) [
      'memberNo' => $memberNo,
      'price' => $price + $addOnPrice,
      'priceMinusFree' => $price + $addOnMinusFree,
      'priceMessage' => $priceMessage,
      'basePrice' => $basePrice,
      'basePriceMinusFree' => $basePrice,
      'addOnPrice' => $addOnPrice,
      'addOnMinusFree' => $addOnMinusFree,
      'memberType' => $memberType,
      'days' => $days,
      'daysDesc' => $daysDesc,
    ];
  }

}
