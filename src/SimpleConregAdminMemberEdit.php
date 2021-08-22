<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMemberEdit
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
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\user\Entity\User;
use Drupal\node\NodeInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;

// If PHP<7.3, add array_key_first function.
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }
}

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberEdit extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormID()
  {
    return 'simple_conreg_admin_member_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $mid = NULL)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $memberPrices = array();

    // Get event configuration from config.
    $config = $this->config('simple_conreg.settings.'.$eid);

    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypeOptions = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    list($addOnOptions, $addOnPrices) = SimpleConregOptions::memberAddons($eid, $config);
    $symbol = $config->get('payments.symbol');
    $countryOptions = SimpleConregOptions::memberCountries($eid, $config);
    $defaultCountry = $config->get('reference.default_country');

    if (isset($mid)) {
      // Load the member record.
      $member = Member::loadMember($mid);
      // Check member exists.
      if (empty($member->mid)) {
        // Event not in database. Display error.
        $form['simple_conreg_event'] = array(
          '#markup' => $this->t('Member not found. Please confirm member valid.'),
          '#prefix' => '<h3>',
          '#suffix' => '</h3>',
        );
        return $form;
      }
    } else {
      $member = new Member();
      $member->join_date = \Drupal::time()->getCurrentTime();
      $member->member_type = array_key_first($types->publicOptions);
    }

    // Get config for selected member type.
    $fieldsetConfig = $types->types[$member->member_type]->config;

    $form = [
      '#tree' => TRUE,
      '#prefix' => '<div id="regform">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => ['simple_conreg/conreg_form', 'simple_conreg/conreg_fieldoptions']
      ],
    ];

    $form['member'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Member details'),
    ];

    $form['member']['is_approved'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Approved'),
      '#default_value' => (isset($member->is_approved) ? $member->is_approved : 0),
    ];

    $form['member']['member_no'] = array(
      '#type' => 'number',
      '#title' => $this->t('Member number'),
      '#description' => $this->t('Check approved and leave blank to auto assign.'),
      '#default_value' => (!empty($member->member_no) ? $member->member_no : ''),
    );

    $firstname_max_length = $fieldsetConfig->get('fields.first_name_max_length');
    $form['member']['first_name'] = array(
      '#type' => 'textfield',
      '#title' => $fieldsetConfig->get('fields.first_name_label'),
      '#size' => 29,
      '#default_value' => (isset($member->first_name) ? $member->first_name : ''),
      '#maxlength' => (empty($firstname_max_length) ? 128 : $firstname_max_length),
      '#required' => ($config->get('fields.first_name_mandatory') ? TRUE : FALSE),
      '#attributes' => array(
        'id' => "edit-member-first-name",
        'class' => array('edit-members-first-name')),
    );

    $lastname_max_length = $fieldsetConfig->get('fields.last_name_max_length');
    $form['member']['last_name'] = array(
      '#type' => 'textfield',
      '#title' => $fieldsetConfig->get('fields.last_name_label'),
      '#size' => 29,
      '#default_value' => (isset($member->last_name) ? $member->last_name : ''),
      '#maxlength' => (empty($lastname_max_length) ? 128 : $lastname_max_length),
      '#required' => ($config->get('fields.last_name_mandatory') ? TRUE : FALSE),
      '#attributes' => array(
        'id' => "edit-member-last-name",
        'class' => array('edit-members-last-name')),
    );

    $form['member']['email'] = array(
      '#type' => 'email',
      '#title' => $fieldsetConfig->get('fields.email_label'),
      '#default_value' => (isset($member->email) ? $member->email : ''),
    );

    $form['member']['type'] = array(
      '#type' => 'select',
      '#title' => $fieldsetConfig->get('fields.membership_type_label'),
      '#options' => $types->privateOptions,
      '#default_value' => $member->member_type,
      '#required' => TRUE,
    );

    $dayVals = [];
    if (isset($member->days)) {
      foreach(explode('|', $member->days) as $day)
        $dayVals[] = $day;
    }
    $form['member']['days'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Days'),
      '#options' => $days,
      '#default_value' => $dayVals,
    );


    $badgename_max_length = $fieldsetConfig->get('fields.badge_name_max_length');
    $form['member']['badge_name'] = [
      '#type' => 'textfield',
      '#title' => $fieldsetConfig->get('fields.badge_name_label'),
      '#default_value' => (isset($member->badge_name) ? $member->badge_name : ''),
      '#required' => TRUE,
      '#maxlength' => (empty($badgename_max_length) ? 128 : $badgename_max_length),
      '#attributes' => [
        'id' => "edit-member-badge-name",
        'class' => ['edit-members-badge-name'],
      ],
    ];

    $form['member']['badge_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Badge type'),
      '#options' => $badgeTypeOptions,
      '#default_value' => (isset($member->badge_type) ? $member->badge_type : ''),
      '#required' => TRUE,
    );

    if (!empty($fieldsetConfig->get('fields.display_label'))) {
      $form['member']['display'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.display_label'),
        '#description' => $this->t('Select how you would like to appear on the membership list.'),
        '#options' => SimpleConregOptions::display(),
        '#default_value' => (isset($member->display) ? $member->display : 'F'),
        '#required' => TRUE,
      );
    }

    if (!empty($fieldsetConfig->get('fields.communication_method_label'))) {
      $form['member']['communication_method'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.communication_method_label'),
        '#description' => $fieldsetConfig->get('fields.communication_method_description'),
        '#options' => SimpleConregOptions::communicationMethod($eid, $config, FALSE),
        '#default_value' => (isset($member->communication_method) ? $member->communication_method : 'E'),
        '#required' => TRUE,
      );
    }

    if (!empty($fieldsetConfig->get('fields.street_label'))) {
      $form['member']['street'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.street_label'),
        '#default_value' => (isset($member->street) ? $member->street : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.street2_label'))) {
      $form['member']['street2'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.street2_label'),
        '#default_value' => (isset($member->street2) ? $member->street2 : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.city_label'))) {
      $form['member']['city'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.city_label'),
        '#default_value' => (isset($member->city) ? $member->city : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.county_label'))) {
      $form['member']['county'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.county_label'),
        '#default_value' => (isset($member->county) ? $member->county : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.postcode_label'))) {
      $form['member']['postcode'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.postcode_label'),
        '#default_value' => (isset($member->postcode) ? $member->postcode : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.country_label'))) {
      $form['member']['country'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.country_label'),
        '#options' => $countryOptions,
        '#default_value' => (isset($member->country) ? $member->country : $defaultCountry),
        '#required' => TRUE,
      );
    }

    if (!empty($fieldsetConfig->get('fields.phone_label'))) {
      $form['member']['phone'] = array(
        '#type' => 'tel',
        '#title' => $fieldsetConfig->get('fields.phone_label'),
        '#default_value' => (isset($member->phone) ? $member->phone : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.birth_date_label'))) {
      $form['member']['birth_date'] = array(
        '#type' => 'date',
        '#title' => $fieldsetConfig->get('fields.birth_date_label'),
        '#default_value' => (isset($member->birth_date) ? $member->birth_date : ''),
      );
    }

    if (!empty($fieldsetConfig->get('fields.age_label'))) {
      $form['member']['age'] = array(
        '#type' => 'number',
        '#title' => $fieldsetConfig->get('fields.age_label'),
        '#default_value' => (isset($member->age) ? $member->age : ''),
      );
    }

    if (!empty($fieldsetConfig->get('extras.flag1'))) {
      $form['member']['extra_flag1'] = array(
        '#type' => 'checkbox',
        '#title' => $fieldsetConfig->get('extras.flag1'),
        '#default_value' => (isset($member->extra_flag1) ? $member->extra_flag1 : ''),
      );
    }

    if (!empty($fieldsetConfig->get('extras.flag2'))) {
      $form['member']['extra_flag2'] = array(
        '#type' => 'checkbox',
        '#title' => $fieldsetConfig->get('extras.flag2'),
        '#default_value' => (isset($member->extra_flag2) ? $member->extra_flag2 : ''),
      );
    }

    $fieldset = isset($types->types[$member->member_type]->fieldset) ? $types->types[$member->member_type]->fieldset : 0;

    // Get field options from form state. If not set, get from config.
    $fieldOptions = $form_state->get('fieldOptions');
    if (is_null($fieldOptions)) {
      $fieldOptions = FieldOptions::getFieldOptions($eid);
    }
    // Add the field options to the form. Display both global and member fields. Display public and private fields. 
    $fieldOptions->addOptionFields($fieldset, $form['member'], $member, NULL, TRUE, FALSE);

    $form['member']['is_paid'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Paid'),
      '#default_value' => (isset($member->is_paid) ? $member->is_paid : 0),
    );

    $form['member']['payment_method'] = array(
      '#type' => 'select',
      '#title' => $this->t('Payment method'),
      '#options' => SimpleConregOptions::paymentMethod(),
      '#default_value' => (isset($member->payment_method) ? $member->payment_method : ''),
      '#required' => TRUE,
    );

    $form['member']['member_price'] = array(
      '#type' => 'number',
      '#title' => $this->t('Price'),
      '#default_value' => (isset($member->member_price) ? $member->member_price : ''),
      '#step' => '0.01',
    );

    $form['member']['payment_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment reference'),
      '#default_value' => (isset($member->payment_id) ? $member->payment_id : ''),
    );

    $form['member']['comment'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Comment'),
      '#default_value' => (isset($member->comment) ? $member->comment : ''),
    );

    $form['member']['is_checked_in'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Checked In'),
      '#description' => $this->t('Only tick if adding member at convention and they are present.'),
      '#default_value' => (isset($member->is_checked_in) ? $member->is_checked_in : 0),
    );

    $form['member']['join_date'] = array(
      '#type' => 'datetime',
      '#title' => $this->t('Date joined'),
      '#description' => $this->t('Leave blank to set to current date.'),
      '#default_value' => DrupalDateTime::createFromTimestamp($member->join_date),
    );

    $form['payment']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save member'),
    );

    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#submit' => [[$this, 'submitCancel']],
    );

    $form_state->set('member', $member);
    $form_state->set('mid', $mid);
    $form_state->set('fieldset', $fieldset);
    return $form;
  }

  /*
   * Submit handler for cancel button.
   */

  public function submitCancel(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    // Get session state to return to correct page.
    $tempstore = \Drupal::service('tempstore.private')->get('simple_conreg');
    $display = $tempstore->get('display');
    $page = $tempstore->get('page');
    // Redirect to member list.
    $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
  }

  /*
   * Submit handler for member edit form.
   */

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $mid = $form_state->get('mid');
    $fieldset = $form_state->get('fieldset');
    $member = $form_state->get('member');
    $options = $member->options;
    unset($member->options); // Remove options form member object so it doesn't save it.

    $config = $this->config('simple_conreg.settings.'.$eid);
    $form_values = $form_state->getValues();
    $memberDays = [];
    foreach($form_values['member']['days'] as $key=>$val) {
      if ($val)
        $memberDays[] = $key;
    }

    // If no date, use NULL.
    if (isset($form_values['member']['birth_date']) && preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $form_values['member']['birth_date'])) {
      $birth_date = $form_values['member']['birth_date'];
    } else {
      $birth_date = NULL;
    }

    // Get the maximum member number from the database.
    $max_member = SimpleConregStorage::loadMaxMemberNo($eid);
    // Check if approved has been checked.
    if ($form_values['member']["is_approved"]) {
      if (empty($form_values['member']["member_no"])) {
        // No member no specified, so assign next one.
        $max_member++;
        $member_no = $max_member;
      } else {
        // Member no specified.
        $member_no = $form_values['member']["member_no"];
      }
    } else {
      // No member number for unapproved members.
      $member_no = 0;
    }

    // Get field options from form state. If not set, get from config.
    $fieldOptions = $form_state->get('fieldOptions');
    if (is_null($fieldOptions)) {
      $fieldOptions = FieldOptions::getFieldOptions($eid);
    }
    // Process option fields to remove any modifications from form values.
    $fieldOptions->procesOptionFields($fieldset, $form_values['member'], $mid, $options);

    // Save the submitted entry.
    $member->is_approved = $form_values['member']['is_approved'];
    $member->member_no = $member_no;
    $member->member_type = $form_values['member']['type'];
    $member->days = implode('|', $memberDays);
    if (isset($form_values['member']['first_name'])) $member->first_name = trim($form_values['member']['first_name']);
    if (isset($form_values['member']['last_name'])) $member->last_name = trim($form_values['member']['last_name']);
    if (isset($form_values['member']['email'])) $member->email = trim($form_values['member']['email']);
    if (isset($form_values['member']['badge_name'])) $member->badge_name = trim($form_values['member']['badge_name']);
    if (isset($form_values['member']['badge_type'])) $member->badge_type = trim($form_values['member']['badge_type']);
    if (isset($form_values['member']['display'])) $member->display = $form_values['member']['display'];
    if (isset($form_values['member']['communication_method'])) $member->communication_method = $form_values['member']['communication_method'];
    if (isset($form_values['member']['street'])) $member->street = trim($form_values['member']['street']);
    if (isset($form_values['member']['street2'])) $member->street2 = trim($form_values['member']['street2']);
    if (isset($form_values['member']['city'])) $member->city = trim($form_values['member']['city']);
    if (isset($form_values['member']['county'])) $member->county = trim($form_values['member']['county']);
    if (isset($form_values['member']['postcode'])) $member->postcode = trim($form_values['member']['postcode']);
    if (isset($form_values['member']['country'])) $member->country = trim($form_values['member']['country']);
    if (isset($form_values['member']['phone'])) $member->phone = trim($form_values['member']['phone']);
    if (isset($form_values['member']['birth_date'])) $member->birth_date = $form_values['member']['birth_date'];
    if (isset($form_values['member']['age'])) $member->age = $form_values['member']['age'];
    if (isset($form_values['member']['extra_flag1'])) $member->extra_flag1 = $form_values['member']['extra_flag1'];
    if (isset($form_values['member']['extra_flag2'])) $member->extra_flag2 = $form_values['member']['extra_flag2'];
    $member->is_paid = $form_values['member']['is_paid'];
    if (isset($form_values['member']['comment'])) $member->comment = $form_values['member']['comment'];
    if (isset($form_values['member']['payment_method'])) $member->payment_method = $form_values['member']['payment_method'];
    if (isset($form_values['member']['member_price'])) $member->member_price = $form_values['member']['member_price'];
    if (isset($form_values['member']['payment_id'])) $member->payment_id = $form_values['member']['payment_id'];
    $member->is_checked_in = $form_values['member']['is_checked_in'];

    $return = $member->saveMember();

    if (!empty($form_values["member"]["is_checked_in"])) {
      $user = User::load(\Drupal::currentUser()->id());
      $entry['check_in_date'] = time();
      $entry['check_in_by'] = $user->get('uid')->value;
    }

    $join_date = $form_values['member']['join_date']->getTimestamp();
    // If Join Date specified, use it. If not, use current date/time.
    if ($join_date == 0)
      $entry['join_date'] = time();
    else
      // Date specified, and successfully converted into timestamp.
      $entry['join_date'] = $join_date;

    if (isset($mid)) {
      // Update the member record.
      $entry['mid'] = $mid;
      $entry['update_date'] = time();
      $return = SimpleConregStorage::update($entry);
    } else {
      // Specify the event.
      $entry['eid'] = $eid;
      $entry['update_date'] = time();
      // Insert to database table.
      $mid = SimpleConregStorage::insert($entry);
      
      // After saving new member, get key from insert statement to use for lead member ID.
      $lead_mid = $mid;
      $lead_key = $rand_key;
      // Update member with own member ID as lead member ID.
      $update = array('mid' => $lead_mid, 'lead_mid' => $lead_mid);
      $return = SimpleConregStorage::update($update);
    }

    if ($return) {

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
            if (isset($entry['email']) && $entry['email'] != '') {
              if (isset($entry['communication_method']) &&
                  isset($communications_methods[$entry['communication_method']]) &&
                  $communications_methods[$entry['communication_method']]) {
                // Subscribe member if criteria met.
                $subscription_manager->subscribe($entry['email'], $newsletter_id, FALSE, 'website');
                \Drupal::messenger()->addMessage($this->t('Subscribed %email to %newsletter.', ['%email' => $entry['email'], '%newsletter' => $newsletter_id]));
              } else {
                // Unsubscribe member if criteria not met (their communications method may have changed).
                $subscription_manager->unsubscribe($entry['email'], $newsletter_id, FALSE, 'website');
                \Drupal::messenger()->addMessage($this->t('Unsubscribed %email from %newsletter.', ['%email' => $entry['email'], '%newsletter' => $newsletter_id]));
              }
            }
          }
        }
      }

      // Get session state to return to correct page.
      $tempstore = \Drupal::service('tempstore.private')->get('simple_conreg');
      $display = $tempstore->get('display');
      $page = $tempstore->get('page');

      // Redirect to member list.
      $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
    }
  }

}
