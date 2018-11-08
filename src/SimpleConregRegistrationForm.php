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
use Drupal\Core\Messenger\MessengerInterface;
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
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $memberPrices = array();

    // Fetch event name from Event table.
    if (count($event = SimpleConregEventStorage::load(['eid' => $eid])) < 3) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return $form;
    }

    if ($event['is_open'] == 0) {
      // Event not configured. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Sorry. This event is not currently open for registration.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return $form;
    }

    // Get config for event and fieldset.    
    $config = SimpleConregConfig::getConfig($eid);
    if (empty($config->get('payments.system'))) {
      // Event not configured. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return $form;
    }

    $types = SimpleConregOptions::memberTypes($eid, $config);
    $symbol = $config->get('payments.symbol');
    $countryOptions = SimpleConregOptions::memberCountries($eid, $config);
    $defaultCountry = $config->get('reference.default_country');
    list($addOnOptions, $addOnPrices) = SimpleConregOptions::memberAddons($eid, $config);
    // Check if discounts enabled.
    $discountEnabled = $config->get('discount.enable');
    $discountFreeEvery = $config->get('discount.free_every');

    // Get thenumber of members on the form.
    $memberQty = isset($form_values['global']['member_quantity']) ? $form_values['global']['member_quantity'] : 1;
    
    // Calculate price for all members.
    list($fullPrice, $discountPrice, $totalPrice, $memberPrices) =
      $this->getAllMemberPrices($form_values, $memberQty, $types->types, $addOnPrices, $symbol, $discountEnabled, $discountFreeEvery);
  
    $form = array(
      '#tree' => TRUE,
      '#cache' => ['max-age' => 0],
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

    $optionCallbacks = [];
    // Array to store fieldset for each member.
    $memberFieldsets = [];
    // Get the previous fieldsets to compare.
    $prevFieldsets = $form_state->get('member_fieldsets');
    $memberFieldsetChanged = FALSE;

    for ($cnt=1; $cnt<=$memberQty; $cnt++) {
      // Get the fieldset config for the current member type, or if none defined, get the default fieldset config.
      $memberType = isset($form_values['members']['member'.$cnt]['type']) ? $form_values['members']['member'.$cnt]['type'] : '';
      if (!empty($memberType)) {
        $fieldsetConfig = $types->types[$memberType]->config;
        $memberFieldsets[$cnt] = $types->types[$memberType]->fieldset;
      }
      // No fieldset config found, so use default.
      if (empty($fieldsetConfig)) {
        $fieldsetConfig = SimpleConregConfig::getFieldsetConfig($eid, 0);
        $memberFieldsets[$cnt] = 0;
      }
      if ($memberFieldsets[$cnt] != $prevFieldsets[$cnt]) {
        $memberFieldsetChanged = TRUE;
      }

      $form['members']['member'.$cnt] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Member @number', array('@number' => $cnt)),
      );

      $form['members']['member'.$cnt]['first_name'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.first_name_label'),
        '#size' => 29,
        '#attributes' => array(
          'id' => "edit-members-member$cnt-first-name",
          'class' => array('edit-members-first-name')),
        '#required' => ($fieldsetConfig->get('fields.first_name_mandatory') ? TRUE : FALSE),
      );

      $form['members']['member'.$cnt]['last_name'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.last_name_label'),
        '#size' => 29,
        '#attributes' => array(
          'id' => "edit-members-member$cnt-last-name",
          'class' => array('edit-members-last-name')),
        '#required' => ($fieldsetConfig->get('fields.last_name_mandatory') ? TRUE : FALSE),
      );

      $form['members']['member'.$cnt]['email'] = array(
        '#type' => 'email',
        '#title' => $fieldsetConfig->get('fields.email_label'),
      );
      if ($cnt==1) {
        $form['members']['member'.$cnt]['email']['#required'] = TRUE;
        $form['members']['member'.$cnt]['email']['#description'] = $this->t('Email address required for first member.');
      } else {
        $form['members']['member'.$cnt]['email']['#description'] = $this->t('If you don\'t provide an email, you will have to get convention updates from the first member.');
      }

      $form['members']['member'.$cnt]['type'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.membership_type_label'),
        '#options' => $types->publicOptions,
        '#required' => TRUE,
        '#ajax' => array(
          'wrapper' => 'regform',
          'callback' => array($this, 'updateMemberPriceCallback'),
          'event' => 'change',
        ),
      );

      $form['members']['member'.$cnt]['dayOptions'] = array(
        '#prefix' => '<div id="memberDayOptions'.$cnt.'">',
        '#suffix' => '</div>',
      );
      // Get the current member type. If none selected, take the first entry in the options array.
      if (!empty($form_values['members']['member'.$cnt]['type'])) {
        $currentType = $form_values['members']['member'.$cnt]['type'];
        // If current member type has day options, display 
        if (count($types->types[$currentType]->dayOptions)) {
          // If day options available, we need to give them Ajax callbacks, and can't do a partial form update, so treat like fieldset change.
          $memberFieldsetChanged = TRUE;
          // Checkboxes for days.
          $form['members']['member'.$cnt]['dayOptions']['days'] = array(
            '#type' => 'checkboxes',
            '#title' => $fieldsetConfig->get('fields.membership_days_label'),
            '#description' => $fieldsetConfig->get('fields.membership_days_description'),
            '#options' => $types->types[$currentType]->dayOptions,
            '#attributes' => array(
              'class' => array('edit-members-days')),
            '#ajax' => array(
              'wrapper' => 'regform',
              'callback' => array($this, 'updateMemberPriceCallback'),
              'event' => 'change',
            ),
          );
        }
      }

      // Get member add-on details.
      $addon = isset($form_values['members']['member'.$cnt]['add_on']) ? $form_values['members']['member'.$cnt]['add_on'] : '';
      $form['members']['member'.$cnt]['add_on'] = SimpleConregAddons::getAddon($config,
        $addon,
        $addOnOptions, $cnt, [$this, 'updateMemberPriceCallback']);

      // Display price for selected member type and add-ons.
      $form['members']['member'.$cnt]['price'] = array(
        '#prefix' => '<div id="memberPrice'.$cnt.'">',
        '#suffix' => '</div>',
        '#markup' => $memberPrices[$cnt]->priceMessage,
      );

      $form['members']['member'.$cnt]['badge_name_option'] = array(
        '#type' => 'radios',
        '#title' => $fieldsetConfig->get('fields.badge_name_option_label'),
        '#description' => $fieldsetConfig->get('fields.badge_name_description'),
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
          '#title' => $fieldsetConfig->get('fields.badge_name_label'),
          '#required' => TRUE,
          '#attributes' => array(
            'id' => "edit-members-member$cnt-badge-name",
            'class' => array('edit-members-badge-name')),
        );
      }

      if (!empty($fieldsetConfig->get('fields.display_label'))) {
        $form['members']['member'.$cnt]['display'] = array(
          '#type' => 'select',
          '#title' => $fieldsetConfig->get('fields.display_label'),
          '#description' => $fieldsetConfig->get('fields.display_description'),
          '#options' => SimpleConregOptions::display(),
          '#default_value' => 'F',
          '#required' => TRUE,
        );
      } else {
        $form['members']['member'.$cnt]['display'] = array(
          '#prefix' => '<div id="memberDisplayMessage'.$cnt.'">',
          '#suffix' => '</div>',
          '#markup' => $fieldsetConfig->get('fields.display_description'),
        );
      }

      if (!empty($fieldsetConfig->get('fields.communication_method_label'))) {
        $form['members']['member'.$cnt]['communication_method'] = array(
          '#type' => 'select',
          '#title' => $fieldsetConfig->get('fields.communication_method_label'),
          '#options' => SimpleConregOptions::communicationMethod($eid, $config),
          '#default_value' => 'E',
          '#required' => TRUE,
        );
      }

      if ($cnt > 1 && !empty($fieldsetConfig->get('fields.same_address_label'))) {
        $form['members']['member'.$cnt]['same_address'] = array(
          '#type' => 'checkbox',
          '#title' => $fieldsetConfig->get('fields.same_address_label'),
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
        if (!empty($fieldsetConfig->get('fields.street_label'))) {
          $form['members']['member'.$cnt]['address']['street'] = array(
            '#type' => 'textfield',
            '#title' => $fieldsetConfig->get('fields.street_label'),
            '#required' => ($fieldsetConfig->get('fields.street_mandatory') ? TRUE : FALSE),
          );
        }

        if (!empty($fieldsetConfig->get('fields.street2_label'))) {
          $form['members']['member'.$cnt]['address']['street2'] = array(
            '#type' => 'textfield',
            '#title' => $fieldsetConfig->get('fields.street2_label'),
            '#required' => ($fieldsetConfig->get('fields.street2_mandatory') ? TRUE : FALSE),
          );
        }

        if (!empty($fieldsetConfig->get('fields.city_label'))) {
          $form['members']['member'.$cnt]['address']['city'] = array(
            '#type' => 'textfield',
            '#title' => $fieldsetConfig->get('fields.city_label'),
            '#required' => ($fieldsetConfig->get('fields.city_mandatory') ? TRUE : FALSE),
          );
        }

        if (!empty($fieldsetConfig->get('fields.county_label'))) {
          $form['members']['member'.$cnt]['address']['county'] = array(
            '#type' => 'textfield',
            '#title' => $fieldsetConfig->get('fields.county_label'),
            '#required' => ($fieldsetConfig->get('fields.county_mandatory') ? TRUE : FALSE),
          );
        }

        if (!empty($fieldsetConfig->get('fields.postcode_label'))) {
          $form['members']['member'.$cnt]['address']['postcode'] = array(
            '#type' => 'textfield',
            '#title' => $fieldsetConfig->get('fields.postcode_label'),
            '#required' => ($fieldsetConfig->get('fields.postcode_mandatory') ? TRUE : FALSE),
          );
        }

        if (!empty($fieldsetConfig->get('fields.country_label'))) {
          $form['members']['member'.$cnt]['address']['country'] = array(
            '#type' => 'select',
            '#title' => $fieldsetConfig->get('fields.country_label'),
            '#options' => $countryOptions,
            '#default_value' => $defaultCountry,
            '#required' => ($fieldsetConfig->get('fields.country_mandatory') ? TRUE : FALSE),
          );
        }
      }

      if (!empty($fieldsetConfig->get('fields.phone_label'))) {
        $form['members']['member'.$cnt]['phone'] = array(
          '#type' => 'tel',
          '#title' => $fieldsetConfig->get('fields.phone_label'),
        );
      }

      if (!empty($fieldsetConfig->get('fields.birth_date_label'))) {
        $form['members']['member'.$cnt]['birth_date'] = array(
          '#type' => 'date',
          '#title' => $fieldsetConfig->get('fields.birth_date_label'),
          '#required' => ($fieldsetConfig->get('fields.birth_date_mandatory') ? TRUE : FALSE),
        );
      }

      if (!empty($fieldsetConfig->get('fields.age_label'))) {
        $ageOptions = [];
        $min = $fieldsetConfig->get('fields.age_min');
        $max = $fieldsetConfig->get('fields.age_max');
        for ($age=$min; $age<=$max; $age++)
          $ageOptions[$age] = $age;
        $form['members']['member'.$cnt]['age'] = array(
          '#type' => 'select',
          '#title' => $fieldsetConfig->get('fields.age_label'),
          '#options' => $ageOptions,
          '#required' => ($fieldsetConfig->get('fields.age_mandatory') ? TRUE : FALSE),
        );
      }

      if (!empty($fieldsetConfig->get('extras.flag1'))) {
        $form['members']['member'.$cnt]['extra_flag1'] = array(
          '#type' => 'checkbox',
          '#title' => $fieldsetConfig->get('extras.flag1'),
        );
      }

      if (!empty($fieldsetConfig->get('extras.flag2'))) {
        $form['members']['member'.$cnt]['extra_flag2'] = array(
          '#type' => 'checkbox',
          '#title' => $fieldsetConfig->get('extras.flag2'),
        );
      }
      
      $callback = [$this, 'updateMemberOptionFields'];
      $fieldset = isset($types->types[$memberType]->fieldset) ? $types->types[$memberType]->fieldset : 0;
      SimpleConregFieldOptions::addOptionFields($eid, $fieldset, $form['members']['member'.$cnt], $form_values['members']['member'.$cnt], $optionCallbacks, $callback, $cnt);
    }

    $form['payment'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Total price'),
    );

    // Get global add-on details.
    $form['payment']['global_add_on'] = SimpleConregAddons::getAddon($config,
      (isset($form_values['payment']['global_add_on']) ? $form_values['payment']['global_add_on'] : NULL),
      $addOnOptions, 0, [$this, 'updateMemberPriceCallback']);

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

    $form_state->set('member_fieldsets', $memberFieldsets);
    $form_state->set('fieldset_changed', $memberFieldsetChanged);
    $form_state->set('option_callbacks', $optionCallbacks);
    $form_state->set('total_price', $totalPrice);
    return $form;
  }
  
  // Callback function for "number of members" drop down.
  public function updateMemberQuantityCallback(array $form, FormStateInterface $form_state) {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  // Callback function for "member type" and "add-on" drop-downs. Replace price fields.
  public function updateMemberPriceCallback(array $form, FormStateInterface $form_state) {
    // Check if fieldset has changed, which will require a full form refresh to update the member fields.
    $memberFieldsetChanged = $form_state->get('fieldset_changed');
    if ($memberFieldsetChanged) {
      // Get the triggering element.    
      $trigger = $form_state->getTriggeringElement()['#name'];
      if (preg_match("/^members\[member(\d+)\]\[(\w+)\]/", $trigger, $matches)) {
        // If the triggering element is the type drop-down, return the whole form;
        if ($matches[2] == 'type') {
          return $form;
        }
      }
    }
    // Member fieldset has not changed, so we only need to update the prices.
    $ajax_response = new AjaxResponse();
    // Calculate price for each member.
    $memberQty = $form_state->getValue(array('global', 'member_quantity'));
    for ($cnt=1; $cnt<=$memberQty; $cnt++) {
      if (!empty($form['members']['member'.$cnt]['add_on']['extra'])) {
        $ajax_response->addCommand(new HtmlCommand('#member_addon_info_'.$cnt, render($form['members']['member'.$cnt]['add_on']['extra']['info'])));
      }
      $ajax_response->addCommand(new HtmlCommand('#memberPrice'.$cnt, $form['members']['member'.$cnt]['price']['#markup']));
    }
    // If global addon, return update that.
    if (!empty($form['payment']['global_add_on']['extra'])) {
      $ajax_response->addCommand(new HtmlCommand('#global_addon_info', render($form['payment']['global_add_on']['extra']['info'])));
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

  // Callback function for option fields - add/remove detail field.
  public function updateMemberOptionFields(array $form, FormStateInterface $form_state) {
    // Get the triggering element.    
    $trigger = $form_state->getTriggeringElement()['#name'];
    // Get array of items to return, keyed by trigering element.
    $optionCallbacks = $form_state->get('option_callbacks');
    $callback = $optionCallbacks[$trigger];
    // Build the index of the element to return.
    switch ($callback[0]) {
      case 'group':
        return $form['members']['member'.$callback[1]][$callback[2]];
      case 'detail':
        return $form['members']['member'.$callback[1]][$callback[2]]['options']['container_'.$callback[3]];
    }
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
    $eid = $form_state->get('eid');

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
    $eid = $form_state->get('eid');

    $form_values = $form_state->getValues();
    
    $config = SimpleConregConfig::getConfig($eid);
    $symbol = $config->get('payments.symbol');
    $discountEnabled = $config->get('discount.enable');
    $discountFreeEvery = $config->get('discount.free_every');
    $types = SimpleConregOptions::memberTypes($eid, $config);
    list($addOnOptions, $addOnPrices) = SimpleConregOptions::memberAddons($eid, $config);
    
    // Check if global add-on.
    $global = $config->get('add_ons.global');
    
    // Find out number of members.
    $memberQty = $form_values['global']['member_quantity'];
    $members = $form_state->get('members');
    
    // Can't rely on price sent back from form, so recalculate.
    list($fullPrice, $discountPrice, $totalPrice, $memberPrices) =
      $this->getAllMemberPrices($form_values, $memberQty, $types->types, $addOnPrices, $symbol, $discountEnabled, $discountFreeEvery);
    
    // Gather the current user so the new record has ownership.
    $account = \Drupal::currentUser();
    $lead_mid = 0;
    $lead_key = 0;
    
    // Set up parameters for confirmation email.
    $confirm_params = array();
    $confirm_params["eid"] = $eid;
    $confirm_params["quantity"] = $memberQty;
    $confirm_params["email"] = $form_values['members']['member1']['email'];
    $confirm_params["first"] = $form_values['members']['member1']['first_name'];
    $confirm_params["last"] = $form_values['members']['member1']['last_name'];
    $confirm_params["members"] = array();
    $confirm_params['from'] = $config->get('confirmation.from_name').' <'.$config->get('confirmation.from_email').'>';
    
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Look up the member type, and get the default badge type for member type.
      $member_type = $form_values['members']['member'.$cnt]['type'];
      if (isset($defaultBadgeTypes[$member_type])) {
        $badge_type = $defaultBadgeTypes[$member_type];
      } else
        $badge_type = 'A'; // This shouldn't happen, but if no default badge type found, hard code to A.

      $fieldset = $types->types[$member_type]->fieldset;
      $optionVals = [];
      SimpleConregFieldOptions::procesOptionFields($eid, $fieldset, $form_values['members']['member'.$cnt], $optionVals);
    
      // Get add-on details.
      $addOn = isset($form_values['members']['member'.$cnt]['add_on']['option']) ?
        $form_values['members']['member'.$cnt]['add_on']['option'] : '';
      $addOnInfo = isset($form_values['members']['member'.$cnt]['add_on']['extra']['info']) ?
        $form_values['members']['member'.$cnt]['add_on']['extra']['info'] : '';

      // If global add-on and first member, add global add-on price and details.
      if ($global && $cnt==1) {
        $addOn = isset($form_values['payment']['global_add_on']['option']) ? $form_values['payment']['global_add_on']['option'] : '';
        if (!empty($addOn)) {
          $addOnInfo = isset($form_values['payment']['global_add_on']['extra']['info']) ? $form_values['payment']['global_add_on']['extra']['info'] : '';
          $memberPrices[$cnt]->addOnPrice = $addOnPrices[$addOn];
          $memberPrices[$cnt]->price = $memberPrices[$cnt]->basePrice + $memberPrices[$cnt]->addOnPrice;
        }

        // If global add on free amount, add value.
        if (!empty($freeAmount = $form_values['payment']['global_add_on']['free_amount'])) {
          $memberPrices[$cnt]->addOnPrice += $freeAmount;
          $memberPrices[$cnt]->price += $freeAmount;
        }
      }

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
        'eid' => $eid,
        'lead_mid' => $lead_mid,
        'random_key' => $rand_key,
        'member_type' => $memberPrices[$cnt]->memberType,
        'days' => $memberPrices[$cnt]->days,
        'first_name' => $form_values['members']['member'.$cnt]['first_name'],
        'last_name' => $form_values['members']['member'.$cnt]['last_name'],
        'badge_name' => $badge_name,
        'badge_type' => $badge_type,
        'display' => empty($form_values['members']['member'.$cnt]['display']) ?
            'N' : $form_values['members']['member'.$cnt]['display'],
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
        'age' => isset($form_values['members']['member'.$cnt]['age']) ?
            $form_values['members']['member'.$cnt]['age'] : 0,
        'add_on' => $addOn,
        'add_on_info' => $addOnInfo,
        'extra_flag1' => isset($form_values['members']['member'.$cnt]['extra_flag1']) ?
            $form_values['members']['member'.$cnt]['extra_flag1'] : 0,
        'extra_flag2' => isset($form_values['members']['member'.$cnt]['extra_flag2']) ?
            $form_values['members']['member'.$cnt]['extra_flag2'] : 0,
        'member_price' => $memberPrices[$cnt]->basePrice,
        'member_total' => $memberPrices[$cnt]->price,
        'add_on_price' => $memberPrices[$cnt]->addOnPrice,
        'payment_amount' => $totalPrice,
        'join_date' => time(),
      );
      // Add member details to parameters for email.
      $confirm_params["members"][$cnt] = $entry;
      // Insert to database table.
      $return = SimpleConregStorage::insert($entry);
      
      if ($return) {
        // Now we have the member ID we can save the field options.
        SimpleConregFieldOptions::insertOptionFields($return, $optionVals);
        \Drupal::messenger()->addMessage(t('Thank you for registering @first_name @last_name.',
                                           array('@first_name' => $entry['first_name'],
                                                 '@last_name' => $entry['last_name'])));
      }
      if ($cnt == 1) {
        // For first member, get key from insert statement to use for lead member ID.
        $lead_mid = $return;
        $lead_key = $rand_key;
        $lead_name = trim($entry['first_name'].' '.$entry['last_name']);
        $lead_postcode = trim($entry['postcode']);
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
        if (isset($simplenews_options)) {
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
    }

    // Redirect to payment form.
    $form_state->setRedirect('simple_conreg_payment',
      array('mid' => $lead_mid, 'key' => $lead_key, 'name' => $lead_name, 'postcode' => $lead_postcode)
    );
  }

  /**
   * Callback for sorting member prices.
   */
  public function MemberPriceCompare($a, $b)
  {
    if ($a->basePrice == $b->basePrice) {
      if ($a->memberNo == $b->memberNo)
        return 0; // Should never actually happen.
      return ($a->memberNo < $b->memberNo ? -1 : 1);
    }
    return ($a->basePrice > $b->basePrice ? -1 : 1);
  }

  /**
   * Method to calculate price of all members, and subtract any discounts.
   */
  public function getAllMemberPrices($form_values, $memberQty, $types, $addOnPrices, $symbol,
                                     $discountEnabled, $discountFreeEvery) {
    $fullPrice = 0;
    $discountPrice = 0;
    $totalPrice = 0;
    $memberPrices = [];
    $prices = [];
    for ($cnt = 1; $cnt <= $memberQty; $cnt++) {
      // Check member price.
      $memberPrices[$cnt] = $this->getMemberPrice($form_values, $cnt, $types, $addOnPrices, $symbol);
      if ($memberPrices[$cnt]->basePrice > 0)
        $prices[] = (object)['memberNo' => $memberPrices[$cnt]->memberNo,
                             'basePrice' => $memberPrices[$cnt]->basePrice];
      $fullPrice += $memberPrices[$cnt]->price;
    }
    // If global add on selected, look up value.
    $option = isset($form_values['payment']['global_add_on']['option']) ? $form_values['payment']['global_add_on']['option'] : '';
    if (!empty($option))
      $fullPrice += $addOnPrices[$option];
    // If global add on free amount, add value.
    $freeAmount = isset($form_values['payment']['global_add_on']['free_amount']) ? $form_values['payment']['global_add_on']['free_amount'] : '';
    if (!empty($freeAmount))
      $fullPrice += $freeAmount;
    // Sort prices array in reverse order, but keep indexes so memberPrices array can be referenced.
    $cnt = 0;
    if ($discountEnabled && usort($prices, [$this, 'MemberPriceCompare'])) {
      foreach ($prices as $curPrice) {
        $cnt++;
        // Check if discount applies (count divisible by number pre discount).
        if ($cnt % ($discountFreeEvery + 1) == 0) {
          $discountPrice += $curPrice->basePrice;
          // Take base price off member price (but leave add-ons).
          $memberPrices[$curPrice->memberNo]->price = $memberPrices[$curPrice->memberNo]->price - $curPrice->basePrice;
          // New message. Be sure to include add-on price if there is one.
          if ($memberPrices[$curPrice->memberNo]->price == 0)
            $memberPrices[$curPrice->memberNo]->priceMessage = $this->t('Free member!');
          else
            $memberPrices[$curPrice->memberNo]->priceMessage = $this->t('Free member! Price for add-on: @symbol@price', array(
              '@symbol' => $symbol,
              '@price' => $memberPrices[$curPrice->memberNo]->price));
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
  public function getMemberPrice(array $form_values, $memberNo, $types, $addOnPrices, $symbol)
  {
    $price = 0;
    // If type selected, look up value.
    $memberType = isset($form_values['members']['member'.$memberNo]['type']) ? $form_values['members']['member'.$memberNo]['type'] : '';
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
      if (isset($form_values['members']['member'.$memberNo]['dayOptions']['days'][$memberType]) && $form_values['members']['member'.$memberNo]['dayOptions']['days'][$memberType]) {
        $daysPrice = $price;
      }
      else {
        foreach($types[$memberType]->days as $dayCode => $dayOptions) {
          if (isset($form_values['members']['member'.$memberNo]['dayOptions']['days'][$dayCode]) && $form_values['members']['member'.$memberNo]['dayOptions']['days'][$dayCode]) {
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
    
    // If add on selected, look up value.
    $addOnPrice = 0;
    $option = isset($form_values['members']['member'.$memberNo]['add_on']['option']) ? $form_values['members']['member'.$memberNo]['add_on']['option'] : '';
    if (!empty($option)) {
      $addOnPrice = $addOnPrices[$option];
      $price += $addOnPrice;
    }

    // If add on free amount, add value.
    $freeAmount = isset($form_values['members']['member'.$memberNo]['add_on']['free_amount']) ? $form_values['members']['member'.$memberNo]['add_on']['free_amount'] : '';
    if (!empty($freeAmount)) {
      $addOnPrice += $freeAmount;
      $price += $freeAmount;
    }
    
    //Make sure price can never be negative.
    if ($price < 0) {
      $price = 0;
    }
    
    $priceMessage = $this->t('Price for member #@number: @symbol@price', [
        '@number' => $memberNo,
        '@symbol' => $symbol,
        '@price' => $price]);

    return (object)[
      'memberNo' => $memberNo,
      'price' => $price,
      'priceMessage' => $priceMessage,
      'basePrice' => $basePrice,
      'addOnPrice' => $addOnPrice,
      'memberType' => $memberType,
      'days' => $days,
      'daysDesc' => $daysDesc,
    ];
  }

}

