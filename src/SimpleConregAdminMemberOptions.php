<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMemberOptions
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Link;
use Drupal\Core\URL;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\devel;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberOptions extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_options';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $selOption = 0) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.'.$eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $pageSize = $config->get('display.page_size');

    $optionList = SimpleConregStorage::adminOptionListLoad();
    $options = [];
    foreach ($optionList as $val) {
      $options[$val['optid']] = $val['option_title'];
    }

    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['selOption']))
      $selOption = $form_values['selOption'];
    elseif (empty($selOption)) {
      // If display not submitted from form or passed in through URL, take last value from session.
      $selOption = $tempstore->get('adminMemberSelectedOption');
    }
    if (empty($selOption) || !array_key_exists($selOption, $options))
      $selOption = key($options); // If still no display specified, or invalid option, default to first key in displayOptions.

    $tempstore->set('adminMemberSelectedOption', $selOption);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $form['selOption'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select member option'),
      '#options' => $options,
      '#default_value' => $selOption,
      '#required' => TRUE,
      '#ajax' => array(
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ),
    );

    $headers = array(
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'is_selected' => ['data' => t('Selected?'), 'field' => 'm.is_selected'],
      'option_detail' => ['data' => t('Detail'), 'field' => 'm.option_detail'],
    );

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );      

    if (!empty($selOption)) {
      $entries = SimpleConregStorage::adminOptionMemberListLoad($eid, $selOption);

      foreach ($entries as $entry) {
        $row = array();
        $row['first_name'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['first_name']),
        );
        $row['last_name'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['last_name']),
        );
        $row['email'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['email']),
        );
        $row['is_selected'] = array(
          '#markup' => $entry['is_selected'] ? $this->t('Yes') : $this->t('No'),
        );
        $row['option_detail'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['option_detail']),
        );
        $form['table'][] = $row;
      }
    }
    
    return $form;
  }

  // Callback function for "member type" and "add-on" drop-downs. Replace price fields.
  public function updateApprovedCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $ajax_response = new AjaxResponse();
    if (preg_match("/table\[(\d+)\]\[is_approved\]/", $triggering_element['#name'], $matches)) {
      $mid = $matches[1];
      $form['table'][$mid]["member_div"]["member_no"]['#value'] = $triggering_element['#value'];
      $ajax_response->addCommand(new HtmlCommand('#member_no_'.$mid, render($form['table'][$mid]["member_div"]["member_no"]['#value'])));
      //$ajax_response->addCommand(new AlertCommand($row." = ".));
    }
    return $ajax_response;
  }

  // Callback function for "member type" and "add-on" drop-downs. Replace price fields.
  public function updateTestCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new AlertCommand($triggering_element['#name']." = ".$triggering_element['#value']));
    return $ajax_response;
  }

  // Callback function for "display" drop down.
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  public function search(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  	  $saved_members = SimpleConregStorage::loadAllMemberNos($eid);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $form_values = $form_state->getValues();
    $saved_members = SimpleConregStorage::loadAllMemberNos($eid);
    $max_member = SimpleConregStorage::loadMaxMemberNo($eid);
    foreach ($form_values["table"] as $mid => $member) {
      if (($member["is_approved"] != $saved_members[$mid]["is_approved"]) ||
          ($member["is_approved"] && $member["member_no"] != $saved_members[$mid]["member_no"])) {
        if ($member["is_approved"]) {
	        if (empty($member["member_no"])) {
	          // No member no specified, so assign next one.
	          $max_member++;
	          $member_no = $max_member;
	        } else {
	          // Member no specified. Adjust next member no.
	          $member_no = $member["member_no"];
	          if ($member_no > $max_member)
	            $max_member = $member_no;
	        }
	      } else {
	        // No member number for unapproved members.
	        $member_no = 0;
	      }
        $entry = array('mid' => $mid, 'is_approved' => $member["is_approved"], 'member_no' => $member_no);
        $return = SimpleConregStorage::update($entry);
      }
    }
    \Drupal\Core\Cache\Cache::invalidateTags(['simple-conreg-member-list']);
  }
}    

