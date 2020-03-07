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
use Drupal\node\NodeInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $mid = NULL) {
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
      $member = SimpleConregStorage::load(['eid' => $eid, 'mid' => $mid]);
      // Check member exists.
      if (count($member) < 3) {
        // Event not in database. Display error.
        $form['simple_conreg_event'] = array(
          '#markup' => $this->t('Member not found. Please confirm member valid.'),
          '#prefix' => '<h3>',
          '#suffix' => '</h3>',
        );
        return parent::buildForm($form, $form_state);
      }
    } else {
      $member = ['join_date' => \Drupal::time()->getCurrentTime()];
    }

    $form = array(
      '#tree' => TRUE,
      '#prefix' => '<div id="regform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_form')
      ),
    );

    $form['member'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Member details'),
    );

    $form['member']['is_approved'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Approved'),
      '#default_value' => $member['is_approved'],
    );

    $form['member']['member_no'] = array(
      '#type' => 'number',
      '#title' => $this->t('Member number'),
      '#description' => $this->t('Check approved and leave blank to auto assign.'),
      '#default_value' => ($member['member_no'] ? $member['member_no'] : ''),
    );

    $form['member']['first_name'] = array(
      '#type' => 'textfield',
      '#title' => $config->get('fields.first_name_label'),
      '#size' => 29,
      '#default_value' => $member['first_name'],
      '#attributes' => array(
        'id' => "edit-member-first-name",
        'class' => array('edit-members-first-name')),
      '#required' => ($config->get('fields.first_name_mandatory') ? TRUE : FALSE),
    );

    $form['member']['last_name'] = array(
      '#type' => 'textfield',
      '#title' => $config->get('fields.last_name_label'),
      '#size' => 29,
      '#default_value' => $member['last_name'],
      '#attributes' => array(
        'id' => "edit-member-last-name",
        'class' => array('edit-members-last-name')),
      '#required' => ($config->get('fields.last_name_mandatory') ? TRUE : FALSE),
    );

    $form['member']['email'] = array(
      '#type' => 'email',
      '#title' => $config->get('fields.email_label'),
      '#default_value' => $member['email'],
    );

    $form['member']['type'] = array(
      '#type' => 'select',
      '#title' => $config->get('fields.membership_type_label'),
      '#options' => $types->privateOptions,
      '#default_value' => $member['member_type'],
      '#required' => TRUE,
    );

    $dayVals = [];
    if (isset($member['days'])) {
      foreach(explode('|', $member['days']) as $day)
        $dayVals[] = $day;
    }
    $form['member']['days'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Days'),
      '#options' => $days,
      '#default_value' => $dayVals,
    );

    if (!empty($config->get('add_ons.label'))) {
      $form['member']['add_on'] = array(
        '#type' => 'select',
        '#title' => $config->get('add_ons.label'),
        '#description' => $config->get('add_ons.description'),
        '#options' => $addOnOptions,
        '#default_value' => $member['add_on'],
        '#required' => TRUE,
      );

      if (!empty($config->get('add_on_info.label'))) {
        $form['member']['add_on_extra']['info'] = array(
          '#type' => 'textfield',
          '#title' => $config->get('add_on_info.label'),
          '#description' => $config->get('add_on_info.description'),
          '#default_value' => $member['add_on_info'],
        );
      }
    }

    $form['member']['badge_name'] = array(
      '#type' => 'textfield',
      '#title' => $config->get('fields.badge_name_label'),
      '#default_value' => $member['badge_name'],
      '#required' => TRUE,
      '#attributes' => array(
        'id' => "edit-members-member$cnt-badge-name",
        'class' => array('edit-members-badge-name')),
    );

    $form['member']['badge_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Badge type'),
      '#options' => $badgeTypeOptions,
      '#default_value' => $member['badge_type'],
      '#required' => TRUE,
    );

    $form['member']['display'] = array(
      '#type' => 'select',
      '#title' => $config->get('fields.display_label'),
      '#description' => $this->t('Select how you would like to appear on the membership list.'),
      '#options' => SimpleConregOptions::display(),
      '#default_value' => (isset($member['display']) ? $member['display'] : 'F'),
      '#required' => TRUE,
    );

    if (!empty($config->get('fields.communication_method_label'))) {
      $form['member']['communication_method'] = array(
        '#type' => 'select',
        '#title' => $config->get('fields.communication_method_label'),
        '#options' => SimpleConregOptions::communicationMethod($eid, $config, FALSE),
        '#default_value' => (isset($member['communication_method']) ? $member['communication_method'] : 'E'),
        '#required' => TRUE,
      );
    }

    if (!empty($config->get('fields.street_label'))) {
      $form['member']['street'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.street_label'),
        '#default_value' => $member['street'],
      );
    }

    if (!empty($config->get('fields.street2_label'))) {
      $form['member']['street2'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.street2_label'),
        '#default_value' => $member['street2'],
      );
    }

    if (!empty($config->get('fields.city_label'))) {
      $form['member']['city'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.city_label'),
        '#default_value' => $member['city'],
      );
    }

    if (!empty($config->get('fields.county_label'))) {
      $form['member']['county'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.county_label'),
        '#default_value' => $member['county'],
      );
    }

    if (!empty($config->get('fields.postcode_label'))) {
      $form['member']['postcode'] = array(
        '#type' => 'textfield',
        '#title' => $config->get('fields.postcode_label'),
        '#default_value' => $member['postcode'],
      );
    }

    if (!empty($config->get('fields.country_label'))) {
      $form['member']['country'] = array(
        '#type' => 'select',
        '#title' => $config->get('fields.country_label'),
        '#options' => $countryOptions,
        '#default_value' => (isset($member['country']) ? $member['country'] : $defaultCountry),
        '#required' => TRUE,
      );
    }

    if (!empty($config->get('fields.phone_label'))) {
      $form['member']['phone'] = array(
        '#type' => 'tel',
        '#title' => $config->get('fields.phone_label'),
        '#default_value' => $member['phone'],
      );
    }

    if (!empty($config->get('fields.birth_date_label'))) {
      $form['member']['birth_date'] = array(
        '#type' => 'date',
        '#title' => $config->get('fields.birth_date_label'),
        '#default_value' => $member['birth_date'],
      );
    }

    if (!empty($config->get('fields.age_label'))) {
      $form['member']['age'] = array(
        '#type' => 'number',
        '#title' => $config->get('fields.birth_date_label'),
        '#default_value' => $member['birth_date'],
      );
    }

    if (!empty($config->get('extras.flag1'))) {
      $form['member']['extra_flag1'] = array(
        '#type' => 'checkbox',
        '#title' => $config->get('extras.flag1'),
        '#default_value' => $member['extra_flag1'],
      );
    }

    if (!empty($config->get('extras.flag2'))) {
      $form['member']['extra_flag2'] = array(
        '#type' => 'checkbox',
        '#title' => $config->get('extras.flag2'),
        '#default_value' => $member['extra_flag2'],
      );
    }

    $form['member']['is_paid'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Paid'),
      '#default_value' => $member['is_paid'],
    );

    $form['member']['payment_method'] = array(
      '#type' => 'select',
      '#title' => $this->t('Payment method'),
      '#options' => SimpleConregOptions::paymentMethod(),
      '#default_value' => $member['payment_method'],
      '#required' => TRUE,
    );

    $form['member']['member_price'] = array(
      '#type' => 'number',
      '#title' => $this->t('Price'),
      '#default_value' => $member['member_price'],
      '#step' => '0.01',
    );

    $form['member']['payment_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment reference'),
      '#default_value' => $member['payment_id'],
    );

    $form['member']['comment'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Comment'),
      '#default_value' => $member['comment'],
    );

    $form['member']['is_checked_in'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Checked In'),
      '#description' => $this->t('Only tick if adding member at convention and they are present.'),
      '#default_value' => $member['is_checked_in'],
    );

    $form['member']['join_date'] = array(
      '#type' => 'datetime',
      '#title' => $this->t('Date joined'),
      '#description' => $this->t('Leave blank to set to current date.'),
      '#default_value' => DrupalDateTime::createFromTimestamp($member['join_date']),
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

    $form_state->set('mid', $mid);
    return $form;
  }
  
  /*
   * Submit handler for cancel button.
   */

  public function submitCancel(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    // Get session state to return to correct page.
    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
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

    // Save the submitted entry.
    $entry = array(
      'is_approved' => $form_values['member']['is_approved'],
      'member_no' => $member_no,
      'member_type' => $form_values['member']['type'],
      'days' => implode('|', $memberDays),
      'first_name' => $form_values['member']['first_name'],
      'last_name' => $form_values['member']['last_name'],
      'badge_name' => $form_values['member']['badge_name'],
      'badge_type' => $form_values['member']['badge_type'],
      'display' => $form_values['member']['display'],
      'communication_method' => isset($form_values['member']['communication_method']) ?
          $form_values['member']['communication_method'] : '',
      'email' => $form_values['member']['email'],
      'street' => isset($form_values['member']['street']) ?
          $form_values['member']['street'] : '',
      'street2' => isset($form_values['member']['street2']) ?
          $form_values['member']['street2'] : '',
      'city' => isset($form_values['member']['city']) ?
          $form_values['member']['city'] : '',
      'county' => isset($form_values['member']['county']) ?
          $form_values['member']['county'] : '',
      'postcode' => isset($form_values['member']['postcode']) ?
          $form_values['member']['postcode'] : '',
      'country' => isset($form_values['member']['country']) ?
          $form_values['member']['country'] : '',
      'phone' => isset($form_values['member']['phone']) ?
          $form_values['member']['phone'] : '',
      'birth_date' => $birth_date,
      'add_on' => isset($form_values['member']['add_on']) ?
          $form_values['member']['add_on'] : '',
      'add_on_info' => isset($form_values['member']['add_on_extra']['info']) ?
          $form_values['member']['add_on_extra']['info'] : '',
      'extra_flag1' => isset($form_values['member']['extra_flag1']) ?
          $form_values['member']['extra_flag1'] : 0,
      'extra_flag2' => isset($form_values['member']['extra_flag2']) ?
          $form_values['member']['extra_flag2'] : 0,
      'is_paid' => $form_values['member']['is_paid'],
      'comment' => $form_values['member']['comment'],
      'payment_method' => $form_values['member']['payment_method'],
      'member_price' => isset($form_values['member']['member_price']) && $form_values['member']['member_price'] != '' ?
        $form_values['member']['member_price'] : 0,
      'payment_id' => $form_values['member']['payment_id'],
      'is_checked_in' => $form_values["member"]["is_checked_in"], 
    );
    
    
    if ($form_values["member"]["is_checked_in"]) {
      $entry['check_in_date'] = time();
      $entry['check_in_by'] = $uid;
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
      $return = SimpleConregStorage::insert($entry);
      
      // After saving new member, get key from insert statement to use for lead member ID.
      $lead_mid = $return;
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
      $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
      $display = $tempstore->get('display');
      $page = $tempstore->get('page');

      // Redirect to member list.
      $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
    }
  }

}
