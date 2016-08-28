<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregRegistrationForm
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\node\NodeInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregRegistrationForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a new EmailExampleGetFormPage.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.mail'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_register';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $memberPrices = array();
    
    $config = $this->config('simple_conreg.settings');
    list($typeOptions, $typePrices) = SimpleConregOptions::memberTypes($config);
    list($addOnOptions, $addOnPrices) = SimpleConregOptions::memberAddons($config);
    $symbol = $config->get('payments.symbol');
    $countryOptions = SimpleConregOptions::memberCountries($config);
    $defaultCountry = $config->get('reference.default_country');
    // Check if discounts enabled.
    $discountEnabled = $config->get('discount.enable');
    $discountFreeEvery = $config->get('discount.free_every');

    // Get thenumber of members on the form.
    $memberQty = $form_values['global']['member_quantity'];
    if (empty($memberQty))
      $memberQty = 1;
    
    // Calculate price for all members.
    list($fullPrice, $discountPrice, $totalPrice, $memberPrices) =
      $this->getAllMemberPrices($form_values, $memberQty, $typePrices, $addOnPrices, $symbol, $discountEnabled, $discountFreeEvery);
  
    $form = array(
      '#tree' => TRUE,
      '#prefix' => '<div id="regform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_form')
      ),
    );

    $form['intro'] = array(
      '#markup' => $config->get('registration_intro'),
    );

    $form['global'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('How many members?'),
    );

    $qtyOptions = array();
    for ($cnt = 1; $cnt <=6; $cnt++) $qtyOptions[$cnt] = $cnt;
    $form['global']['member_quantity'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select number of members to register'),
      '#options' => $qtyOptions,
      '#default_value' => 1,
      '#attributes' => array('class' => array('edit-member-quantity')),
      '#ajax' => array(
        'wrapper' => 'regform',
        'callback' => array($this, 'updateMemberQuantityCallback'),
        'event' => 'change',
      ),
    );

    $form['members'] = array(
      '#prefix' => '<div id="members">',
      '#suffix' => '</div>',
    );

    for ($cnt=1; $cnt<=$memberQty; $cnt++) {
      $form['members']['member'.$cnt] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Member @number', array('@number' => $cnt)),
      );

      $form['members']['member'.$cnt]['first_name'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.first_name_label'),
        '#size' => 29,
        '#attributes' => array(
          'id' => "edit-members-member$cnt-first-name",
          'class' => array('edit-members-first-name')),
        '#required' => ($config->get('fields.first_name_mandatory') ? TRUE : FALSE),
      );

      $form['members']['member'.$cnt]['last_name'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.last_name_label'),
        '#size' => 29,
        '#attributes' => array(
          'id' => "edit-members-member$cnt-last-name",
          'class' => array('edit-members-last-name')),
        '#required' => ($config->get('fields.last_name_mandatory') ? TRUE : FALSE),
      );

      $form['members']['member'.$cnt]['email'] = array(
        '#type' => 'email',
        '#title' => $config->get('fields.email_label'),
      );
      if ($cnt==1) {
        $form['members']['member'.$cnt]['email']['#required'] = TRUE;
        $form['members']['member'.$cnt]['email']['#description'] = $this->t('Email address required for first member.');
      } else {
        $form['members']['member'.$cnt]['email']['#description'] = $this->t('If you don\'t provide an email, you will have to get convention updates from the first member.');
      }

      $form['members']['member'.$cnt]['type'] = array(
        '#type' => 'select',
        '#title' => $config->get('fields.membership_type_label'),
        '#options' => $typeOptions,
        '#required' => TRUE,
        '#ajax' => array(
          'callback' => array($this, 'updateMemberPriceCallback'),
          'event' => 'change',
        ),
      );

      if (!empty($config->get('add_ons.label'))) {
        $form['members']['member'.$cnt]['add_on'] = array(
          '#type' => 'select',
          '#title' => $config->get('add_ons.label'),
          '#description' => $config->get('add_ons.description'),
          '#options' => $addOnOptions,
          '#required' => TRUE,
          '#ajax' => array(
            'callback' => array($this, 'updateMemberPriceCallback'),
            'event' => 'change',
          ),
        );

        $form['members']['member'.$cnt]['add_on_extra'] = array(
          '#prefix' => '<div id="memberAddOnInfo'.$cnt.'">',
          '#suffix' => '</div>',
        );

        // Check if something other than the first value in add-on list selected. Display add-on info field if so. Use current(array_keys()) to get first add-on option.
        if (!empty($form_values['members']['member'.$cnt]['add_on']) &&
            $form_values['members']['member'.$cnt]['add_on']!=current(array_keys($addOnOptions)) &&
            !empty($config->get('add_on_info.label'))) {
          $form['members']['member'.$cnt]['add_on_extra']['info'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('add_on_info.label'),
            '#description' => $config->get('add_on_info.description'),
          );
        }
      }

      // Display price for selected member type and add-ons.
      list($price, $priceMessage) = $memberPrices[$cnt];
      $form['members']['member'.$cnt]['price'] = array(
        '#prefix' => '<div id="memberPrice'.$cnt.'">',
        '#suffix' => '</div>',
        '#markup' => $priceMessage,
      );

      $form['members']['member'.$cnt]['badge_name_option'] = array(
        '#type' => 'radios',
        '#title' => $config->get('fields.badge_name_option_label'),
        '#description' => $config->get('fields.badge_name_description'),
        '#options' => array('N' => $this->t('Full name on badge'),
                            'F' => $this->t('First name only'),
                            'O' => $this->t('Other badge name')),
        '#default_value' => 'N',
        '#required' => TRUE,
        '#attributes' => array(
          'class' => array('edit-members-badge-name-option')),
        '#ajax' => array(
          'callback' => array($this, 'updateMemberBadgeNameCallback'),
          'event' => 'change',
        ),
      );

      $form['members']['member'.$cnt]['badge_name'] = array(
        '#prefix' => '<div id="memberBadgeName'.$cnt.'">',
        '#suffix' => '</div>',
      );

      // Check if "other" selected for badge name option, and display badge name textbox.
      if (!empty($form_values['members']['member'.$cnt]['badge_name_option']) &&
          $form_values['members']['member'.$cnt]['badge_name_option']=='O') {
        $form['members']['member'.$cnt]['badge_name']['other'] = array(
          '#type' => 'textfield',
          '#title' => $config->get('fields.badge_name_label'),
          '#required' => TRUE,
          '#attributes' => array(
            'id' => "edit-members-member$cnt-badge-name",
            'class' => array('edit-members-badge-name')),
        );
      }

      $form['members']['member'.$cnt]['display'] = array(
        '#type' => 'select',
        '#title' => $config->get('fields.display_label'),
        '#description' => $this->t('Select how you would like to appear on the membership list.'),
        '#options' => SimpleConregOptions::display(),
        '#default_value' => 'F',
        '#required' => TRUE,
      );

      if (!empty($config->get('fields.communication_method_label'))) {
        $form['members']['member'.$cnt]['communication_method'] = array(
          '#type' => 'select',
          '#title' => $config->get('fields.communication_method_label'),
          '#options' => SimpleConregOptions::communicationMethod(),
          '#default_value' => 'E',
          '#required' => TRUE,
        );
      }

      if ($cnt > 1 && !empty($config->get('fields.same_address_label'))) {
        $form['members']['member'.$cnt]['same_address'] = array(
          '#type' => 'checkbox',
          '#title' => $config->get('fields.same_address_label'),
          '#ajax' => array(
            'callback' => array($this, 'updateMemberAddressCallback'),
            'event' => 'change',
          ),
        );
      }
      
      $form['members']['member'.$cnt]['address'] = array(
        '#prefix' => '<div id="memberAddress'.$cnt.'">',
        '#suffix' => '</div>',
      );

      if (empty($form_values['members']['member'.$cnt]['same_address']))
        $same = FALSE;
      else
        $same = $form_values['members']['member'.$cnt]['same_address'];

      // Always show address for member 1, and for other members if "same" box isn't checked.
      if ($cnt == 1 || !$same) {
        if (!empty($config->get('fields.street_label'))) {
          $form['members']['member'.$cnt]['address']['street'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('fields.street_label'),
          );
        }

        if (!empty($config->get('fields.street2_label'))) {
          $form['members']['member'.$cnt]['address']['street2'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('fields.street2_label'),
          );
        }

        if (!empty($config->get('fields.city_label'))) {
          $form['members']['member'.$cnt]['address']['city'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('fields.city_label'),
          );
        }

        if (!empty($config->get('fields.county_label'))) {
          $form['members']['member'.$cnt]['address']['county'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('fields.county_label'),
          );
        }

        if (!empty($config->get('fields.postcode_label'))) {
          $form['members']['member'.$cnt]['address']['postcode'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('fields.postcode_label'),
          );
        }

        if (!empty($config->get('fields.country_label'))) {
          $form['members']['member'.$cnt]['address']['country'] = array(
            '#type' => 'select',
            '#title' => $config->get('fields.country_label'),
            '#options' => $countryOptions,
            '#default_value' => $defaultCountry,
            '#required' => TRUE,
          );
        }
      }

      if (!empty($config->get('fields.phone_label'))) {
        $form['members']['member'.$cnt]['phone'] = array(
          '#type' => 'tel',
          '#title' => $config->get('fields.phone_label'),
        );
      }

      if (!empty($config->get('fields.birth_date_label'))) {
        $form['members']['member'.$cnt]['birth_date'] = array(
          '#type' => 'date',
          '#title' => $config->get('fields.birth_date_label'),
        );
      }

      if (!empty($config->get('extras.flag1'))) {
        $form['members']['member'.$cnt]['extra_flag1'] = array(
          '#type' => 'checkbox',
          '#title' => $config->get('extras.flag1'),
        );
      }

      if (!empty($config->get('extras.flag2'))) {
        $form['members']['member'.$cnt]['extra_flag2'] = array(
          '#type' => 'checkbox',
          '#title' => $config->get('extras.flag2'),
        );
      }
    }

    $form['payment'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Total price'),
    );

    $form['payment']['price'] = array(
      '#prefix' => '<div id="Pricing">',
      '#suffix' => '</div>',
    );

    if ($discountPrice > 0) {
      $form['payment']['price']['full_price'] = array(
        '#prefix' => '<div id="fullPrice">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Amount before discount: @symbol@full',
                     ['@symbol' => $symbol, '@full' => $fullPrice]),
      );
      $form['payment']['price']['discount_price'] = array(
        '#prefix' => '<div id="discountPrice">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Total discount: @symbol@discount',
                     ['@symbol' => $symbol, '@discount' => $discountPrice]),
      );
    }
    $form['payment']['price']['total_price'] = array(
      '#prefix' => '<div id="totalPrice">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Total amount to pay: @symbol@total', 
                   ['@symbol' => $symbol, '@total' => $totalPrice]),
    );

    $form['payment']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Proceed to payment page'),
    );

    $form_state->set('total_price', $totalPrice);
    return $form;
  }
  
  // Callback function for "number of members" drop down.
  public function updateMemberQuantityCallback(array $form, FormStateInterface $form_state) {
    // Form rebuilt with required number of members before callback. Return new form.
    //$ajax_response = new AjaxResponse();
    //$ajax_response->addCommand(new HtmlCommand('#regform', render($form)));
    return $form;
  }

  // Callback function for "member type" and "add-on" drop-downs. Replace price fields.
  public function updateMemberPriceCallback(array $form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    // Calculate price for each member.
    $memberQty = $form_state->getValue(array('global', 'member_quantity'));
    for ($cnt=1; $cnt<=$memberQty; $cnt++) {
      $ajax_response->addCommand(new HtmlCommand('#memberAddOnInfo'.$cnt, render($form['members']['member'.$cnt]['add_on_extra']['info'])));
      $ajax_response->addCommand(new HtmlCommand('#memberPrice'.$cnt, $form['members']['member'.$cnt]['price']['#markup']));
    }
    $ajax_response->addCommand(new HtmlCommand('#Pricing', $form['payment']['price']));

    return $ajax_response;
  }

  // Callback function for "badge name" radios. Show badge name field if required.
  public function updateMemberBadgeNameCallback(array $form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    // Calculate price for each member.
    $memberQty = $form_state->getValue(array('global', 'member_quantity'));
    for ($cnt=1; $cnt<=$memberQty; $cnt++) {
      if (isset($form['members']['member'.$cnt]['badge_name']['other']))
        $ajax_response->addCommand(new HtmlCommand('#memberBadgeName'.$cnt, render($form['members']['member'.$cnt]['badge_name']['other'])));
      else
        $ajax_response->addCommand(new HtmlCommand('#memberBadgeName'.$cnt, ""));
    }

    return $ajax_response;
  }

  // Callback function for "same as first member" checkbox. Replace address block.
  public function updateMemberAddressCallback(array $form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    // Don't show address fields if "same as member 1 box" ticked.
    $memberQty = $form_state->getValue(array('global', 'member_quantity'));
    // Only need to reshow from member 2 up.
    for ($cnt=2; $cnt<=$memberQty; $cnt++) {
      $ajax_response->addCommand(new HtmlCommand('#memberAddress'.$cnt, render($form['members']['member'.$cnt]['address'])));
    }

    return $ajax_response;
  }

  // Function to validate fields as you type (currently unused).
  public function updateMandatoryValidationCallback(array $form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    // Check if all mandatory fields have values.
    $ajax_response->addCommand(new HtmlCommand('#mandatory', $form['payment']['mandatory']['#markup']));
    return $ajax_response;
  }
  
  // Function to recursively check that all mandatory fields have been populated.
  public function checkMandatoryPopulated(array $elements, array $values) {
    foreach($elements as $key=>$val) {
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
            } else {
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
          } else {
            // Call recursively with empty array (only fails if required key present).
            if (!$this->checkMandatoryPopulated($val, array())) {
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $memberQty = $form_values['global']['member_quantity'];
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Check that either first name or last name has been entered. Will only arise if both first and last name are optional fields.
      if ((empty($form_values['members']['member'.$cnt]['first_name']) ||
           empty(trim($form_values['members']['member'.$cnt]['first_name']))) &&
          (empty($form_values['members']['member'.$cnt]['last_name']) ||
           empty(trim($form_values['members']['member'.$cnt]['last_name'])))) {
        $form_state->setErrorByName('members][member'.$cnt.'][first_name', $this->t('You must enter either first name or last name'));
        $form_state->setErrorByName('members][member'.$cnt.'][last_name');
      }
      // Check that if first name selected for badge, that first name has actually been entered.
      if ($form_values['members']['member'.$cnt]['badge_name_option']=='F' &&
          (empty($form_values['members']['member'.$cnt]['first_name']) ||
           empty(trim($form_values['members']['member'.$cnt]['first_name'])))) {
        $form_state->setErrorByName('members][member'.$cnt.'][first_name', $this->t('You cannot choose first name for badge unless you enter a first name'));
      }
      // Check that if the "other" option has been chosen for badge name, that a badge name has been entered.
      if ($form_values['members']['member'.$cnt]['badge_name_option']=='O' &&
          (empty($form_values['members']['member'.$cnt]['badge_name']['other']) ||
           empty(trim($form_values['members']['member'.$cnt]['badge_name']['other'])))) {
        $form_state->setErrorByName('members][member'.$cnt.'][badge_name][other', $this->t('Please enter your badge name'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    
    $config = $this->config('simple_conreg.settings');
    $symbol = $config->get('payments.symbol');
    $discountEnabled = $config->get('discount.enable');
    $discountFreeEvery = $config->get('discount.free_every');
    list($typeOptions, $typePrices, $defaultBadgeTypes) = SimpleConregOptions::memberTypes($config);
    list($addOnOptions, $addOnPrices) = SimpleConregOptions::memberAddons($config);
    
    // Find out number of members.
    $memberQty = $form_values['global']['member_quantity'];
    $members = $form_state->get('members');
    
    // Can't rely on price sent back from form, so recalculate.
    list($fullPrice, $discountPrice, $totalPrice, $memberPrices) =
      $this->getAllMemberPrices($form_values, $memberQty, $typePrices, $addOnPrices, $symbol, $discountEnabled, $discountFreeEvery);
    
    // Gather the current user so the new record has ownership.
    $account = \Drupal::currentUser();
    $lead_mid = 0;
    $lead_key = 0;
    
    // Set up parameters for confirmation email.
    $confirm_params = array();
    $confirm_params["quantity"] = $memberQty;
    $confirm_params["email"] = $form_values['members']['member1']['email'];
    $confirm_params["first"] = $form_values['members']['member1']['first_name'];
    $confirm_params["last"] = $form_values['members']['member1']['last_name'];
    $confirm_params["members"] = array();
    $confirm_params['from'] = $config->get('confirmation.from_name').' <'.$config->get('confirmation.from_email').'>';
    
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Check member price.
      list($price, $priceMessage) = $memberPrices[$cnt];

      // Look up the member type, and get the default badge type for member type.
      $member_type = $form_values['members']['member'.$cnt]['type'];
      if (isset($defaultBadgeTypes[$member_type])) {
        $badge_type = $defaultBadgeTypes[$member_type];
      } else
        $badge_type = 'A'; // This shouldn't happen, but if no default badge type found, hard code to A.

      // Check whether to use name or "other" badge name...
      switch($form_values['members']['member'.$cnt]['badge_name_option']) {
        case 'N':
          $badge_name = trim($form_values['members']['member'.$cnt]['first_name'].' '.$form_values['members']['member'.$cnt]['last_name']);
          break;
        case 'F':
          $badge_name = trim($form_values['members']['member'.$cnt]['first_name']);
          break;
        case 'O':
          $badge_name = trim($form_values['members']['member'.$cnt]['badge_name']['other']);
          break;
      }
    
      // If "same" checkbox ticked for member, use member 1 for address fields.
      if ($cnt == 1 || $form_values['members']['member'.$cnt]['same_address'])
        $addressMember = 1;
      else
        $addressMember = $cnt;
      // Assign random key for payment URL.
      $rand_key = mt_rand();
      // If no date, use NULL.
      if (isset($form_values['members']['member'.$cnt]['birth_date']) && preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $form_values['members']['member'.$cnt]['birth_date'])) {
        $birth_date = $form_values['members']['member'.$cnt]['birth_date'];
      } else {
        $birth_date = NULL;
      }
      // Save the submitted entry.
      $entry = array(
        'lead_mid' => $lead_mid,
        'random_key' => $rand_key,
        'member_type' => $member_type,
        'first_name' => $form_values['members']['member'.$cnt]['first_name'],
        'last_name' => $form_values['members']['member'.$cnt]['last_name'],
        'badge_name' => $badge_name,
        'badge_type' => $badge_type,
        'display' => $form_values['members']['member'.$cnt]['display'],
        'communication_method' => isset($form_values['members']['member'.$cnt]['communication_method']) ?
            $form_values['members']['member'.$cnt]['communication_method'] : '',
        'email' => $form_values['members']['member'.$cnt]['email'],
        'street' => isset($form_values['members']['member'.$addressMember]['address']['street']) ?
            $form_values['members']['member'.$addressMember]['address']['street'] : '',
        'street2' => isset($form_values['members']['member'.$addressMember]['address']['street2']) ?
            $form_values['members']['member'.$addressMember]['address']['street2'] : '',
        'city' => isset($form_values['members']['member'.$addressMember]['address']['city']) ?
            $form_values['members']['member'.$addressMember]['address']['city'] : '',
        'county' => isset($form_values['members']['member'.$addressMember]['address']['county']) ?
            $form_values['members']['member'.$addressMember]['address']['county'] : '',
        'postcode' => isset($form_values['members']['member'.$addressMember]['address']['postcode']) ?
            $form_values['members']['member'.$addressMember]['address']['postcode'] : '',
        'country' => isset($form_values['members']['member'.$addressMember]['address']['country']) ?
            $form_values['members']['member'.$addressMember]['address']['country'] : '',
        'phone' => isset($form_values['members']['member'.$cnt]['phone']) ?
            $form_values['members']['member'.$cnt]['phone'] : '',
        'birth_date' => $birth_date,
        'add_on' => isset($form_values['members']['member'.$cnt]['add_on']) ?
            $form_values['members']['member'.$cnt]['add_on'] : '',
        'add_on_info' => isset($form_values['members']['member'.$cnt]['add_on_extra']['info']) ?
            $form_values['members']['member'.$cnt]['add_on_extra']['info'] : '',
        'extra_flag1' => isset($form_values['members']['member'.$cnt]['extra_flag1']) ?
            $form_values['members']['member'.$cnt]['extra_flag1'] : 0,
        'extra_flag2' => isset($form_values['members']['member'.$cnt]['extra_flag2']) ?
            $form_values['members']['member'.$cnt]['extra_flag2'] : 0,
        'member_price' => $price,
        'payment_amount' => $totalPrice,
        'join_date' => time(),
      );
      // Add member details to parameters for email.
      $confirm_params["members"][$cnt] = $entry;
      // Insert to database table.
      $return = SimpleConregStorage::insert($entry);
      
      if ($return) {
        drupal_set_message(t('Thank you for registering @first_name @last_name.',
                             array('@first_name' => $entry['first_name'],
                                   '@last_name' => $entry['last_name'])));
      }
      if ($cnt == 1) {
        // For first member, get key from insert statement to use for mead member ID.
        $lead_mid = $return;
        $lead_key = $rand_key;
        // Update first member with own member ID as lead member ID.
        $update = array('mid' => $lead_mid, 'lead_mid' => $lead_mid);
        $return = SimpleConregStorage::update($update);
      }

      // Check Simplenews module loaded.
      if (\Drupal::moduleHandler()->moduleExists('simplenews')) {
        // Get Drupal SimpleNews subscription manager.
        $subscription_manager = \Drupal::service('simplenews.subscription_manager');
        // Simplenews is active, so check for mailing lists member should be subscribed to.
        $simplenews_options = $config->get('simplenews.options');
        foreach ($simplenews_options as $newsletter_id => $options) {
          if ($options['active']) {
            // Get communications methods selected for newsletter.
            $communications_methods = $simplenews_options[$newsletter_id]['communications_methods'];
            // Check if member matches newsletter criteria.
            if (isset($entry['email']) &&
                $entry['email'] != '' &&
                isset($entry['communication_method']) &&
                isset($communications_methods[$entry['communication_method']]) &&
                $communications_methods[$entry['communication_method']]) {
              // Subscribe member if criteria met.
              $subscription_manager->subscribe($entry['email'], $newsletter_id, FALSE, 'website');
            }
          }
        }
      }
    }

    // Add payment URL to confirmation params (couldn't do before saving as keys weren't known).
    $confirm_params["payment_url"] = \Drupal\Core\Url::fromRoute('simple_conreg_payment',
      array('mid' => $lead_mid, 'key' => $lead_key),
      array('absolute' => TRUE)
    )->toString();

    $module = "simple_conreg";
    $key = "confirmation_message";
    $to = $confirm_params["email"];
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    $result = $this->mailManager->mail($module, $key, $to, $language_code, $confirm_params);

    // If copy_us checkbox checked, send a copy to us.
    if ($config->get('confirmation.copy_us')) {
      // Send a copy of confirmation email to organiser.
      $key = "organiser_copy_message";
      $to = $config->get('confirmation.from_email');
      $result = $this->mailManager->mail($module, $key, $to, $language_code, $confirm_params);
    }
    
    // If copy email to field provided, send an extra copy to us.
    if (!empty($config->get('confirmation.copy_email_to'))) {
      // Send a copy of confirmation email to organiser.
      $key = "organiser_copy_message";
      $to = $config->get('confirmation.copy_email_to');
      $result = $this->mailManager->mail($module, $key, $to, $language_code, $confirm_params);
    }
    
    // Redirect to payment form.
    $form_state->setRedirect('simple_conreg_payment',
      array('mid' => $lead_mid, 'key' => $lead_key)
    );
  }


  /**
   * Method to calculate price of all members, and subtract any discounts.
   */
  public function getAllMemberPrices($form_values, $memberQty, $typePrices, $addOnPrices, $symbol,
                                     $discountEnabled, $discountFreeEvery) {
    $fullPrice = 0;
    $discountPrice = 0;
    $totalPrice = 0;
    $memberPrices = [];
    $prices = [];
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Check member price.
      $memberPrices[$cnt] = $this->getMemberPrice($form_values, $cnt, $typePrices, $addOnPrices, $symbol);
      list($price, $priceMessage, $basePrice) = $memberPrices[$cnt];
      if ($basePrice > 0)
        $prices[$cnt] = $basePrice;
      //$members['member'.$cnt]['price'] = $price;
      $fullPrice += $price;
    }
    // Sort prices array in reverse order.
    $cnt = 0;
    if ($discountEnabled && arsort($prices)) {
      foreach ($prices as $memberNo => $curPrice) {
        $cnt++;
        // Check if discount applies (count divisible by number pre discount).
        if ($cnt % ($discountFreeEvery + 1) == 0) {
          $discountPrice += $curPrice;
          list($price, $priceMessage, $basePrice) = $memberPrices[$cnt];
          // Take base price off member price (but leave add-ons).
          $newPrice = $price - $basePrice;
          // New message. Be sure to include add-on price if there is one.
          if ($newPrice == 0)
            $message = $this->t('Free member!');
          else
            $message = $this->t('Free member! Price for add-on: @symbol@price', array(
              '@symbol' => $symbol,
              '@price' => $newPrice));
          // Update member prices array.
          $memberPrices[$memberNo] = [$newPrice, $message, $basePrice];
        }
      }
    }
    // Calculate total price with discounts.
    $totalPrice = $fullPrice - $discountPrice;
    return [$fullPrice, $discountPrice, $totalPrice, $memberPrices];
  }


  /**
   * Method to return the price of a member
   */
  public function getMemberPrice(array $form_values, $memberNo, $typePrices, $addOnPrices, $symbol) {
    $price = 0;
    // If type selected, look up value.
    if (!empty($form_values['members']['member'.$memberNo]['type'])) {
      $price = $typePrices[$form_values['members']['member'.$memberNo]['type']];
    }
    $basePrice = $price;
    // If add on selected, look up value.
    if (!empty($form_values['members']['member'.$memberNo]['add_on'])) {
      $price += $addOnPrices[$form_values['members']['member'.$memberNo]['add_on']];
    }
    
    //Make sure price can never be negative.
    if ($price < 0) {
      $price = 0;
    }
    
    $priceMessage = $this->t('Price for member #@number: @symbol@price', array(
        '@number' => $memberNo,
        '@symbol' => $symbol,
        '@price' => $price));
    return array($price, $priceMessage, $basePrice);
  }

}

