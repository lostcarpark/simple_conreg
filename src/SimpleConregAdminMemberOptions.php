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

    $groupList = SimpleConregFieldOptions::getFieldOptionGroupedList($eid, $config);

    $user = \Drupal::currentUser();
    foreach ($groupList as $val) {
      if (empty($val['optid'])) {
        // optid not set, so entry is group heading.
        $groupAdded = FALSE;
        $grpid = $val['grpid'];
        $groupTitle = $val['group_title'];
      } else {
        // Entry is option.
        if ($user->hasPermission('view field option ' . $val['optid'] . ' event ' . $eid)) {
          // Only display if user has permission to see option.
          if (!$groupAdded) {
            $options[$grpid] = $groupTitle;
            $groupAdded = TRUE;
          }
          $options[$grpid."_".$val['optid']] = " - ".$val['option_title'];
        }
      }
    }


    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['selOption']))
      $selection = $form_values['selOption'];
    elseif (empty($selection)) {
      // If display not submitted from form or passed in through URL, take last value from session.
      $selection = $tempstore->get('adminMemberSelectedOption');
    }
    if (empty($selection) || !array_key_exists($selection, $options))
      $selection = key($options); // If still no display specified, or invalid option, default to first key in displayOptions.
    list($selGroup, $selOption) = explode('_', $selection);

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
    );

    // Check if single option selected.
    $displayOpts = [];
    $optionTotals = [];
    if (!empty($selOption)) {
      $headers['option_'.$selOption] = ['data' => $options[$selGroup.'_'.$selOption], 'field' => 'option_'.$selOption];
      $displayOpts[] = $selOption;
      $optionTotals[$selOption] = 0;
    }
    else if (!empty($selGroup)) {
      $selOption = [];
      foreach($groupList as $groupOption) {
        if ($groupOption['grpid'] == $selGroup && !empty($groupOption['optid']) && $user->hasPermission('view field option ' . $groupOption['optid'] . ' event ' . $eid)) {
          $selOption[] = $groupOption['optid'];
          $displayOpts[] = $groupOption['optid'];
          $headers['option_'.$groupOption['optid']] = ['data' => $groupOption['option_title'], 'field' => 'option_'.$groupOption['optid']];
          $optionTotals[$groupOption['optid']] = 0;
        }
      }
    }
    
    $headers['total'] = ['data' => 'Total', 'field' => 'total'];

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );      

    if (!empty($selOption)) {
      // Fetch all entries for selected option or group.
      $entries = SimpleConregFieldOptionStorage::adminOptionMemberListLoad($eid, $selOption);
      // Reorganise to put all entries for a member in the same row.
      $optRows = [];
      foreach ($entries as $entry) {
        if (!isset($optRows[$entry['mid']]))
          $optRows[$entry['mid']] = ['mid' => $entry['mid'], 'first_name' => $entry['first_name'], 'last_name' => $entry['last_name'], 'email' => $entry['email']];
        $optRows[$entry['mid']]['option_'.$entry['optid']] = ($entry['is_selected'] ? '✓ ' . $entry['option_detail'] : '');
      }
      // Track the total number of members in the category.
      $totalRows = 0;
      
      // Now loop through the combined results.
      foreach ($optRows as $entry) {
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
        $rowTotal = 0;
        foreach ($displayOpts as $display) {
          if (isset($entry['option_' . $display])) {
            $val = $entry['option_' . $display];
            $rowTotal++;
            $optionTotals[$display]++;
          }
          else
            $val = '';
          $row['option_'.$display] = array(
            '#markup' => SafeMarkup::checkPlain($val),
          );
        }
        $row['total'] = [
          '#markup' => $rowTotal,
        ];
        $form['table'][] = $row;
        $totalRows ++;
      }
    }
    $totalRow = [
      'first_name' => ['#markup' => $this->t('Total')],
      'last_name' => ['#markup' => ''],
      'email' => ['#markup' => ''],
    ];
    foreach ($displayOpts as $display)
      $totalRow['option_'.$display] = ['#markup' => $optionTotals[$display]];
    $totalRow['total'] = ['#markup' => $totalRows];
    $form['table'][] = $totalRow;
    
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

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}    

