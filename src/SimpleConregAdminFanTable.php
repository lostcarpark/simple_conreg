<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminFanTable
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
class SimpleConregAdminFanTable extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_members';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $lead_mid = 0) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.'.$eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $upgrades = SimpleConregOptions::memberUpgrades($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $communicationMethods = SimpleConregOptions::communicationMethod($eid, $config, TRUE);
    $pageSize = $config->get('display.page_size');

    // If action set, display either payment or check-in subpage.
    $action = $form_state->get("action");
    if (isset($action) && !empty($action)) {
      switch ($action) {
        case "payCash":
          $toPay = $form_state->get("topay");
          return $this->buildCashForm($toPay, $config);
          break;
      }
    }

    if (isset($form_values['search']))
      $search = trim($form_values['search']);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    SimpleConregController::memberAdminMemberListSummary($eid, $form);

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
      /*t('Action'),*/
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
        $memberType = trim($entry['member_type']);
        if (isset($upgrades->options[$memberType][$entry['days']]))
          $row['member_type'] = array(
            '#type' => 'select',
            //'#title' => $fieldsetConfig->get('fields.membership_type_label'),
            '#options' => $upgrades->options[$memberType][$entry['days']],
            '#default_value' => 0,
            '#required' => TRUE,
          );
        else
          $row['member_type'] = array(
            '#markup' => SafeMarkup::checkPlain(isset($types->types[$memberType]->name) ? $types->types[$memberType]->name : $memberType),
          );


        if (!empty($entry['days'])) {
          $dayDescs = [];
          foreach(explode('|', $entry['days']) as $day) {
            $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
          }
          $memberDays = implode(', ', $dayDescs);
        } else
          $memberDays = '';
        $row['days'] = array(
          '#markup' => SafeMarkup::checkPlain($memberDays),
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

    $headers = array(
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
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
      $memberType = trim($entry['member_type']);
      $row['member_type'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($types->types[$memberType]->name) ? $types->types[$memberType]->name : $memberType),
      );
      if (!empty($entry['days'])) {
        $dayDescs = [];
        foreach(explode('|', $entry['days']) as $day) {
          $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
        }
        $memberDays = implode(', ', $dayDescs);
      } else
        $memberDays = '';
      $row['days'] = array(
        '#markup' => SafeMarkup::checkPlain($memberDays),
      );
      $row['price'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['member_total']),
      );
      $row["is_selected"] = array(
        //'#attributes' => array('name' => 'is_approved_'.$mid, 'id' => 'edit_is_approved_'.$mid),
        '#type' => 'checkbox',
        '#title' => t('Select'),
        /*'#title_display' => 'invisible',*/
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
    
    $form['add_members_button'] = array(
      '#type' => 'submit',
      '#value' => t('Add Members'),
      '#attributes' => array('id' => "addBtn"),
      '#validate' => array(),
      '#submit' => array('::addMembers'),
    );

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
  public function buildCashForm($toPay, $config) {
    $symbol = $config->get('payments.symbol');
    $form = [];
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Please confirm cash received from:'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $total_price = 0;
    foreach ($toPay as $mid) {
      if ($member = SimpleConregStorage::load(['mid' => $mid, 'is_paid' => 0])) {
        $form['member'.$mid] = [
          '#type' => 'markup',
          '#markup' => $this->t('Member @first @last to pay @symbol@total',
            ['@first' => $member['first_name'],
             '@last' => $member['last_name'],
             '@symbol' => $symbol,
             '@total' => $member['member_total'],
            ]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
        $total_price += $member['member_total'];
      }
      if ($upgrade = SimpleConregUpgradeStorage::load(['mid' => $mid, 'is_paid' => 0])) {
        $member = SimpleConregStorage::load(['mid' => $mid]);
        $form['member'.$mid] = [
          '#type' => 'markup',
          '#markup' => $this->t('Member @first @last to pay @symbol@total',
            ['@first' => $member['first_name'],
             '@last' => $member['last_name'],
             '@symbol' => $symbol,
             '@total' => $upgrade['upgrade_price'],
            ]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
        $total_price += $upgrade['upgrade_price'];
      }
    }
    $form['payment_method'] = array(
      '#type' => 'select',
      '#title' => $this->t('Payment method'),
      '#options' => SimpleConregOptions::paymentMethod(),
      '#default_value' => "Cash",
      '#required' => TRUE,
    );
    $form['payment_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment reference'),
    );
    $form['total'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Total to pay @symbol@total', ['@symbol' => $symbol, '@total' => $total_price]),
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
  
  public function addMembers(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    // Redirect to payment form.
    $form_state->setRedirect('simple_conreg_fantable_register',
      array('eid' => $eid)
    );
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

  public function saveUpgrades($eid, $form_values, $upgrades, $lead_mid, &$toPay) {
    // Calculate total price of upbrades.
    $upgrade_total = 0;
    foreach ($form_values["table"] as $mid => $member) {
      if (isset($member["member_type"]) && $member["member_type"]) {
        $upgid = $member["member_type"];
        $upgrade_total += $upgrades->upgrades[$upgid]->price;
      }
    }
    
    // Next, check for any member upgrades.
    foreach ($form_values["table"] as $mid => $member) {
      if (isset($member["member_type"]) && $member["member_type"]) {
        if (empty($lead_mid))
          $lead_mid = $mid;
        $upgid = $member["member_type"];
        
        $bad_upgrades = SimpleConregUpgradeStorage::loadAll(['mid' => $mid, 'is_paid' => 0]);
        foreach ($bad_upgrades as $delete) {
          SimpleConregUpgradeStorage::delete($delete);
        }
        
        SimpleConregUpgradeStorage::insert([
          'mid' => $mid,
          'eid' => $eid,
          'lead_mid' => $lead_mid,
          'from_type' => $upgrades->upgrades[$upgid]->fromType,
          'from_days' => $upgrades->upgrades[$upgid]->fromDays,
          'to_type' => $upgrades->upgrades[$upgid]->toType,
          'to_days' => $upgrades->upgrades[$upgid]->toDays,
          'to_badge_type' => $upgrades->upgrades[$upgid]->toBadgeType,
          'upgrade_price' => $upgrades->upgrades[$upgid]->price,
          'is_paid' => 0,
          'payment_amount' => $upgrade_total,
        ]);	
        
        $toPay[$mid] = $mid;
      }
    }
  }

  public function payCash(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $upgrades = SimpleConregOptions::memberUpgrades($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();

    // First check for any unpaid members to be paid.
    $toPay = [];
    foreach ($form_values["unpaid"] as $mid => $member) {
      if (isset($member["is_selected"]) && $member["is_selected"]) {
        $toPay[$mid] = $mid;
      }
    }

    // Save any member upgrades.
    self::saveUpgrades($eid, $form_values, $upgrades, 0, $toPay);

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
    $memberPay = [];
    $upgradePay = [];
    foreach ($toPay as $mid) {
      // Check for member payments.
      if ($member = SimpleConregStorage::load(['mid' => $mid, 'is_paid' => 0])) {
        // Make first member lead member.
        if ($lead_mid == 0)
          $lead_mid = $mid;
        $payment_amount += $member['member_total'];
        $memberPay[] = $mid;
      }
      // Check for upgrade payments.
      if ($upgrade = SimpleConregUpgradeStorage::load(['mid' => $mid, 'is_paid' => 0])) {
        // Make first member lead member.
        if ($lead_mid == 0)
          $lead_mid = $mid;
        $payment_amount += $upgrade['upgrade_price'];
        $upgradePay[$mid] = $upgrade['upgid'];
      }
    }
    // Get next member number.
    $max_member_no = SimpleConregStorage::loadMaxMemberNo($eid);
    // Loop again to update members.
    foreach ($memberPay as $mid) {
      $update = [
        'mid' => $mid,
        'lead_mid' => $lead_mid,
        'payment_amount' => $payment_amount,
        'payment_method' => $form_values['payment_method'],
        'payment_id' => $form_values['payment_id'],
        'is_paid' => 1,
        'member_no' => ++$max_member_no,
        'is_approved' => 1,
      ];
      SimpleConregStorage::update($update);
    }
    // Loop again to update upgrades.
    foreach ($upgradePay as $mid => $upgid) {
      // Fetch update details.
      if ($upgrade = SimpleConregUpgradeStorage::load(['upgid' => $upgid])) {
        $update = [
          'upgid' => $upgid,
          'lead_mid' => $lead_mid,
          'payment_amount' => $payment_amount,
          'payment_method' => $form_values['payment_method'],
          'payment_id' => $form_values['payment_id'],
          'is_paid' => 1,
        ];
        SimpleConregUpgradeStorage::update($update);
        // Fetch member record.
        if ($member = SimpleConregStorage::load(['mid' => $mid])) {
          // Update member type, days and price.
          $member['member_type'] = $upgrade['to_type'];
          $member['days'] = $upgrade['to_days'];
          $member['badge_type'] = $upgrade['to_badge_type'];
          $member['member_price'] += $upgrade['upgrade_price'];
          $member['member_total'] = $member['member_price'] + $member['add_on_price'] + $upgrade['upgrade_price'];
          // Save updated member.
          SimpleConregStorage::update($member);
        }
      }
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
    $uid = \Drupal::currentUser()->id();
    // Loop through members and mark checked in.
    foreach ($toPay as $mid) {
      $update = [
        'mid' => $mid,
        'is_checked_in' => 1,
        'check_in_date' => time(),
        'check_in_by' => $uid,
      ];
      SimpleConregStorage::update($update);
    }
    //$form_state->set('action', 'checkin');
    //$form_state->setRebuild();
    // Form may have checked in member in URL. Redirect to clear.
    $form_state->setRedirect('simple_conreg_admin_checkin', ['eid' => $eid]);
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
      $form_state->setRedirect('simple_conreg_fantable_payment',
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

