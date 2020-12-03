<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregForm
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class SimpleConregConfigForm extends ConfigFormBase {
  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_config';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'simple_conreg.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    // Fetch event name from Event table.
    if (count($event = SimpleConregEventStorage::load(['eid' => $eid])) < 3) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return parent::buildForm($form, $form_state);
    }

    $prevFieldset = $form_state->get('fieldset');
    $prevContainer = "fieldset_container_$prevFieldset";

    // Get fieldset from submitted form values.
    $vals = $form_state->getValues();
    $curFieldset = $vals['simple_conreg_fieldsets']['fieldset'];
    if (empty($curFieldset))
      $curFieldset = 0;
    $fieldsetContainer = "fieldset_container_$curFieldset";
    $form_state->set('fieldset', $curFieldset);
    
    // If fieldset has changed, save subbmitted field set values to previous field set.
    if ($prevFieldset != $curFieldset) {
      SimpleConregConfig::saveFieldsetConfig($eid, $prevFieldset, $vals['simple_conreg_fieldsets'][$prevContainer]);
    }

    // Get config for event and fieldset.    
    $config = SimpleConregConfig::getConfig($eid);
    $fieldsetConfig = SimpleConregConfig::getFieldsetConfig($eid, $curFieldset);

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-simple_conreg_event',
    );

    $form['simple_conreg_event'] = array(
      '#type' => 'details',
      '#title' => $this->t('Event Details'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_event']['event_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Event name'),
      '#default_value' => $event['event_name'],
    );

    $form['simple_conreg_event']['open'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Event registration open'),
      '#default_value' => $event['is_open'],
    );

    $form['simple_conreg_payments'] = array(
      '#type' => 'details',
      '#title' => $this->t('Payment System'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_payments']['system'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select payment system'),
      '#options' => array('Stripe'=>'Stripe', 'None'=>'None'),
      '#default_value' => $config->get('payments.system'),
    );  

    $form['simple_conreg_payments']['mode'] = array(
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => array('Test'=>'Test', 'Live'=>'Live'),
      '#default_value' => $config->get('payments.mode'),
    );  

    $form['simple_conreg_payments']['private_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment System Private (Secret) Key'),
      '#default_value' => $config->get('payments.private_key'),
    );  

    $form['simple_conreg_payments']['public_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment System Public (Publishable) Key'),
      '#default_value' => $config->get('payments.public_key'),
    );  

    $form['simple_conreg_payments']['currency'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Currency'),
      '#default_value' => $config->get('payments.currency'),
    );  

    $form['simple_conreg_payments']['symbol'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Currency symbol'),
      '#default_value' => $config->get('payments.symbol'),
    );  

    $form['simple_conreg_payments']['show_name'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display name field on payment form'),
      '#default_value' => $config->get('payments.name'),
    );

    $form['simple_conreg_payments']['show_postcode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display postcode field on payment form'),
      '#default_value' => $config->get('payments.postcode'),
    );


    // Member Information Section.

    $form['simple_conreg_members'] = array(
      '#type' => 'details',
      '#title' => $this->t('Member Information'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_members']['types'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Member types'),
      '#description' => $this->t('Put each membership type on a line with type code, description, name, price,  default badge type, and fieldset number separated by | character (e.g. J|Junior Attending|Junior|50|A|1). Optionally, add a field per day, consisting of Day Code~Description~Short Name~Price (e.g. Sa~Saturday~Sat~25)'),
      '#default_value' => $config->get('member_types'),
    );

    $form['simple_conreg_members']['upgrades'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Member upgrades'),
      '#description' => $this->t('Put allowed membership upgrade on a line each with from upgrade ID, type code, from day, to type code, to day, to badge, description, price separated by | character (e.g. 101|S|W|A|W|Attending upgrade|45).'),
      '#default_value' => $config->get('member_upgrades'),
    );

    $form['simple_conreg_members']['badge_types'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Badge types'),
      '#description' => $this->t('Put each badge type on a line with type code and description separated by | character (e.g. G|Guest).'),
      '#default_value' => $config->get('badge_types'),
    );

    $form['simple_conreg_members']['days'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Days'),
      '#description' => $this->t('Put each day type on a line with day code and description separated by | character (e.g. Sa|Saturday).'),
      '#default_value' => $config->get('days'),
    );

    $form['simple_conreg_members']['digits'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Digits in Member Numbers'),
      '#description' => $this->t('The number of digits to show in member numbers. Member numbers will be padded to this number with zeros, e.g. 0001.'),
      '#default_value' => $config->get('member_no_digits'),
    );  

    /* Intro text. */

    $form['simple_conreg_intros'] = array(
      '#type' => 'details',
      '#title' => $this->t('Introduction messages'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_intros']['registration_intro'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Registration page introduction'),
      '#description' => $this->t('Introductory message to be displayed at the top of the registration page.'),
      '#default_value' => $config->get('registration_intro'),
    );

    $form['simple_conreg_intros']['payment_intro'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Payment page introduction'),
      '#description' => $this->t('Introductory message to be displayed at the top of the payment page.'),
      '#default_value' => $config->get('payment_intro'),
    );

    /* Select fieldset. */

    $form['simple_conreg_fieldsets'] = array(
      '#type' => 'details',
      '#title' => $this->t('Field sets'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );


    $fieldsetOptions = [ 0 => 'Default' ];
    for ($cnt = 1; $cnt <=5; $cnt++) $fieldsetOptions[$cnt] = $cnt;
    $form['simple_conreg_fieldsets']['fieldset'] = array(
      '#type' => 'select',
      '#title' => $this->t('Current fieldset'),
      '#description' => $this->t('Note: changing fieldset saves the current fieldset values.'),
      '#options' => $fieldsetOptions,
      '#default_value' => 0,
      '#ajax' => array(
        'wrapper' => 'fieldset_container',
        'callback' => array($this, 'updateFieldsetCallback'),
        'event' => 'change',
      ),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer] = array(
      '#prefix' => '<div id="fieldset_container">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    );

    /* Field labels. */

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Field Labels'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['first_name_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('First name label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.first_name_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['last_name_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Last name label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.last_name_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['name_description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name description (discription to appear under both name fields)'),
      '#default_value' => $fieldsetConfig->get('fields.name_description'),
      '#maxlength' => 255, 
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['email_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email address label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.email_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['membership_type_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Type of membership label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.membership_type_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['membership_days_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Membership days label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.membership_days_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['membership_days_description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Membership days description'),
      '#default_value' => $fieldsetConfig->get('fields.membership_days_description'),
      '#maxlength' => 255, 
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['badge_name_option_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name option label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.badge_name_option_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['badge_name_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name label (required)'),
      '#default_value' => $fieldsetConfig->get('fields.badge_name_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['badge_name_description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name description'),
      '#default_value' => $fieldsetConfig->get('fields.badge_name_description'),
      '#maxlength' => 255, 
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['display_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Display name on membership list label (leave blank if member type not to be displayed)'),
      '#default_value' => $fieldsetConfig->get('fields.display_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['display_description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Display name on membership list description (description below display name field; if display name blank, this text will be displayed in place of the field)'),
      '#default_value' => $fieldsetConfig->get('fields.display_description'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['communication_method_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communication method label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.communication_method_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['same_address_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Same address as member 1 label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.same_address_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['street_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Street address label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.street_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['street2_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Street address line 2 label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.street2_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['city_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Town/city label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.city_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['county_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('County/state label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.county_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['postcode_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Postal code label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.postcode_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['country_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Country label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.country_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['phone_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Phone number label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.phone_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['birth_date_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Date of birth label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.birth_date_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['age_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Age label (leave empty to remove field)'),
      '#default_value' => $fieldsetConfig->get('fields.age_label'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['age_min'] = array(
      '#type' => 'number',
      '#title' => $this->t('Minimum age'),
      '#default_value' => $fieldsetConfig->get('fields.age_min'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_fields']['age_max'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum age'),
      '#default_value' => $fieldsetConfig->get('fields.age_max'),
    );

    /* Mandatory fields. */

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Mandatory Field'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['first_name'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('First name mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.first_name_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['last_name'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Last name mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.last_name_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['street'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Street address mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.street_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['street2'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Street address 2 mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.street2_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['city'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Town/City mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.city'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['county'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('County/State mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.county_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['postcode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Postal code mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.postcode_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['country'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Country mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.country_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['birth_date'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Date of birth mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.birth_date_mandatory'),
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_mandatory']['age'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Age mandatory'),
      '#default_value' => $fieldsetConfig->get('fields.age_mandatory'),
    );


    /*
     * Fields for extra flags.
     */
    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_extras'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Extras'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_extras']['flag1'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Flag 1 label'),
      '#default_value' => $fieldsetConfig->get('extras.flag1'),
    );  

    $form['simple_conreg_fieldsets'][$fieldsetContainer]['simple_conreg_extras']['flag2'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Flag 2 label'),
      '#default_value' => $fieldsetConfig->get('extras.flag2'),
    );  


    /*
     * Fields for communications method options.
     */
    $form['simple_conreg_communication'] = array(
      '#type' => 'details',
      '#title' => $this->t('Communications Methods'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_communication']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each communications method on a line with single character code, description and 1/0 for public/private, separated by | character (e.g. "E|Electronic|1").'),
      '#default_value' => $config->get('communications_method.options'),
    );  


    /*
     * Fields for options.
     */
    $form['simple_conreg_options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Options'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_options']['option_groups'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each option group on a line with group ID, field name to attach to, and group title, separated by | character (e.g. "1|extra_flag1|Please tick the areas you\'d like to volunteer").'),
      '#default_value' => $config->get('simple_conreg_options.option_groups'),
    );  

    $form['simple_conreg_options']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each option on a line with option ID, group ID, option title, detail title, detail required (1/0), weight (bigger number goes to bottom), and comma setprated list of fieldsets to include it, separated by | character (e.g. "1|1| Help with pre-con tasks|Please provide details of areas you\'d like to help|0|1|0,1").'),
      '#default_value' => $config->get('simple_conreg_options.options'),
    );  


    /*
     * Fields for add on choices and options.
     */
    $form['addons'] = array(
      '#type' => 'details',
      '#title' => $this->t('Add-ons'),
      '#group' => 'admin',
    );

    $form['addons']['simple_conreg_addons'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Add-ons'),
      '#tree' => TRUE,
    );

    $form['addons']['simple_conreg_addons']['global'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Global add-on (uncheck for add-on per member)'),
      '#default_value' => $config->get('add_ons.global'),
    );

    $form['addons']['simple_conreg_addons']['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $config->get('add_ons.label'),
    );  

    $form['addons']['simple_conreg_addons']['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('add_ons.description'),
    );  

    $form['addons']['simple_conreg_addons']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each add-on on a line with description and price separated by | character.'),
      '#default_value' => $config->get('add_ons.options'),
    );  

    $form['addons']['simple_conreg_addon_info'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Add-on Information'),
      '#tree' => TRUE,
    );

    $form['addons']['simple_conreg_addon_info']['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => t('If you would like to capture optional information about the add-on, please provide label and description for the information field.'),
      '#default_value' => $config->get('add_on_info.label'),
    );  

    $form['addons']['simple_conreg_addon_info']['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('add_on_info.description'),
    );  

    $form['addons']['simple_conreg_addon_free'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Add-on Free Input Amount'),
      '#tree' => TRUE,
    );

    $form['addons']['simple_conreg_addon_free']['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $config->get('add_on_free.label'),
    );  

    $form['addons']['simple_conreg_addon_free']['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('add_on_free.description'),
    );  

    /*
     * Display settings - entries per page.
     */

    $form['simple_conreg_display'] = array(
      '#type' => 'details',
      '#title' => $this->t('Display settings'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_display']['page_size'] = array(
      '#type' => 'number',
      '#title' => $this->t('Page size'),
      '#description' => $this->t('Number of entries per page on lists.'),
      '#default_value' => $config->get('display.page_size'),
    );  

    /*
     * Fields for reference data - countries list.
     */

    $form['simple_conreg_reference'] = array(
      '#type' => 'details',
      '#title' => $this->t('Reference'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_reference']['countries'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Country list'),
      '#description' => $this->t('Put each country on a line with 2-letter country code and name separated by | character.'),
      '#default_value' => $config->get('reference.countries'),
    );  

    $form['simple_conreg_reference']['default_country'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default country code'),
      '#description' => $this->t('Enter 2 letter country code of country to default to. Leave blank to have no default.'),
      '#default_value' => $config->get('reference.default_country'),
    );  


    /*
     * Fields for Thank You page.
     */

    $form['simple_conreg_thanks'] = array(
      '#type' => 'details',
      '#title' => $this->t('Thank You Page'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_thanks']['thank_you_message'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Thank You Message'),
      '#description' => $this->t('Text to appear on the thank you page displayed after payment completed. [reference] will be replaced with payment reference.'),
      '#default_value' => $config->get('thanks.thank_you_message'),
      '#format' => $config->get('thanks.thank_you_format'),
    );  


    /*
     * Fields for multiple member discounts.
     */

    $form['simple_conreg_discount'] = array(
      '#type' => 'details',
      '#title' => $this->t('Multiple Member Discounts'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_discount']['enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable discount for multiple memberships.'),
      '#default_value' => $config->get('discount.enable'),
    );  

    $form['simple_conreg_discount']['free_every'] = array(
      '#type' => 'number',
      '#title' => $this->t('Free member for every'),
      '#description' => $this->t('The number of paid members for every free member.'),
      '#default_value' => $config->get('discount.free_every'),
    );  


    /*
     * Fields for check-in page.
     */

    $form['simple_conreg_checkin'] = array(
      '#type' => 'details',
      '#title' => $this->t('Check In Page'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_checkin']['display'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default display'),
      '#description' => $this->t('The value for the "display" for new members added through checkin screen.'),
      '#default_value' => $config->get('checkin.display'),
    );  

    $form['simple_conreg_checkin']['communication_method'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default communicationmethod'),
      '#description' => $this->t('The value for the "communication method" for new members added through checkin screen.'),
      '#default_value' => $config->get('checkin.communication_method'),
    );  


    /*
     * Fields for confirmation emails.
     */

    $form['simple_conreg_confirmation'] = array(
      '#type' => 'details',
      '#title' => $this->t('Confirmation Email'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    // Template selection drop-down.
    $form['simple_conreg_confirmation']['format_html'] = array(
      '#type' => 'select',
      '#title' => $this->t('Format for emails'),
      '#options' => [0 => 'Plain text', 1 => 'HTML (still in development - do not use)'],
      '#default_value' => $config->get('confirmation.format_html'),
    );

    $form['simple_conreg_confirmation']['copy_us'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Send a copy of the confirmation email to the below address.'),
      '#default_value' => $config->get('confirmation.copy_us'),
    );  

    $form['simple_conreg_confirmation']['from_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('From email name'),
      '#description' => $this->t('Name that confirmation email is sent from.'),
      '#default_value' => $config->get('confirmation.from_name'),
    );  

    $form['simple_conreg_confirmation']['from_email'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('From email address'),
      '#description' => $this->t('Email address that confirmation email is sent from (if you check the above box, a copy will also be sent to this address).'),
      '#default_value' => $config->get('confirmation.from_email'),
    );  

    $form['simple_conreg_confirmation']['copy_email_to'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Copy email to'),
      '#description' => $this->t('Email address that an extra copy of the confirmation email gets sent to (leave blank if not required).'),
      '#default_value' => $config->get('confirmation.copy_email_to'),
    );

    $form['simple_conreg_confirmation']['template_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Confiramtion email subject'),
      '#default_value' => $config->get('confirmation.template_subject'),
    );

    $form['simple_conreg_confirmation']['template_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Confiramtion email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
      '#default_value' => $config->get('confirmation.template_body'),
      '#format' => $config->get('confirmation.template_format'),
    );  

    $form['simple_conreg_confirmation']['notification_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Admin notification subject'),
      '#default_value' => $config->get('confirmation.notification_subject'),
    );

    /* Member check. */

    $form['simple_conreg_member_check'] = array(
      '#type' => 'details',
      '#title' => $this->t('Member Check Settings'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_member_check']['member_check_intro'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Member check introduction'),
      '#description' => $this->t('Introductory message to be displayed at the top of the member check page.'),
      '#default_value' => $config->get('member_check.intro'),
    );

    $form['simple_conreg_member_check']['confirm_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Member check email subject'),
      '#default_value' => $config->get('member_check.confirm_subject'),
    );

    $form['simple_conreg_member_check']['confirm_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Member check confiramtion email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
      '#default_value' => $config->get('member_check.confirm_body'),
      '#format' => $config->get('member_check.confirm_format'),
    );  

    $form['simple_conreg_member_check']['unknown_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Member check confiramtion email body'),
      '#description' => $this->t('Text for the unknown body, to be sent if no member found for email address.'),
      '#default_value' => $config->get('member_check.unknown_body'),
      '#format' => $config->get('member_check.unknown_format'),
    );  

    return parent::buildForm($form, $form_state);
  }

  // Callback function for "fieldset" drop down.
  public function updateFieldsetCallback(array $form, FormStateInterface $form_state) {
    $fieldset = $form_state->getValue(['simple_conreg_fieldsets', 'fieldset']);
    if (empty($fieldset))
      $fieldset = 0;
    $fieldsetContainer = "fieldset_container_$fieldset";
    return $form['simple_conreg_fieldsets'][$fieldsetContainer];
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $vals = $form_state->getValues();
    $event = [
      'eid' => $eid,
      'event_name' => trim($vals['simple_conreg_event']['event_name']),
      'is_open' => $vals['simple_conreg_event']['open'],
    ];
    SimpleConregEventStorage::update($event);

    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    $config->set('payments.system', $vals['simple_conreg_payments']['system']);
    $config->set('payments.mode', $vals['simple_conreg_payments']['mode']);
    $config->set('payments.private_key', trim($vals['simple_conreg_payments']['private_key']));
    $config->set('payments.public_key', trim($vals['simple_conreg_payments']['public_key']));
    $config->set('payments.currency', trim($vals['simple_conreg_payments']['currency']));
    $config->set('payments.symbol', trim($vals['simple_conreg_payments']['symbol']));
    $config->set('payments.name', trim($vals['simple_conreg_payments']['show_name']));
    $config->set('payments.postcode', trim($vals['simple_conreg_payments']['show_postcode']));
    $config->set('member_types', $vals['simple_conreg_members']['types']);
    $config->set('member_upgrades', $vals['simple_conreg_members']['upgrades']);
    $config->set('badge_types', $vals['simple_conreg_members']['badge_types']);
    $config->set('days', $vals['simple_conreg_members']['days']);
    $config->set('member_no_digits', $vals['simple_conreg_members']['digits']);
    $config->set('registration_intro', $vals['simple_conreg_intros']['registration_intro']);
    $config->set('payment_intro', $vals['simple_conreg_intros']['payment_intro']);
    $config->set('communications_method.options', $vals['simple_conreg_communication']['options']);
    $config->set('simple_conreg_options.option_groups', $vals['simple_conreg_options']['option_groups']);
    $config->set('simple_conreg_options.options', $vals['simple_conreg_options']['options']);
    $config->set('add_ons.global', $vals['simple_conreg_addons']['global']);
    $config->set('add_ons.label', $vals['simple_conreg_addons']['label']);
    $config->set('add_ons.description', $vals['simple_conreg_addons']['description']);
    $config->set('add_ons.options', $vals['simple_conreg_addons']['options']);
    $config->set('add_on_info.label', $vals['simple_conreg_addon_info']['label']);
    $config->set('add_on_info.description', $vals['simple_conreg_addon_info']['description']);
    $config->set('add_on_free.label', $vals['simple_conreg_addon_free']['label']);
    $config->set('add_on_free.description', $vals['simple_conreg_addon_free']['description']);
    $config->set('display.page_size', $vals['simple_conreg_display']['page_size']);
    $config->set('reference.default_country', $vals['simple_conreg_reference']['default_country']);
    $config->set('reference.countries', $vals['simple_conreg_reference']['countries']);
    $config->set('thanks.thank_you_message', $vals['simple_conreg_thanks']['thank_you_message']['value']);
    $config->set('thanks.thank_you_format', $vals['simple_conreg_thanks']['thank_you_message']['format']);
    $config->set('discount.enable', $vals['simple_conreg_discount']['enable']);
    $config->set('discount.free_every', $vals['simple_conreg_discount']['free_every']);
    $config->set('checkin.display', $vals['simple_conreg_checkin']['display']);
    $config->set('checkin.communication_method', $vals['simple_conreg_checkin']['communication_method']);
    $config->set('confirmation.format_html', $vals['simple_conreg_confirmation']['format_html']);
    $config->set('confirmation.copy_us', $vals['simple_conreg_confirmation']['copy_us']);
    $config->set('confirmation.from_name', $vals['simple_conreg_confirmation']['from_name']);
    $config->set('confirmation.from_email', $vals['simple_conreg_confirmation']['from_email']);
    $config->set('confirmation.copy_email_to', $vals['simple_conreg_confirmation']['copy_email_to']);
    $config->set('confirmation.template_subject', $vals['simple_conreg_confirmation']['template_subject']);
    $config->set('confirmation.template_body', $vals['simple_conreg_confirmation']['template_body']['value']);
    $config->set('confirmation.template_format', $vals['simple_conreg_confirmation']['template_body']['format']);
    $config->set('confirmation.notification_subject', $vals['simple_conreg_confirmation']['notification_subject']);
    $config->set('member_check.intro', $vals['simple_conreg_member_check']['member_check_intro']);
    $config->set('member_check.confirm_subject', $vals['simple_conreg_member_check']['confirm_subject']);
    $config->set('member_check.confirm_body', $vals['simple_conreg_member_check']['confirm_body']['value']);
    $config->set('member_check.confirm_format', $vals['simple_conreg_member_check']['confirm_body']['format']);
    $config->set('member_check.unknown_body', $vals['simple_conreg_member_check']['unknown_body']['value']);
    $config->set('member_check.unknown_format', $vals['simple_conreg_member_check']['unknown_body']['format']);
    $config->save();

    $fieldset = $vals['simple_conreg_fieldsets']['fieldset'];
    $fieldsetContainer = "fieldset_container_$fieldset";
    SimpleConregConfig::saveFieldsetConfig($eid, $fieldset, $vals['simple_conreg_fieldsets'][$fieldsetContainer]);

    parent::submitForm($form, $form_state);
  }
}
