<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregMemberEdit
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
class SimpleConregMemberEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_member_edit';
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


    // Load the member record.
    $member = SimpleConregStorage::load(['eid' => $eid, 'mid' => $mid, 'is_paid' => 1]);
    $memberType = $member['member_type'];
    $fieldsetConfig = $types->types[$memberType]->config;
    // Check member exists.
    if (!is_array($member)) {
      // Member not in database. Display error.
      $form['error'] = array(
        '#markup' => $this->t('Sorry, we couldn\'t find that member.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return $form;
    }
    $lead_mid = $member['lead_mid'];

    // Add member options to member array.
    $member['options'] = SimpleConregFieldOptions::getMemberOptionValues($mid);

    // Check out who is editing.
    $user = \Drupal::currentUser();
    $email = $user->getEmail();

    // Get the member with the matching email address.
    $owner = SimpleConregStorage::load(['eid' => $eid, 'email' => $email, 'mid' => $lead_mid, 'is_paid' => 1]);
    // If couldn't find member with matching Lead MID, check on MID, as editor may not be group leader.
    if (!is_array($owner))
      $owner = SimpleConregStorage::load(['eid' => $eid, 'email' => $email, 'mid' => $mid, 'is_paid' => 1]);
    if (!is_array($owner)) {
      // Member not in database. Display error.
      $form['error'] = array(
        '#markup' => $this->t('Sorry, you don\'t have permission to do that.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return $form;
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

    $form['member']['intro'] = array(
      '#markup' => $config->get('member_edit.intro_text'),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    $form['member']['member_no'] = array(
      '#markup' => $this->t('Member number: @member_no', ['@member_no' => $member['member_no']]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    $form['member']['first_name'] = array(
      '#markup' => $fieldsetConfig->get('fields.first_name_label') . ': ' .$member['first_name'],
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    $form['member']['last_name'] = array(
      '#markup' => $fieldsetConfig->get('fields.last_name_label') . ': ' . $member['last_name'],
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    if (!$config->get('member_edit.email_editable')) {
      $form['member']['email'] = array(
        '#markup' => $fieldsetConfig->get('fields.email_label') . ': ' . $member['email'],
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      );
    }

    if (!$config->get('member_edit.badge_name_editable')) {
      $form['member']['badge_name'] = array(
        '#markup' => $fieldsetConfig->get('fields.badge_name_label') . ': ' . $member['badge_name'],
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      );
    }

    $form['member']['type'] = array(
      '#markup' => $fieldsetConfig->get('fields.membership_type_label') . ': ' . $types->privateOptions[$member['member_type']],
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    $dayVals = '';
    $sep = '';
    if (isset($member['days'])) {
      foreach(explode('|', $member['days']) as $day) {
        $dayVals .= $sep . $days[$day];
        $sep = ', ';
      }
    }
    $form['member']['days'] = array(
      '#markup' => $this->t('Days') . ': ' . $dayVals,
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    if ($config->get('member_edit.email_editable')) {
      $form['member']['email'] = array(
        '#type' => 'email',
        '#title' => $fieldsetConfig->get('fields.email_label'),
        '#default_value' => (isset($member['email']) ? $member['email'] : ''),
      );
    }

    if ($config->get('member_edit.badge_name_editable')) {
      $badgename_max_length = $fieldsetConfig->get('fields.badge_name_max_length');
      $form['member']['badge_name'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.badge_name_label'),
        '#default_value' => $member['badge_name'],
        '#required' => TRUE,
        '#maxlength' => (empty($badgename_max_length) ? 128 : $badgename_max_length),
        '#attributes' => array(
          'id' => "edit-members-badge-name",
          'class' => array('edit-members-badge-name')),
      );
    }

    if (!empty($fieldsetConfig->get('fields.display_label'))) {
      $form['member']['display'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.display_label'),
        '#description' => $this->t('Select how you would like to appear on the membership list.'),
        '#options' => SimpleConregOptions::display(),
        '#default_value' => (isset($member['display']) ? $member['display'] : 'F'),
        '#required' => TRUE,
      );
    }

    if (!empty($fieldsetConfig->get('fields.communication_method_label'))) {
      $form['member']['communication_method'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.communication_method_label'),
        '#description' => $fieldsetConfig->get('fields.communication_method_description'),
        '#options' => SimpleConregOptions::communicationMethod($eid, $config, FALSE),
        '#default_value' => (isset($member['communication_method']) ? $member['communication_method'] : 'E'),
        '#required' => TRUE,
      );
    }


    if (!empty($fieldsetConfig->get('fields.street_label'))) {
      $form['member']['street'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.street_label'),
        '#default_value' => $member['street'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.street2_label'))) {
      $form['member']['street2'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.street2_label'),
        '#default_value' => $member['street2'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.city_label'))) {
      $form['member']['city'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.city_label'),
        '#default_value' => $member['city'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.county_label'))) {
      $form['member']['county'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.county_label'),
        '#default_value' => $member['county'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.postcode_label'))) {
      $form['member']['postcode'] = array(
        '#type' => 'textfield',
        '#title' => $fieldsetConfig->get('fields.postcode_label'),
        '#default_value' => $member['postcode'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.country_label'))) {
      $form['member']['country'] = array(
        '#type' => 'select',
        '#title' => $fieldsetConfig->get('fields.country_label'),
        '#options' => $countryOptions,
        '#default_value' => (isset($member['country']) ? $member['country'] : $defaultCountry),
        '#required' => TRUE,
      );
    }

    if (!empty($fieldsetConfig->get('fields.phone_label'))) {
      $form['member']['phone'] = array(
        '#type' => 'tel',
        '#title' => $fieldsetConfig->get('fields.phone_label'),
        '#default_value' => $member['phone'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.birth_date_label'))) {
      $form['member']['birth_date'] = array(
        '#type' => 'date',
        '#title' => $fieldsetConfig->get('fields.birth_date_label'),
        '#default_value' => $member['birth_date'],
      );
    }

    if (!empty($fieldsetConfig->get('fields.age_label'))) {
      $form['member']['age'] = array(
        '#type' => 'number',
        '#title' => $fieldsetConfig->get('fields.age_label'),
        '#default_value' => $member['age'],
      );
    }


    // Get member add-on details.
    $addon = isset($form_values['member']['add_on']) ? $form_values['member']['add_on'] : '';
    $form['member']['add_on'] = SimpleConregAddons::getAddon($config,
      $addon,
      $addOnOptions, -1, [$this, 'updateMemberPriceCallback'], $form_state, $mid);

    if (!empty($fieldsetConfig->get('extras.flag1'))) {
      $form['member']['extra_flag1'] = array(
        '#type' => 'checkbox',
        '#title' => $fieldsetConfig->get('extras.flag1'),
        '#default_value' => $member['extra_flag1'],
      );
    }

    if (!empty($fieldsetConfig->get('extras.flag2'))) {
      $form['member']['extra_flag2'] = array(
        '#type' => 'checkbox',
        '#title' => $fieldsetConfig->get('extras.flag2'),
        '#default_value' => $member['extra_flag2'],
      );
    }

    $optionCallbacks = [];
    $callback = [$this, 'updateMemberOptionFields'];
    $fieldset = isset($types->types[$member['member_type']]->fieldset) ? $types->types[$member['member_type']]->fieldset : 0;
    SimpleConregFieldOptions::addOptionFields($eid, $fieldset, $form['member'], $form_values['member'], $optionCallbacks, $callback, NULL, $member);

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save member'),
    );

    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'cancel',
      '#submit' => [[$this, 'submitCancel']],
      '#limit_validation_errors' => [],
    );

    $form_state->set('mid', $mid);
    $form_state->set('fieldset', $fieldset);
    $form_state->set('badgename_max_length', $badgename_max_length);
    $form_state->set('option_callbacks', $optionCallbacks);
    return $form;
  }
  
  // Callback function for "member type" and "add-on" drop-downs. Replace price fields.
  public function updateMemberPriceCallback(array $form, FormStateInterface $form_state)
  {
    $ajax_response = new AjaxResponse();
    foreach ($addons as $addOnId) {
      if (!empty($form['member']['add_on'][$addOnId]['extra'])) {
        $id = '#member_addon_'.$addOnId.'_info';
        $ajax_response->addCommand(new HtmlCommand($id, render($form['member']['add_on'][$addOnId]['extra']['info'])));
      }
    }

    //We don't currently display a total price, but keep the below commented in case we add it infuture.
    //$ajax_response->addCommand(new HtmlCommand('#Pricing', $form['payment']['price']));

    return $ajax_response;
  }
  
  // Callback function for option fields - add/remove detail field.
  public function updateMemberOptionFields(array $form, FormStateInterface $form_state)
  {
    // Get the triggering element.    
    $trigger = $form_state->getTriggeringElement()['#name'];
    // Get array of items to return, keyed by trigering element.
    $optionCallbacks = $form_state->get('option_callbacks');
    $callback = $optionCallbacks[$trigger];
    // Build the index of the element to return.
    switch ($callback[0]) {
      case 'group':
        return $form['member'][$callback[2]];
      case 'detail':
        return $form['member'][$callback[2]]['options']['container_'.$callback[3]];
    }
  }


  /*
   * Validate form before submit.
   */

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  
  /*
   * Submit handler for cancel button.
   */

  public function submitCancel(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    // Get session state to return to correct page.
    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    $display = $tempstore->get('display');
    $page = $tempstore->get('page');
    // Redirect to member list.
    $form_state->setRedirect('simple_conreg_portal', ['eid' => $eid]);
  }

  /*
   * Submit handler for member edit form.
   */

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $mid = $form_state->get('mid');
    $fieldset = $form_state->get('fieldset');

    $config = $this->config('simple_conreg.settings.'.$eid);
    $form_values = $form_state->getValues();

    // Process option fields to remove any modifications from form values.
    $optionVals = [];
    SimpleConregFieldOptions::procesOptionFields($eid, $fieldset, $form_values['member'], $optionVals);

    // Save the submitted entry.
    $entry = [
      'mid' => $mid,
      'badge_name' => trim($form_values['member']['']),
      'update_date' => time(),
    ];
    if (isset($form_values['member']['email'])) $entry['email'] = trim($form_values['member']['email']);
    if (isset($form_values['member']['badge_name'])) $entry['badge_name'] = trim($form_values['member']['badge_name']);
    if (isset($form_values['member']['display'])) $entry['display'] = $form_values['member']['display'];
    if (isset($form_values['member']['communication_method'])) $entry['communication_method'] = $form_values['member']['communication_method'];
    if (isset($form_values['member']['street'])) $entry['street'] = trim($form_values['member']['street']);
    if (isset($form_values['member']['street2'])) $entry['street2'] = trim($form_values['member']['street2']);
    if (isset($form_values['member']['city'])) $entry['city'] = trim($form_values['member']['city']);
    if (isset($form_values['member']['county'])) $entry['county'] = trim($form_values['member']['county']);
    if (isset($form_values['member']['postcode'])) $entry['postcode'] = trim($form_values['member']['postcode']);
    if (isset($form_values['member']['country'])) $entry['country'] = trim($form_values['member']['country']);
    if (isset($form_values['member']['phone'])) $entry['phone'] = trim($form_values['member']['phone']);
    if (isset($form_values['member']['birth_date'])) $entry['birth_date'] = $form_values['member']['birth_date'];
    if (isset($form_values['member']['age'])) $entry['age'] = $form_values['member']['age'];
    if (isset($form_values['member']['extra_flag1'])) $entry['extra_flag1'] = $form_values['member']['extra_flag1'];
    if (isset($form_values['member']['extra_flag2'])) $entry['extra_flag2'] = $form_values['member']['extra_flag2'];
    $return = SimpleConregStorage::update($entry);

    // Update member field options.
    SimpleConregFieldOptions::updateOptionFields($mid, $optionVals);

    // Create ClickUp tasks for options.
    SimpleConregClickUp::createMemberTasks($eid, $mid, $optionVals);

    // All members saved. Now save any add-ons.
    SimpleConregAddons::saveMemberAddons($config, $form_values, $mid);

    if ($return) {

      // Get session state to return to correct page.
      $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
      $display = $tempstore->get('display');
      $page = $tempstore->get('page');

      // Redirect to member list.
      $form_state->setRedirect('simple_conreg_portal', ['eid' => $eid]);
    }
  }


}
