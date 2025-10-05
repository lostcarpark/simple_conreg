<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminFanTable
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminFanTable extends FormBase {

  /**
   * Constructs a new EmailExampleGetFormPage.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
    );
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
    $action = $form_state->get('action');
    if (isset($action) && !empty($action)) {
      switch ($action) {
        case "payCash":
          $payid = $form_state->get("payid");
          $payment = SimpleConregPayment::load($payid);
          return $this->buildCashForm($eid, $payment, $config);
          break;
      }
    }

    if (isset($form_values['search']))
      $search = trim($form_values['search']);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $form['summary'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Membership summary'),
    );

    $this->memberAdminMemberListSummaryHorizontal($eid, $form['summary']);

    $form['search'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Search registered members'),
    );

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


    $form['search']['search'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Custom search term'),
      '#default_value' => trim($search ?? ''),
    );

    $form['search']['search_button'] = array(
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

    $form['search']['table'] = array(
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
        $row = array();
        $row['mid'] = array(
          '#markup' => Html::escape($entry['member_no']),
        );
        $row['first_name'] = array(
          '#markup' => Html::escape($entry['first_name']),
        );
        $row['last_name'] = array(
          '#markup' => Html::escape($entry['last_name']),
        );
        $row['email'] = array(
          '#markup' => Html::escape($entry['email']),
        );
        $row['badge_name'] = array(
          '#markup' => Html::escape($entry['badge_name']),
        );
        $row['registered_by'] = array(
          '#markup' => Html::escape($entry['registered_by']),
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
            '#markup' => Html::escape(isset($types->types[$memberType]->name) ? $types->types[$memberType]->name : $memberType),
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
          '#markup' => Html::escape($memberDays),
        );
        $badgeType = trim($entry['badge_type']);
        $row['badge_type'] = array(
          '#markup' => Html::escape(isset($badgeTypes[$badgeType]) ? $badgeTypes[$badgeType] : $badgeType),
        );
        $row['comment'] = array(
          '#markup' => Html::escape(trim(substr($entry['comment'], 0, 20))),
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

        $form['search']['table'][$mid] = $row;
      }
    }

    $form['unpaid'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Members awaiting payment'),
    );

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

    $form['unpaid']['unpaid'] = array(
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
      $row = [];
      $row['first_name'] = array(
        '#markup' => Html::escape($entry['first_name']),
      );
      $row['last_name'] = array(
        '#markup' => Html::escape($entry['last_name']),
      );
      $row['email'] = array(
        '#markup' => Html::escape($entry['email']),
      );
      $row['badge_name'] = array(
        '#markup' => Html::escape($entry['badge_name']),
      );
      $memberType = trim($entry['member_type']);
      $row['member_type'] = array(
        '#markup' => Html::escape(isset($types->types[$memberType]->name) ? $types->types[$memberType]->name : $memberType),
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
        '#markup' => Html::escape($memberDays),
      );
      $row['price'] = array(
        '#markup' => Html::escape($entry['member_total']),
      );
      $row['is_selected'] = array(
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

      $form['unpaid']['unpaid'][$mid] = $row;
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

  /**
   * Add a summary by member type to render array.
   */
  public function memberAdminMemberListSummaryHorizontal($eid, &$content) {
    $types = SimpleConregOptions::memberTypes($eid);
    $headers = [];
    $rows = [];
    $total = 0;
    foreach (SimpleConregStorage::adminMemberSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $headers[] = isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type'];
      $rows[] = ['#markup' => $entry['num']];
      $total += $entry['num'];
    }
    // Add a row for the total.
    $headers[] = $this->t("Total");
    $rows[] = ['#markup' => $total];
    $content['summary'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
      'rows' => $rows,
    ];

    return $content;
  }

  //
  // Set up markup fields to display cash payment.
  //
  public function buildCashForm($eid, SimpleConregPayment $payment, $config) {
    $symbol = $config->get('payments.symbol');
    $form = [];
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Please confirm cash received from:'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $total_price = 0;

    // $mgr = new SimpleConregUpgradeManager($eid);
    // $mgr->loadUpgrades($lead_mid, FALSE);
    // foreach ($mgr->upgrades as $upgrade) {
    //   $member = SimpleConregStorage::load(['mid' => $upgrade->mid]);
    //   $form['member'.$upgrade->mid] = [
    //     '#type' => 'markup',
    //     '#markup' => $this->t('Member @first @last to pay @symbol@total',
    //       ['@first' => $member['first_name'],
    //        '@last' => $member['last_name'],
    //        '@symbol' => $symbol,
    //        '@total' => $upgrade->upgradePrice,
    //       ]),
    //     '#prefix' => '<div>',
    //     '#suffix' => '</div>',
    //   ];
    //   $total_price += $upgrade->upgradePrice;
    // }

    /** @var SimpleConregPaymentLine */
    foreach ($payment->paymentLines as $line) {
      $form['line'.$line->payLineId] = [
        '#type' => 'markup',
        '#markup' => $line->lineDesc,
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
      $total_price += $line->amount;
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

  /**
   * If any member upgrades selected, save them so they can be charged.
   *
   * @param int $eid
   *   The event ID.
   * @param array $form_values
   *   Array of values returned by the form.
   * @param int|float $upgrade_price
   *   Returns the price of upgrades.
   * @param SimpleConregPayment $payment
   *   Payment object.
   */
  public function saveUpgrades($eid, $form_values, &$upgrade_price, SimpleConregPayment &$payment) {
    $mgr = new SimpleConregUpgradeManager($eid);

    if ($form_values["table"]) {
      foreach ($form_values["table"] as $mid => $memberRow) {
        $upgrade = new SimpleConregUpgrade($eid, $mid, $memberRow["member_type"]);
        // Only save upgrade if price is not null.
        if (isset($upgrade->upgradePrice)) {
          $mgr->Add($upgrade);
          $member = SimpleConregStorage::load(['mid' => $mid]);
          $payment->add(new SimpleConregPaymentLine(
            $mid,
            'upgrade',
            t("Upgrade for @first_name @last_name", array('@first_name' => $member['first_name'], '@last_name' => $member['last_name'])),
            $upgrade->upgradePrice,
          ));
        }
      }
    }
    $upgrade_price = $mgr->getTotalPrice();
    $lead_mid = $mgr->saveUpgrades();
    return $lead_mid;
  }

  private function addMembersToPayment($eid, $form_values, SimpleConregPayment &$payment): array {
    $event = SimpleConregEventStorage::load(['eid' => $eid]);
    $config = SimpleConregConfig::getConfig($eid);

    // Check for any unpaid members to add to payment.
    $toPay = [];
    foreach ($form_values['unpaid'] as $mid => $member) {
      if (isset($member['is_selected']) && $member['is_selected']) {
        $member = Member::loadMember($mid);
        $payment->add(new SimpleConregPaymentLine(
          $mid,
          'member',
          $this->t("Member registration to @event_name for @first_name @last_name",
          [
            '@event_name' => $event['event_name'],
            '@first_name' => $member->first_name,
            '@last_name' => $member->last_name,
          ]),
          $member->member_total,
        ));
        $addons = SimpleConregAddons::getMemberAddons($config, $mid);
        foreach ($addons as $addon) {
          // Add a payment line for the add-on.
          $payment->add(new SimpleConregPaymentLine(
            $mid,
            'addon',
            t("Add-on @add_on for @first_name @last_name",
            [
              '@add_on' => $addon->name,
              '@first_name' => $member->first_name,
              '@last_name' => $member->last_name,
            ]),
            $addon->amount,
          ));
        }
        $toPay[$mid] = $mid;
      }
    }
    return $toPay;
  }

  // "Pay Cash" button on main form clicked.
  public function payCash(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $form_values = $form_state->getValues();

    $payment = new SimpleConregPayment();
    // Save any member upgrades.
    $lead_mid = $this->saveUpgrades($eid, $form_values, $upgrade_price, $payment);
    $toPay = $this->addMembersToPayment($eid, $form_values, $payment);

    // No need to proceed unless members have been selected.
    if (!empty($lead_mid) || count($toPay)) {
      $payid = $payment->save();
      $form_state->set('action', 'payCash');
      $form_state->set('topay', $toPay);
      $form_state->set('payid', $payid);
      $form_state->set('leadmid', $lead_mid);
    }
    $form_state->setRebuild();
  }

  // "Confirm" button on Pay Cash subform clicked.
  public function confirmPayCash(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $payid = $form_state->get('payid');
    $form_values = $form_state->getValues();

    // Load the payment.
    $payment = SimpleConregPayment::load($payid);
    if (!is_null($payment)) {
      $payment->paidDate = time();
      $payment->paymentMethod = $form_values['payment_method'];
      $payment->paymentRef = $form_values['payment_id'];
      $payment->save();

      SimpleConregAddons::markPaid($payment->getId(), $form_values['payment_id']);

      // Process the payment lines.
      foreach ($payment->paymentLines as $line) {
        switch ($line->type) {
          case "member":
            $member = Member::loadMember($line->mid);
            if (is_object($member) && !$member->is_paid && !$member->is_deleted) {
              $member->is_paid = 1;
              $member->payment_id = $form_values['payment_id'];
              $member->payment_method = 'Stripe';
              $member->saveMember();

              // If email address populated, send confirmation email.
              if (!empty($member->email)) {
                $this->sendConfirmationEmail((array)$member);
              }
            }
        }
      }
    }

    $payment_amount = 0;
    $lead_mid = $form_state->get('leadmid');
    $toPay = $form_state->get('topay');

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
    }

    // Load upgrades into upgrade manager and process.
    $mgr = new SimpleConregUpgradeManager($eid);
    $mgr->loadUpgrades($lead_mid, FALSE);
    $payment_amount += $mgr->getTotalPrice(); // Add total price of upgrades to total price of new members.
    $mgr->completeUpgrades($payment_amount, $form_values['payment_method'], $form_values['payment_id']);

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
    }
    $form_state->setRedirect('simple_conreg_admin_fantable', ['eid' => $eid]);
  }


  public function payCard(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $form_values = $form_state->getValues();

    $payment = new SimpleConregPayment();
    // Save any member upgrades.
    $lead_mid = $this->saveUpgrades($eid, $form_values, $upgrade_price, $payment);

    $toPay = $this->addMembersToPayment($eid, $form_values, $payment);

    // No need to proceed unless members have been selected.
    if (!empty($lead_mid) || count($toPay)) {
      $payid = $payment->save();
      // Redirect to payment form.
      $form_state->setRedirect(
        'simple_conreg_fantable_checkout',
        ['payid' => $payid, 'key' => $payment->randomKey],
      );
    }
  }

  public function cancelAction(array &$form, FormStateInterface $form_state) {
    $form_state->set('action', '');
    $form_state->setRebuild();
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  private function sendConfirmationEmail($member) {
    $config = $this->config('simple_conreg.settings.' . $member['eid']);
    $types = SimpleConregOptions::memberTypes($member['eid'], $config);

    // Set up parameters for receipt email.
    $params = ['eid' => $member['eid'], 'mid' => $member['mid']];
    if ($types->types[$member['member_type']]->confirmation->override ?: false) {
      $params['subject'] = $types->types[$member['member_type']]->confirmation->template_subject;
      $params['body'] = $types->types[$member['member_type']]->confirmation->template_body;
      $params['body_format'] = $types->types[$member['member_type']]->confirmation->template_format;
    } else {
      $params['subject'] = $config->get('confirmation.template_subject');
      $params['body'] = $config->get('confirmation.template_body');
      $params['body_format'] = $config->get('confirmation.template_format');
    }
    $params['include_private'] = true;
    $module = "simple_conreg";
    $key = "template";
    $to = $member["email"];
    $language_code = $this->languageManager->getDefaultLanguage()->getId();
    // Send confirmation email to member.
    if (!empty($member["email"])) {
      $this->mailManager->mail($module, $key, $to, $language_code, $params);
    }

    // If copy_us checkbox checked, send a copy to us.
    if ($config->get('confirmation.copy_us')) {
      $params['subject'] = $config->get('confirmation.notification_subject');
      $params['include_private'] = false;
      $to = $config->get('confirmation.from_email');
      $this->mailManager->mail($module, $key, $to, $language_code, $params);
    }

    // If copy email to field provided, send an extra copy to us.
    if (!empty($config->get('confirmation.copy_email_to'))) {
      $params['subject'] = $config->get('confirmation.notification_subject');
      $params['include_private'] = false;
      $to = $config->get('confirmation.copy_email_to');
      $this->mailManager->mail($module, $key, $to, $language_code, $params);
    }
  }

}
