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

    // Get event configuration from config.
    $config = $this->config('simple_conreg.settings.'.$eid);
    if (empty($config->get('payments.system'))) {
      $config = $this->config('simple_conreg.settings');
    }

    $form['simple_conreg_event'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Event Details'),
      '#tree' => TRUE,
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
      '#type' => 'fieldset',
      '#title' => $this->t('Payment System'),
      '#tree' => TRUE,
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

    // Member Information Section.

    $form['simple_conreg_members'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Member Information'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_members']['types'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Member types'),
      '#description' => $this->t('Put each membership type on a line with type code, description, price and default badge type separated by | character (e.d. J|Junior Attending|$50|A).'),
      '#default_value' => $config->get('member_types'),
    );

    $form['simple_conreg_members']['badge_types'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Badge types'),
      '#description' => $this->t('Put each badge type on a line with type code and description separated by | character (e.g. G|Guest).'),
      '#default_value' => $config->get('badge_types'),
    );

    $form['simple_conreg_members']['digits'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Digits in Member Numbers'),
      '#description' => $this->t('The number of digits to show in member numbers. Member numbers will be padded to this number with zeros, e.g. 0001.'),
      '#default_value' => $config->get('member_no_digits'),
    );  

    /* Intro text. */

    $form['simple_conreg_intros'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Introduction messages'),
      '#tree' => TRUE,
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

    /* Field labels. */

    $form['simple_conreg_fields'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Field Labels'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_fields']['first_name_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('First name label (required)'),
      '#default_value' => $config->get('fields.first_name_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['last_name_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Last name label (required)'),
      '#default_value' => $config->get('fields.last_name_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['email_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email address label (required)'),
      '#default_value' => $config->get('fields.email_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['membership_type_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Type of membership label (required)'),
      '#default_value' => $config->get('fields.membership_type_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['badge_name_option_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name option label (required)'),
      '#default_value' => $config->get('fields.badge_name_option_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['badge_name_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name label (required)'),
      '#default_value' => $config->get('fields.badge_name_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['badge_name_description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name description'),
      '#default_value' => $config->get('fields.badge_name_description'),
      '#maxlength' => 255, 
    );

    $form['simple_conreg_fields']['display_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Display name on membership list label (required)'),
      '#default_value' => $config->get('fields.display_label'),
      '#required' => TRUE,
    );

    $form['simple_conreg_fields']['communication_method_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communication method label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.communication_method_label'),
    );

    $form['simple_conreg_fields']['same_address_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Same address as member 1 label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.same_address_label'),
    );

    $form['simple_conreg_fields']['street_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Street address label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.street_label'),
    );

    $form['simple_conreg_fields']['street2_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Street address line 2 label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.street2_label'),
    );

    $form['simple_conreg_fields']['city_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Town/city label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.city_label'),
    );

    $form['simple_conreg_fields']['county_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('County/state label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.county_label'),
    );

    $form['simple_conreg_fields']['postcode_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Postal code label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.postcode_label'),
    );

    $form['simple_conreg_fields']['country_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Country label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.country_label'),
    );

    $form['simple_conreg_fields']['phone_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Phone number label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.phone_label'),
    );

    $form['simple_conreg_fields']['birth_date_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Date of birth label (leave empty to remove field)'),
      '#default_value' => $config->get('fields.birth_date_label'),
    );

    /* Mandatory fields. */

    $form['simple_conreg_mandatory'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Mandatory Field'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_mandatory']['first_name'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('First name mandatory'),
      '#default_value' => $config->get('fields.first_name_mandatory'),
    );

    $form['simple_conreg_mandatory']['last_name'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Last name mandatory'),
      '#default_value' => $config->get('fields.last_name_mandatory'),
    );


    /*
     * Fields for communications method options.
     */
    $form['simple_conreg_communication'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Communications Methods'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_communication']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each communications method on a line with sincle character code and description separated by | character (e.g. "E|Electronic").'),
      '#default_value' => $config->get('communications_method.options'),
    );  


    /*
     * Fields for add on choices and options.
     */
    $form['simple_conreg_addons'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Add-ons'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_addons']['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $config->get('add_ons.label'),
    );  

    $form['simple_conreg_addons']['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('add_ons.description'),
    );  

    $form['simple_conreg_addons']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each add-on on a line with description and price separated by | character.'),
      '#default_value' => $config->get('add_ons.options'),
    );  

    $form['simple_conreg_addon_info'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Add-on Information'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_addon_info']['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => t('If you would like to capture optional information about the add-on, please provide label and description for the information field.'),
      '#default_value' => $config->get('add_on_info.label'),
    );  

    $form['simple_conreg_addon_info']['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('add_on_info.description'),
    );  

    $form['simple_conreg_extras'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Extras'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_extras']['flag1'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Flag 1 label'),
      '#default_value' => $config->get('extras.flag1'),
    );  

    $form['simple_conreg_extras']['flag2'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Flag 2 label'),
      '#default_value' => $config->get('extras.flag2'),
    );  

    /*
     * Display settings - entries per page.
     */

    $form['simple_conreg_display'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Display settings'),
      '#tree' => TRUE,
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
      '#type' => 'fieldset',
      '#title' => $this->t('Reference'),
      '#tree' => TRUE,
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
      '#default_value' => $config->get('reference.default_country'),
    );  


    /*
     * Fields for Thank You page.
     */

    $form['simple_conreg_thanks'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Thank You Page'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_thanks']['thank_you_message'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Thank You Message'),
      '#description' => $this->t('Text to appear on the thank you page displayed after payment completed.'),
      '#default_value' => $config->get('thanks.thank_you_message'),
    );  


    /*
     * Fields for multiple member discounts.
     */

    $form['simple_conreg_discount'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Multiple Member Discounts'),
      '#tree' => TRUE,
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
     * Fields for confirmation emails.
     */

    $form['simple_conreg_confirmation'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Confirmation Email'),
      '#tree' => TRUE,
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

    $form['simple_conreg_confirmation']['reg_header'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Confirmation message header'),
      '#description' => $this->t('Text for the confirmation email header. Registration details will appear below the header. you may use the following tokens: [first_name], [last_name], [pay_url].'),
      '#default_value' => $config->get('confirmation.reg_header'),
    );  

    $form['simple_conreg_confirmation']['reg_footer'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Confirmation message footer'),
      '#description' => $this->t('Text for the confirmation email footer. Registration details will appear above the footer. you may use the following tokens: [first_name], [last_name], [pay_url].'),
      '#default_value' => $config->get('confirmation.reg_footer'),
    );

    $form['simple_conreg_confirmation']['pay_template'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Payment receipt template'),
      '#description' => $this->t('Text for the payment receipt template. you may use the following tokens: [first_name], [last_name], [amount].'),
      '#default_value' => $config->get('confirmation.pay_template'),
    );  

    return parent::buildForm($form, $form_state);
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
    $config->set('member_types', $vals['simple_conreg_members']['types']);
    $config->set('badge_types', $vals['simple_conreg_members']['badge_types']);
    $config->set('member_no_digits', $vals['simple_conreg_members']['digits']);
    $config->set('registration_intro', $vals['simple_conreg_intros']['registration_intro']);
    $config->set('payment_intro', $vals['simple_conreg_intros']['payment_intro']);
    $config->set('fields.first_name_label', $vals['simple_conreg_fields']['first_name_label']);
    $config->set('fields.last_name_label', $vals['simple_conreg_fields']['last_name_label']);
    $config->set('fields.email_label', $vals['simple_conreg_fields']['email_label']);
    $config->set('fields.membership_type_label', $vals['simple_conreg_fields']['membership_type_label']);
    $config->set('fields.badge_name_option_label', $vals['simple_conreg_fields']['badge_name_option_label']);
    $config->set('fields.badge_name_label', $vals['simple_conreg_fields']['badge_name_label']);
    $config->set('fields.badge_name_description', $vals['simple_conreg_fields']['badge_name_description']);
    $config->set('fields.display_label', $vals['simple_conreg_fields']['display_label']);
    $config->set('fields.communication_method_label', $vals['simple_conreg_fields']['communication_method_label']);
    $config->set('fields.same_address_label', $vals['simple_conreg_fields']['same_address_label']);
    $config->set('fields.street_label', $vals['simple_conreg_fields']['street_label']);
    $config->set('fields.street2_label', $vals['simple_conreg_fields']['street2_label']);
    $config->set('fields.city_label', $vals['simple_conreg_fields']['city_label']);
    $config->set('fields.county_label', $vals['simple_conreg_fields']['county_label']);
    $config->set('fields.postcode_label', $vals['simple_conreg_fields']['postcode_label']);
    $config->set('fields.country_label', $vals['simple_conreg_fields']['country_label']);
    $config->set('fields.phone_label', $vals['simple_conreg_fields']['phone_label']);
    $config->set('fields.birth_date_label', $vals['simple_conreg_fields']['birth_date_label']);
    $config->set('fields.first_name_mandatory', $vals['simple_conreg_mandatory']['first_name']);
    $config->set('fields.last_name_mandatory', $vals['simple_conreg_mandatory']['last_name']);
    $config->set('communications_method.options', $vals['simple_conreg_communication']['options']);
    $config->set('add_ons.label', $vals['simple_conreg_addons']['label']);
    $config->set('add_ons.description', $vals['simple_conreg_addons']['description']);
    $config->set('add_ons.options', $vals['simple_conreg_addons']['options']);
    $config->set('add_on_info.label', $vals['simple_conreg_addon_info']['label']);
    $config->set('add_on_info.description', $vals['simple_conreg_addon_info']['description']);
    $config->set('extras.flag1', $vals['simple_conreg_extras']['flag1']);
    $config->set('extras.flag2', $vals['simple_conreg_extras']['flag2']);
    $config->set('display.page_size', $vals['simple_conreg_display']['page_size']);
    $config->set('reference.default_country', $vals['simple_conreg_reference']['default_country']);
    $config->set('reference.countries', $vals['simple_conreg_reference']['countries']);
    $config->set('thanks.thank_you_message', $vals['simple_conreg_thanks']['thank_you_message']);
    $config->set('discount.enable', $vals['simple_conreg_discount']['enable']);
    $config->set('discount.free_every', $vals['simple_conreg_discount']['free_every']);
    $config->set('confirmation.copy_us', $vals['simple_conreg_confirmation']['copy_us']);
    $config->set('confirmation.from_name', $vals['simple_conreg_confirmation']['from_name']);
    $config->set('confirmation.from_email', $vals['simple_conreg_confirmation']['from_email']);
    $config->set('confirmation.copy_email_to', $vals['simple_conreg_confirmation']['copy_email_to']);
    $config->set('confirmation.reg_header', $vals['simple_conreg_confirmation']['reg_header']);
    $config->set('confirmation.reg_footer', $vals['simple_conreg_confirmation']['reg_footer']);
    $config->set('confirmation.pay_template', $vals['simple_conreg_confirmation']['pay_template']);
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
