<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\DBTNExampleAddForm
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
class SimpleConregAdminCheckIn extends FormBase {


  /**
   * Add a summary by check-in status to render array.
   */
  public function CheckInSummary($eid, &$content) {
    $descriptions = [
      0 => 'Not Checked In',
      1 => 'Checked In',
    ];
    $rows = [];
    $headers = [
      t('Status'), 
      t('Number of members'),
    ];
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberCheckInSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $status = trim($entry['is_checked_in']);
      if (isset($descriptions[$status]))
        $entry['is_checked_in'] = $descriptions[$status];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      $total += $entry['num'];
    }
    //Add a row for the total.
    $rows[] = array(t("Total"), $total);
    $content['check_in_summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );

    return $content;
  }


  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_members';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $display = NULL, $page = NULL) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.'.$eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $communicationMethods = SimpleConregOptions::communicationMethod();
    $pageSize = $config->get('display.page_size');

    $action = $form_state->get("action");
    if (isset($action) && !empty($action)) {
      switch ($action) {
        case "payCash":
          $toPay = $form_state->get("topay");
          return $this->buildCashForm($toPay);
          break;
        case "checkIn":
          $toPay = $form_state->get("topay");
          return $this->buildConfirmForm($eid, $toPay);
          break;
      }
    }

    if (isset($form_values['search']))
      $search = trim($form_values['search']);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $this->CheckInSummary($eid, $form);

    $headers = array(
      'member_no' => ['data' => t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'registered_by' =>  ['data' => t('Registered By'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'badge_type' =>  ['data' => t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'comment' =>  ['data' => t('Comment'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Paid'),
      t('Select'),
      t('Action'),
    );

    $form['search'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Custom search term'),
      '#default_value' => trim($search),
    );
    
    $form['search_button'] = array(
      '#type' => 'button',
      '#value' => t('Search'),
      '#attributes' => array('id' => "searchBtn"),
      '#validate' => array(),
      '#submit' => array('::search'),
      '#ajax' => array(
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
      ),
    );

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );      

    // Only check database if search filled in.
    if (!empty($search)) {
      $entries = SimpleConregStorage::adminMemberCheckInListLoad($eid, $search);

      foreach ($entries as $entry) {
        $mid = $entry['mid'];
        // Sanitize each entry.
        $is_paid = $entry['is_paid'];
        //$row = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $entry);
        $row = array();
        $row['mid'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['member_no']),
        );
        $row['first_name'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['first_name']),
        );
        $row['last_name'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['last_name']),
        );
        $row['email'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['email']),
        );
        $row['badge_name'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['badge_name']),
        );
        $row['registered_by'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['registered_by']),
        );
        $memberType = trim($entry['base_type']);
        $row['member_type'] = array(
          '#markup' => SafeMarkup::checkPlain(isset($types->types[$memberType]->name) ? $types->types[$memberType]->name : $memberType),
        );
        $row['days'] = array(
          '#markup' => SafeMarkup::checkPlain($entry['days_desc']),
        );
        $badgeType = trim($entry['badge_type']);
        $row['badge_type'] = array(
          '#markup' => SafeMarkup::checkPlain(isset($badgeTypes[$badgeType]) ? $badgeTypes[$badgeType] : $badgeType),
        );
        $row['comment'] = array(
          '#markup' => SafeMarkup::checkPlain(trim(substr($entry['comment'], 0, 20))),
        );
        $row['is_paid'] = array(
          '#markup' => $is_paid ? $this->t('Yes') : $this->t('No'),
        );
        $row["is_checked_in"] = array(
          //'#attributes' => array('name' => 'is_approved_'.$mid, 'id' => 'edit_is_approved_'.$mid),
          '#type' => 'checkbox',
          '#title' => t('Is Checked In'),
          '#title_display' => 'invisible',
          '#default_value' => $entry['is_checked_in'],
        );
  /*      $row['link'] = array(
          '#type' => 'dropbutton',
          '#links' => array(
            'edit_button' => array(
              'title' => $this->t('View'),
              'url' => Url::fromRoute ('simple_conreg_admin_members_edit', ['eid' => $eid, 'mid' => $mid]),
            ),
          ),
        );*/

        $form['table'][$mid] = $row;
      }
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Check-in Selected'),
      '#submit' => [[$this, 'checkInSubmit']],
      '#attributes' => array('id' => "submitBtn"),
    );

    $headers = array(
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'display' => ['data' => t('On website'), 'field' => 'm.display'],
      'communication_method' => ['data' => t('Contact'), 'field' => 'm.communication_method'],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'price' => ['data' => t('Price'), 'field' => 'm.member_total'],
      t('Select'),
      /*t('Action'),*/
    );

    $form['unpaid'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
    
    $entries = SimpleConregStorage::adminMemberUnpaidListLoad($eid);

    foreach ($entries as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $is_paid = $entry['is_paid'];
      //$row = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $entry);
      $row = [];
      $row['first_name'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['first_name']),
      );
      $row['last_name'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['last_name']),
      );
      $row['email'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['email']),
      );
      $row['badge_name'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['badge_name']),
      );
      $row['display'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($displayOptions[$entry['display']]) ? $displayOptions[$entry['display']] : $entry['display']),
      );
      $row['communication_method'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($communicationMethods[$entry['communication_method']]) ? $communicationMethods[$entry['communication_method']] : $entry['communication_method']),
      );
      $memberType = trim($entry['base_type']);
      $row['member_type'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($types->types[$memberType]->name) ? $types->types[$memberType]->name : $memberType),
      );
      $row['days'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['days_desc']),
      );
      $row['price'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['member_total']),
      );
      $row["is_selected"] = array(
        //'#attributes' => array('name' => 'is_approved_'.$mid, 'id' => 'edit_is_approved_'.$mid),
        '#type' => 'checkbox',
        '#title' => t('Is Checked In'),
        '#title_display' => 'invisible',
        '#default_value' => 0,
      );
/*      $row['link'] = array(
        '#type' => 'dropbutton',
        '#links' => array(
          'edit_button' => array(
            'title' => $this->t('View'),
            'url' => Url::fromRoute ('simple_conreg_admin_members_edit', ['eid' => $eid, 'mid' => $mid]),
          ),
        ),
      );*/

      $form['unpaid'][$mid] = $row;
    }
    
    
    // Extra table row with blank form for new member.
    $row = [];
    $row["first_name"] = [
      '#type' => 'textfield',
      '#title' => t('First Name'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row["last_name"] = [
      '#type' => 'textfield',
      '#title' => t('Last Name'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row["email"] = [
      '#type' => 'textfield',
      '#title' => t('Email'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row["badge_name"] = [
      '#type' => 'textfield',
      '#title' => t('Badge Name'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row['display'] = array(
      '#type' => 'select',
      '#title' => $this->t('On Website'),
      '#options' => $displayOptions,
      '#title_display' => 'invisible',
    );    
    $row['communication_method'] = array(
      '#type' => 'select',
      '#title' => $this->t('Contact'),
      '#options' => $communicationMethods,
      '#title_display' => 'invisible',
    );    
    $row['memberType'] = array(
      '#type' => 'select',
      '#title' => $this->t('Member Type'),
      '#options' => $types->publicNames,
      '#title_display' => 'invisible',
    );
    $row['days'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Days'),
      '#options' => $days,
      '#title_display' => 'invisible',
    );
    $row['price']=[];
    $row['add'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#submit' => [[$this, 'addMember']],
    );    
    $form['unpaid']['add'] = $row;

    $form['cash'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Pay Cash'),
      '#submit' => [[$this, 'payCash']],
    );    

    $form['card'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Pay Credit Card'),
      '#submit' => [[$this, 'payCard']],
    );    

    return $form;
  }
  
  //
  // Set up markup fields to display cash payment.
  //
  public function buildCashForm($toPay) {
    $form = [];
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Please confirm cash received from:'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $total_price = 0;
    foreach ($toPay as $mid) {
      if ($member = SimpleConregStorage::load(['mid' => $mid])) {
        $form['member'.$mid] = [
          '#type' => 'markup',
          '#markup' => $this->t('Member @first @last to pay @total',
            ['@first' => $member['first_name'],
             '@last' => $member['last_name'],
             '@total' => $member['member_total'],
            ]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
        $total_price += $member['member_total'];
      }
    }
    $form['total'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Total to pay @total', ['@total' => $total_price]),
      '#prefix' => '<div><h4>',
      '#suffix' => '</h4></div>',
    ];
    $form['confirm'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Confirm Cash Payment'),
      '#submit' => [[$this, 'confirmPayCash']],
    );  
    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelAction']],
    );
    return $form;
  }

  //
  // Set up markup fields to display check-in confirm.
  //
  public function buildConfirmForm($eid, $toPay) {
    $form = [];
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Please confirm badges for:'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $maxMemberNo = SimpleConregStorage::loadMaxMemberNo($eid);
    foreach ($toPay as $mid) {
      if ($member = SimpleConregStorage::load(['mid' => $mid])) {
        $update = ['mid' => $mid];
        if (!(isset($member['is_confirmed']) && $member['is_approved']))
          $update['is_approved'] = 1;
        if (isset($member['member_no']) && $member['member_no'])
          $member_no = $member['member_no'];
        else {
          $member_no = ++$maxMemberNo;
          $update['member_no'] = $member_no;
        }
        SimpleConregStorage::update($update);
        $form['member'.$mid] = [
          '#type' => 'markup',
          '#markup' => $this->t('Badge number @memberno for @first @last',
            ['@memberno' => $member_no,
             '@first' => $member['first_name'],
             '@last' => $member['last_name'],
            ]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
      }
    }
    $form['confirm'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Confirm Check-In'),
      '#submit' => [[$this, 'confirmCheckInSubmit']],
    );  
    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelAction']],
    );
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
  
  public function addMember(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();
    // Assign random key for payment URL.
    $rand_key = mt_rand();
    if (!empty($form_values['unpaid']['add']['badge_name']))
      $badge_name = trim($form_values['unpaid']['add']['badge_name']);
    else
      $badge_name = trim($form_values['unpaid']['add']['first_name'].' '.$form_values['unpaid']['add']['last_name']);
    // Work out price.
    $baseType = $form_values['unpaid']['add']['memberType'];
    $memberType = $baseType;
    $price = $types->types[$baseType]->price;
    $daysPrice = 0;
    $memberDays = '';
    $memberDayDescs = $types->types[$baseType]->defaultDays;
    $daysSel = [];
    $daysDescs = [];
    foreach ($form_values['unpaid']['add']['days'] as $key => $val) {
      if (!empty($val) && isset($types->types[$memberType]->days[$key])) {
        $daysPrice += $types->types[$memberType]->days[$key]->price;
        $daysSel[] = $key;
        $daysDescs[] = $days[$key];
      }
    }
    
    if ($daysPrice >0 and $daysPrice < $price) {
      $price = $daysPrice;
      $memberType = $baseType.implode('', $daysSel);
      $memberDays = implode('|', $daysSel);
      $memberDayDescs = implode(', ', $daysDescs);
    }
    // Save the submitted entry.
    $entry = array(
      'eid' => $eid,
      'lead_mid' => 0,
      'random_key' => $rand_key,
      'member_type' => $memberType,
      'base_type' => $baseType,
      'days' => $memberDays,
      'days_desc' => $memberDayDescs,
      'first_name' => $form_values['unpaid']['add']['first_name'],
      'last_name' => $form_values['unpaid']['add']['last_name'],
      'badge_name' => $badge_name,
      'badge_type' => 'A',
      'display' => empty($form_values['unpaid']['add']['display']) ?
          'N' : $form_values['unpaid']['add']['display'],
      'communication_method' => isset($form_values['unpaid']['add']['communication_method']) ?
          $form_values['unpaid']['add']['communication_method'] : '',
      'email' => $form_values['unpaid']['add']['email'],
      'member_price' => $price,
      'member_total' => $price,
      'add_on_price' => 0,
      'payment_amount' => $price,
      'join_date' => time(),
    );
    // Insert to database table.
    $return = SimpleConregStorage::insert($entry);
    
    if ($return) {
      // Update member with own member ID as lead member ID.
      $update = array('mid' => $return, 'lead_mid' => $return);
      $return = SimpleConregStorage::update($update);
      // Clear form fields.
      $form_state->setUserInput([]);
    }
    $form_state->setRebuild();
  }
  
  public function checkInSubmit(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $form_values = $form_state->getValues();

    $toPay = [];
    foreach ($form_values["table"] as $mid => $member) {
      if (isset($member["is_checked_in"]) && $member["is_checked_in"]) {
        $toPay[] = $mid;
      }
    }
    if (count($toPay)) {
      $form_state->set("action", "checkIn");
      $form_state->set("topay", $toPay);
    }
    $form_state->setRebuild();
  }

  public function payCash(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();

    $toPay = [];
    foreach ($form_values["unpaid"] as $mid => $member) {
      if (isset($member["is_selected"]) && $member["is_selected"]) {
        $toPay[] = $mid;
      }
    }
    // No need to proceed unless members have been selected.
    if (count($toPay)) {
      $form_state->set("action", "payCash");
      $form_state->set("topay", $toPay);
    }
    $form_state->setRebuild();
  }

  public function confirmPayCash(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();

    $payment_amount = 0;
    $lead_mid = 0;
    $toPay = $form_state->get("topay");
    // Loop through selected members to get lead and total price.
    foreach ($toPay as $mid) {
      if ($member = SimpleConregStorage::load(['mid' => $mid])) {
        // Make first member lead member.
        if ($lead_mid == 0)
          $lead_mid = $mid;
        $payment_amount += $member['member_total'];
      }
    }
    // Loop again to update members.
    foreach ($toPay as $mid) {
      $update = [
        'mid' => $mid,
        'lead_mid' => $lead_mid,
        'payment_amount' => $payment_amount,
        'payment_method' => 'Cash',
        'is_paid' => 1,
      ];
      SimpleConregStorage::update($update);
    }
    $form_state->set('action', 'checkIn');
    $form_state->setRebuild();
  }

  public function confirmCheckInSubmit(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();
    $toPay = $form_state->get("topay");
    // Loop through members and mark checked in.
    foreach ($toPay as $mid) {
      $update = [
        'mid' => $mid,
        'is_checked_in' => 1,
      ];
      SimpleConregStorage::update($update);
    }
    $form_state->set('action', 'checkin');
    $form_state->setRebuild();
  }
  
  public function payCard(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();

    $payment_amount = 0;
    $lead_mid = 0;
    $toPay = $form_state->get("topay");
    // Loop through selected members to get lead and total price.
    foreach ($form_values["unpaid"] as $mid => $member) {
      if (isset($member["is_selected"]) && $member["is_selected"]) {
        if ($member = SimpleConregStorage::load(['mid' => $mid])) {
          // Make first member lead member.
          if ($lead_mid == 0) {
            $lead_mid = $mid;
            $lead_key = $member['random_key'];
          }
          $payment_amount += $member['member_total'];
        }
      }
    }
    // Loop again to update members.
    foreach ($form_values["unpaid"] as $mid => $member) {
      if (isset($member["is_selected"]) && $member["is_selected"]) {
        $update = [
          'mid' => $mid,
          'lead_mid' => $lead_mid,
          'payment_amount' => $payment_amount,
        ];
        SimpleConregStorage::update($update);
      }
    }
    if ($lead_mid)
      // Redirect to payment form.
      $form_state->setRedirect('simple_conreg_payment',
        array('mid' => $lead_mid, 'key' => $lead_key)
      );
  }

  public function cancelAction(array &$form, FormStateInterface $form_state) {
    $form_state->set('action', '');
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
    $uid = \Drupal::currentUser()->id();
    foreach ($form_values["table"] as $mid => $member) {
      if ($member["is_checked_in"] != $saved_members[$mid]["is_checked_in"]) {
        if ($member["is_checked_in"])
          $entry = array('mid' => $mid, 'is_checked_in' => $member["is_checked_in"], 'check_in_date' => time(), 'check_in_by' => $uid);
        else
          $entry = array('mid' => $mid, 'is_checked_in' => $member["is_checked_in"]);
        $return = SimpleConregStorage::update($entry);
      }
    }
    \Drupal\Core\Cache\Cache::invalidateTags(['simple-conreg-member-list']);
  }
}    

