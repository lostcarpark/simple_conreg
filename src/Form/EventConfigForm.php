<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\Form\EventConfigForm
 */
namespace Drupal\simple_conreg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregTokens;
use Drupal\simple_conreg\FieldOptions;

/**
 * Configure simple_conreg settings for this site.
 */
class EventConfigForm extends ConfigFormBase {
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

    // Get config for event.
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);

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

    $form['simple_conreg_payments']['types'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment Method Types'),
      '#description' => $this->t('Available payment types, separated by |. Default to "card".'),
      '#default_value' => $config->get('payments.types') ?: 'card',
    );  

    $form['simple_conreg_payments']['mode'] = array(
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => array('Test'=>'Test', 'Live'=>'Live'),
      '#default_value' => $config->get('payments.mode'),
    );  

    $form['simple_conreg_payments']['public_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment System Public (Publishable) Key'),
      '#default_value' => $config->get('payments.public_key'),
    );  

    $form['simple_conreg_payments']['private_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment System Private (Secret) Key'),
      '#default_value' => $config->get('payments.private_key'),
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

    $form['simple_conreg_members']['member_type_default'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default Member Type'),
      '#description' => $this->t('Default member type code. Leave blank to have no default member type selected.'),
      '#default_value' => $config->get('member_type_default'),
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

    $form['simple_conreg_members']['badge_name_options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Badge name options'),
      '#description' => $this->t('Put each badge name option on a line with option code and description separated by | character (e.g. F|First name only). Note that F, N, L, and O are the only options that make sense at the moment.'),
      '#default_value' => $config->get('badge_name_options'),
    );

    $form['simple_conreg_members']['badge_name_default'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Badge name default option'),
      '#description' => $this->t('Default badge option code. Leave blank to have no default option selected.'),
      '#default_value' => $config->get('badge_name_default'),
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

    $form['simple_conreg_communication']['default'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communications method default option'),
      '#description' => $this->t('Default communications method option. Leave blank for no default.'),
      '#default_value' => $config->get('communications_method.default'),
    );

    /*
     * Fields for display options.
     */
    $form['simple_conreg_display_options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Display options'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_display_options']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Display options'),
      '#description' => $this->t('Put each display option on a line with single character code, description, separated by | character (e.g. "F|Full name and badge name").'),
      '#default_value' => $config->get('display_options.options'),
    );  


    /*
     * Fields for options.
     */
    $form['simple_conreg_options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Membership Options'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_options']['option_groups'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Option Groups'),
      '#description' => $this->t('Put each option group on a line with group ID, field name to attach to, group title, local/global (0/1), and private/public (0/1 - groups with 0 will only be visible to admins), separated by | character (e.g. "1|extra_flag1|Please tick the areas you\'d like to volunteer|0|1").'),
      '#default_value' => $config->get('simple_conreg_options.option_groups'),
    );  

    $form['simple_conreg_options']['options'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Put each option on a line with option ID, group ID, option title, detail title, detail required (1/0), weight (bigger number goes to bottom), comma separated list of member classes to include it, and must be checked, separated by | character (e.g. "1|1|Help with pre-con tasks|Please provide details of areas you\'d like to help|0|1|0,1|0").'),
      '#default_value' => $config->get('simple_conreg_options.options'),
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
     * Fields for reference data.
     * Used to contain countries list, but now using standard Drupal list.
     */

    $form['simple_conreg_reference'] = array(
      '#type' => 'details',
      '#title' => $this->t('Reference'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );
  
    $form['simple_conreg_reference']['default_country'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default country code'),
      '#description' => $this->t('Enter 2 letter country code of country to default to. Leave blank to have no default.'),
      '#default_value' => $config->get('reference.default_country'),
    );  
   
    $form['simple_conreg_reference']['no_country_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('No country label'),
      '#description' => $this->t('If country not required, enter label for selecting no country.'),
      '#default_value' => $config->get('reference.no_country_label'),
    );  

    $form['simple_conreg_reference']['geoplugin'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use geoPlugin to lookup country from IP'),
      '#default_value' => $config->get('reference.geoplugin'),
    );  


    /*
     * Fields for submit buttons.
     */

    $form['simple_conreg_submit'] = array(
      '#type' => 'details',
      '#title' => $this->t('Submit Button'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_submit']['submit_payment'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label for Submit with payment'),
      '#description' => $this->t('Label to be displayed when price is greater than zero.'),
      '#default_value' => $config->get('submit.payment'),
    );  

    $form['simple_conreg_submit']['submit_free'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label for Submit when no charge'),
      '#description' => $this->t('Label to be displayed when price is equal to zero.'),
      '#default_value' => $config->get('submit.free'),
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

    $form['simple_conreg_thanks']['title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Title for Thank You page'),
      '#default_value' => $config->get('thanks.title'),
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

    $form['simple_conreg_member_check']['member_check_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Member check title'),
      '#default_value' => $config->get('member_check.title'),
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

    /* Member Self Service Edit Settings. */

    $form['simple_conreg_member_edit'] = array(
      '#type' => 'details',
      '#title' => $this->t('Member Self Service Edit'),
      '#tree' => TRUE,
      '#group' => 'admin',
    );

    $form['simple_conreg_member_edit']['member_edit_intro'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Member self service edit intro'),
      '#description' => $this->t('Text to appear at the top of the member edit page.'),
      '#default_value' => $config->get('member_edit.intro_text'),
      '#format' => $config->get('member_edit.intro_format'),
    );  

    $form['simple_conreg_member_edit']['email'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Email editable by member'),
      '#default_value' => $config->get('member_edit.email_editable'),
    );

    $form['simple_conreg_member_edit']['badge_name'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Badge name editable by member'),
      '#default_value' => $config->get('member_edit.badge_name_editable'),
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
    $config->set('payments.types', $vals['simple_conreg_payments']['types']);
    $config->set('payments.mode', $vals['simple_conreg_payments']['mode']);
    $config->set('payments.private_key', trim($vals['simple_conreg_payments']['private_key']));
    $config->set('payments.public_key', trim($vals['simple_conreg_payments']['public_key']));
    $config->set('payments.currency', trim($vals['simple_conreg_payments']['currency']));
    $config->set('payments.symbol', trim($vals['simple_conreg_payments']['symbol']));
    $config->set('payments.name', trim($vals['simple_conreg_payments']['show_name']));
    $config->set('payments.postcode', trim($vals['simple_conreg_payments']['show_postcode']));
    $config->set('member_type_default', $vals['simple_conreg_members']['member_type_default']);
    $config->set('member_upgrades', $vals['simple_conreg_members']['upgrades']);
    $config->set('badge_types', $vals['simple_conreg_members']['badge_types']);
    $config->set('badge_name_options', $vals['simple_conreg_members']['badge_name_options']);
    $config->set('badge_name_default', $vals['simple_conreg_members']['badge_name_default']);
    $config->set('days', $vals['simple_conreg_members']['days']);
    $config->set('member_no_digits', intval($vals['simple_conreg_members']['digits']));
    $config->set('registration_intro', $vals['simple_conreg_intros']['registration_intro']);
    $config->set('payment_intro', $vals['simple_conreg_intros']['payment_intro']);
    $config->set('communications_method.options', $vals['simple_conreg_communication']['options']);
    $config->set('communications_method.default', $vals['simple_conreg_communication']['default']);
    $config->set('display_options.options', $vals['simple_conreg_display_options']['options']);
    $config->set('simple_conreg_options.option_groups', $vals['simple_conreg_options']['option_groups']);
    $config->set('simple_conreg_options.options', $vals['simple_conreg_options']['options']);
    $config->set('display.page_size', intval($vals['simple_conreg_display']['page_size']));
    $config->set('reference.default_country', $vals['simple_conreg_reference']['default_country']);
    $config->set('reference.no_country_label', $vals['simple_conreg_reference']['no_country_label']);
    $config->set('reference.geoplugin', $vals['simple_conreg_reference']['geoplugin']);
    $config->set('submit.payment', $vals['simple_conreg_submit']['submit_payment']);
    $config->set('submit.free', $vals['simple_conreg_submit']['submit_free']);
    $config->set('thanks.title', $vals['simple_conreg_thanks']['title']);
    $config->set('thanks.thank_you_message', $vals['simple_conreg_thanks']['thank_you_message']['value']);
    $config->set('thanks.thank_you_format', $vals['simple_conreg_thanks']['thank_you_message']['format']);
    $config->set('discount.enable', $vals['simple_conreg_discount']['enable']);
    $config->set('discount.free_every', intval($vals['simple_conreg_discount']['free_every']) ?: null);
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
    $config->set('member_check.title', $vals['simple_conreg_member_check']['member_check_title']);
    $config->set('member_check.intro', $vals['simple_conreg_member_check']['member_check_intro']);
    $config->set('member_check.confirm_subject', $vals['simple_conreg_member_check']['confirm_subject']);
    $config->set('member_check.confirm_body', $vals['simple_conreg_member_check']['confirm_body']['value']);
    $config->set('member_check.confirm_format', $vals['simple_conreg_member_check']['confirm_body']['format']);
    $config->set('member_check.unknown_body', $vals['simple_conreg_member_check']['unknown_body']['value']);
    $config->set('member_check.unknown_format', $vals['simple_conreg_member_check']['unknown_body']['format']);
    $config->set('member_edit.intro_text', $vals['simple_conreg_member_edit']['member_edit_intro']['value']);
    $config->set('member_edit.intro_format', $vals['simple_conreg_member_edit']['member_edit_intro']['format']);
    $config->set('member_edit.email_editable', $vals['simple_conreg_member_edit']['email']);
    $config->set('member_edit.badge_name_editable', $vals['simple_conreg_member_edit']['badge_name']);
    $config->save();

    $langcodes = \Drupal::languageManager()->getLanguages();
    foreach ($langcodes as $language) {
      $langCode = $language->getId();
      \Drupal::cache()->delete('simple_conreg:countryList_' . $eid . '_' . $langCode);
      \Drupal::cache()->delete('simple_conreg:fieldOptions_' . $eid . '_' . $langCode);
    }

    parent::submitForm($form, $form_state);
  }
}
