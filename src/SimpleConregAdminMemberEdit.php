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
    $memberClasses = SimpleConregOptions::memberClasses($eid, $config);
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

    // Get member class for selected member type.
    $curMemberClassRef = (!empty($memberType) && isset($types->types[$memberType])) ? $types->types[$memberType]->memberClass : array_key_first($memberClasses->classes);
    $curMemberClass = $memberClasses->classes[$curMemberClassRef];

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

    $firstname_max_length = $curMemberClass->max_length->first_name;
    $form['member']['first_name'] = array(
      '#type' => 'textfield',
      '#title' => $curMemberClass->fields->first_name,
      '#size' => 29,
      '#default_value' => (isset($member->first_name) ? $member->first_name : ''),
      '#maxlength' => (empty($firstname_max_length) ? 128 : $firstname_max_length),
      '#required' => ($config->get('fields.first_name_mandatory') ? TRUE : FALSE),
      '#attributes' => array(
        'id' => "edit-member-first-name",
        'class' => array('edit-members-first-name')),
    );

    $lastname_max_length = $curMemberClass->max_length->last_name;
    $form['member']['last_name'] = array(
      '#type' => 'textfield',
      '#title' => $curMemberClass->fields->last_name,
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
      '#title' => $curMemberClass->fields->email,
      '#default_value' => (isset($member->email) ? $member->email : ''),
    );

    $form['member']['type'] = array(
      '#type' => 'select',
      '#title' => $curMemberClass->fields->membership_type,
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


    $badgename_max_length = $curMemberClass->max_length->badge_name;
    $form['member']['badge_name'] = [
      '#type' => 'textfield',
      '#title' => $curMemberClass->fields->badge_name,
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

    if (!empty($curMemberClass->fields->display)) {
      $form['member']['display'] = array(
        '#type' => 'select',
        '#title' => $curMemberClass->fields->display,
        '#description' => $this->t('Select how you would like to appear on the membership list.'),
        '#options' => SimpleConregOptions::display(),
        '#default_value' => (isset($member->display) ? $member->display : 'F'),
        '#required' => TRUE,
      );
    }

    if (!empty($curMemberClass->fields->communication_method)) {
      $form['member']['communication_method'] = array(
        '#type' => 'select',
        '#title' => $curMemberClass->fields->communication_method,
        '#description' => $curMemberClass->fields->communication_method_description,
        '#options' => SimpleConregOptions::communicationMethod($eid, $config, FALSE),
        '#default_value' => (isset($member->communication_method) ? $member->communication_method : 'E'),
        '#required' => TRUE,
      );
    }

    if (!empty($curMemberClass->fields->street)) {
      $form['member']['street'] = array(
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->street,
        '#default_value' => (isset($member->street) ? $member->street : ''),
      );
    }

    if (!empty($curMemberClass->fields->street2)) {
      $form['member']['street2'] = array(
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->street2,
        '#default_value' => (isset($member->street2) ? $member->street2 : ''),
      );
    }

    if (!empty($curMemberClass->fields->city)) {
      $form['member']['city'] = array(
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->city,
        '#default_value' => (isset($member->city) ? $member->city : ''),
      );
    }

    if (!empty($curMemberClass->fields->county)) {
      $form['member']['county'] = array(
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->county,
        '#default_value' => (isset($member->county) ? $member->county : ''),
      );
    }

    if (!empty($curMemberClass->fields->postcode)) {
      $form['member']['postcode'] = array(
        '#type' => 'textfield',
        '#title' => $curMemberClass->fields->postcode,
        '#default_value' => (isset($member->postcode) ? $member->postcode : ''),
      );
    }

    if (!empty($curMemberClass->fields->country)) {
      $form['member']['country'] = array(
        '#type' => 'select',
        '#title' => $curMemberClass->fields->country,
        '#options' => $countryOptions,
        '#default_value' => (isset($member->country) ? $member->country : $defaultCountry),
        '#required' => TRUE,
      );
    }

    if (!empty($curMemberClass->fields->phone)) {
      $form['member']['phone'] = array(
        '#type' => 'tel',
        '#title' => $curMemberClass->fields->phone,
        '#default_value' => (isset($member->phone) ? $member->phone : ''),
      );
    }

    if (!empty($curMemberClass->fields->birth_date)) {
      $form['member']['birth_date'] = array(
        '#type' => 'date',
        '#title' => $curMemberClass->fields->birth_date,
        '#default_value' => (isset($member->birth_date) ? $member->birth_date : ''),
      );
    }

    if (!empty($curMemberClass->fields->age)) {
      $form['member']['age'] = array(
        '#type' => 'number',
        '#title' => $curMemberClass->fields->age,
        '#default_value' => (isset($member->age) ? $member->age : ''),
      );
    }

    if (!empty($curMemberClass->extras->flag1)) {
      $form['member']['extra_flag1'] = array(
        '#type' => 'checkbox',
        '#title' => $curMemberClass->extras->flag1,
        '#default_value' => (isset($member->extra_flag1) ? $member->extra_flag1 : ''),
      );
    }

    if (!empty($curMemberClass->extras->flag2)) {
      $form['member']['extra_flag2'] = array(
        '#type' => 'checkbox',
        '#title' => $curMemberClass->extras->flag2,
        '#default_value' => (isset($member->extra_flag2) ? $member->extra_flag2 : ''),
      );
    }

    // Get field options from form state. If not set, get from config.
    $fieldOptions = $form_state->get('fieldOptions');
    if (is_null($fieldOptions)) {
      $fieldOptions = FieldOptions::getFieldOptions($eid);
    }
    // Add the field options to the form. Display both global and member fields. Display public and private fields. 
    $fieldOptions->addOptionFields($curMemberClassRef, $form['member'], $member, NULL, TRUE, FALSE);

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
      '#default_value' => (isset($member->member_price) ? $member->member_price : '0'),
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
      '#value' => $this->t('Save member'),
    );

    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#limit_validation_errors' => [],
      '#submit' => [[$this, 'submitCancel']],
    );

    $form_state->set('member', $member);
    $form_state->set('mid', $mid);
    $form_state->set('member_class', $curMemberClassRef);
    return $form;
  }

  /*
   * Validate form on submit.
   */

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $form_values = $form_state->getValues();
    if ($form_values['member']['member_price'] == '' || !is_numeric($form_values['member']['member_price'])) {
      $form_state->setErrorByName('member][member_price', $this->t('Member price must be a number.'));
    }
  }

  /*
   * Submit handler for cancel button.
   */

  public function submitCancel(array &$form, FormStateInterface $form_state)
  {
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

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $mid = $form_state->get('mid');
    $curMemberClassRef = $form_state->get('member_class');
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
    $fieldOptions->procesOptionFields($curMemberClassRef, $form_values['member'], $mid, $options);

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

    if (!empty($form_values["member"]["is_checked_in"])) {
      $user = User::load(\Drupal::currentUser()->id());
      $member->check_in_date = time();
      $member->check_in_by = $user->get('uid')->value;
    }

    $join_date = $form_values['member']['join_date']->getTimestamp();
    // If Join Date specified, use it. If not, use current date/time.
    if ($join_date == 0)
      $member->join_date = time();
    else
      // Date specified, and successfully converted into timestamp.
      $member->join_date = $join_date;

    if (isset($mid)) {
      // Existing member. Member ID should already be set, but make sure.
      $member->mid = $mid;
    } else {
      // New member. Event ID must be set.
      $member->eid = $eid;
    }

    $return = $member->saveMember();
    
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
            if (isset($member->email) && $member->email != '') {
              if (isset($member->communication_method) &&
                  isset($communications_methods[$member->communication_method]) &&
                  $communications_methods[$member->communication_method]) {
                // Subscribe member if criteria met.
                $subscription_manager->subscribe($member->email, $newsletter_id, FALSE, 'website');
                \Drupal::messenger()->addMessage($this->t('Subscribed %email to %newsletter.', ['%email' => $member->email, '%newsletter' => $newsletter_id]));
              } else {
                // Unsubscribe member if criteria not met (their communications method may have changed).
                $subscription_manager->unsubscribe($member->email, $newsletter_id, FALSE, 'website');
                \Drupal::messenger()->addMessage($this->t('Unsubscribed %email from %newsletter.', ['%email' => $member->email, '%newsletter' => $newsletter_id]));
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
