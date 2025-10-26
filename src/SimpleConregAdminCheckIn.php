<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminCheckIn extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructor for member lookup form.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The database connection.
   */
  public function __construct(AccountProxyInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('current_user')
    );
  }

  /**
   * Add a summary by check-in status to render array.
   */
  public function checkInSummary($eid, &$content) {
    $descriptions = [
      0 => 'Not Checked In',
      1 => 'Checked In',
    ];
    $rows = [];
    $headers = [
      $this->t('Status'),
      $this->t('Number of members'),
    ];
    $total = 0;
    foreach (SimpleConregStorage::adminMemberCheckInSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $status = trim($entry['is_checked_in']);
      if (isset($descriptions[$status])) {
        $entry['is_checked_in'] = $descriptions[$status];
      }
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
      $total += $entry['num'];
    }
    // Add a row for the total.
    $rows[] = [$this->t("Total"), $total];
    $content['check_in_summary'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
    ];

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_admin_members';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $lead_mid = 0) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);
    $event = SimpleConregEventStorage::load(['eid' => $eid]);

    // Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.' . $eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);

    // If lead_mid passed in, form is retruning from credit cart payment. Set up
    // for check in of paid member(s).
    if ($lead_mid) {
      $result = SimpleConregStorage::loadAll([
        'eid' => $eid,
        'lead_mid' => $lead_mid,
        'is_paid' => 1,
        'is_deleted' => 0,
      ]);
      $toPay = [];
      foreach ($result as $member) {
        $toPay[] = $member['mid'];
      }
      $form_state->set("action", 'checkIn');
      $form_state->set("topay", $toPay);
    }

    // If action set, display either payment or check-in subpage.
    $action = $form_state->get("action");
    if (isset($action) && !empty($action)) {
      switch ($action) {
        case "payCash":
          $toPay = $form_state->get("topay");
          return $this->buildCashForm($toPay, $config);

        case "checkIn":
          $toPay = $form_state->get("topay");
          return $this->buildConfirmForm($eid, $toPay);
      }
    }

    $search = trim($form_values['search'] ?? '');

    $form = [
      '#title' => $this->t('Member Checkin for %event_name', ['%event_name' => $event['event_name']]),
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    ];

    $this->checkInSummary($eid, $form);

    $headers = [
      'badge_no' => [
        'data' => $this->t('Member no'),
        'field' => 'm.member_no',
        'sort' => 'asc',
      ],
      'first_name' => [
        'data' => $this->t('First name'),
        'field' => 'm.first_name',
      ],
      'last_name' => [
        'data' => $this->t('Last name'),
        'field' => 'm.last_name',
      ],
      'email' => [
        'data' => $this->t('Email'),
        'field' => 'm.email',
      ],
      'badge_name' => [
        'data' => $this->t('Badge name'),
        'field' => 'm.badge_name',
      ],
      'registered_by' => ['data' => $this->t('Registered By')],
      'member_type' => ['data' => $this->t('Member type')],
      'days' => ['data' => $this->t('Days')],
      'badge_type' => ['data' => $this->t('Badge type')],
      'comment' => ['data' => $this->t('Comment')],
      'is_paid' => $this->t('Paid'),
      'select' => $this->t('Select'),
      /*t('Action'),*/
    ];

    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom search term'),
      '#default_value' => $search,
    ];

    $form['search_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#attributes' => ['id' => "searchBtn"],
      '#validate' => [],
      '#submit' => ['::search'],
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => [$this, 'updateDisplayCallback'],
      ],
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => ['id' => 'simple-conreg-admin-member-list'],
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    // Only check database if search filled in.
    if (!empty($search)) {
      $entries = SimpleConregStorage::adminMemberCheckInListLoad($eid, $search);

      foreach ($entries as $entry) {
        $mid = $entry['mid'];
        // Sanitize each entry.
        $is_paid = $entry['is_paid'];
        $row = [];
        $row['badge_no'] = [
          '#markup' => Html::escape($this->showBadgeNumber($entry, $config)),
        ];
        $row['first_name'] = [
          '#markup' => Html::escape($entry['first_name']),
        ];
        $row['last_name'] = [
          '#markup' => Html::escape($entry['last_name']),
        ];
        $row['email'] = [
          '#markup' => Html::escape($entry['email']),
        ];
        $row['badge_name'] = [
          '#markup' => Html::escape($entry['badge_name']),
        ];
        $row['registered_by'] = [
          '#markup' => Html::escape($entry['registered_by']),
        ];
        $memberType = trim($entry['member_type']);
        $row['member_type'] = [
          '#markup' => Html::escape($types->types[$memberType]->name ?? $memberType),
        ];
        if (!empty($entry['days'])) {
          $dayDescs = [];
          foreach (explode('|', $entry['days']) as $day) {
            $dayDescs[] = $days[$day] ?? $day;
          }
          $memberDays = implode(', ', $dayDescs);
        }
        else {
          $memberDays = '';
        }
        $row['days'] = [
          '#markup' => Html::escape($memberDays),
        ];
        $badgeType = trim($entry['badge_type']);
        $row['badge_type'] = [
          '#markup' => Html::escape($badgeTypes[$badgeType] ?? $badgeType),
        ];
        $row['comment'] = [
          '#markup' => Html::escape(trim(substr($entry['comment'], 0, 20))),
        ];
        $row['is_paid'] = [
          '#markup' => $is_paid ? $this->t('Yes') : $this->t('No'),
        ];
        if ($entry['is_checked_in']) {
          $row["is_checked_in"] = [
            '#markup' => $this->t('Checked in'),
          ];
        }
        else {
          $row["is_checked_in"] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Select'),
            '#default_value' => $entry['is_checked_in'],
          ];
        }

        $form['table'][$mid] = $row;
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check-in Selected'),
      '#submit' => [[$this, 'checkInSubmit']],
      '#attributes' => ['id' => "submitBtn"],
    ];

    $headers = [
      'first_name' => [
        'data' => $this->t('First name'),
        'field' => 'm.first_name',
      ],
      'last_name' => [
        'data' => $this->t('Last name'),
        'field' => 'm.last_name',
      ],
      'email' => [
        'data' => $this->t('Email'),
        'field' => 'm.email',
      ],
      'badge_name' => [
        'data' => $this->t('Badge name'),
        'field' => 'm.badge_name',
      ],
      'member_type' => ['data' => $this->t('Member type')],
      'days' => ['data' => $this->t('Days')],
      'price' => ['data' => $this->t('Price'), 'field' => 'm.member_total'],
      'select' => $this->t('Select'),
      /*t('Action'),*/
    ];

    $form['unpaid'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => ['id' => 'simple-conreg-admin-member-list'],
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    $entries = SimpleConregStorage::adminMemberUnpaidListLoad($eid);

    foreach ($entries as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $is_paid = $entry['is_paid'];
      $row = [];
      $row['first_name'] = [
        '#markup' => Html::escape($entry['first_name']),
      ];
      $row['last_name'] = [
        '#markup' => Html::escape($entry['last_name']),
      ];
      $row['email'] = [
        '#markup' => Html::escape($entry['email']),
      ];
      $row['badge_name'] = [
        '#markup' => Html::escape($entry['badge_name']),
      ];
      $memberType = trim($entry['member_type']);
      $row['member_type'] = [
        '#markup' => Html::escape($types->types[$memberType]->name ?? $memberType),
      ];
      if (!empty($entry['days'])) {
        $dayDescs = [];
        foreach (explode('|', $entry['days']) as $day) {
          $dayDescs[] = $days[$day] ?? $day;
        }
        $memberDays = implode(', ', $dayDescs);
      }
      else {
        $memberDays = '';
      }
      $row['days'] = [
        '#markup' => Html::escape($memberDays),
      ];
      $row['price'] = [
        '#markup' => Html::escape($entry['member_total']),
      ];
      $row["is_selected"] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Select'),
        '#default_value' => 0,
      ];

      $form['unpaid'][$mid] = $row;
    }

    // Extra table row with blank form for new member.
    $row = [];
    $row["first_name"] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row["last_name"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row["email"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row["badge_name"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Badge Name'),
      '#title_display' => 'invisible',
      '#size' => 15,
    ];
    $row['memberType'] = [
      '#type' => 'select',
      '#title' => $this->t('Member Type'),
      '#options' => $types->publicNames,
      '#title_display' => 'invisible',
    ];
    $row['days'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Days'),
      '#options' => $days,
      '#title_display' => 'invisible',
    ];
    $row['price'] = [];
    $row['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#submit' => [[$this, 'addMember']],
    ];
    $form['unpaid']['add'] = $row;

    $form['cash'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pay Cash'),
      '#submit' => [[$this, 'payCash']],
    ];

    $form['card'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pay Credit Card'),
      '#submit' => [[$this, 'payCard']],
    ];

    return $form;
  }

  /**
   * Set up markup fields to display cash payment.
   */
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
      if ($member = SimpleConregStorage::load(['mid' => $mid])) {
        $form['member' . $mid] = [
          '#type' => 'markup',
          '#markup' => $this->t('Member @first @last to pay @symbol@total',
          [
            '@first' => $member['first_name'],
            '@last' => $member['last_name'],
            '@symbol' => $symbol,
            '@total' => $member['member_total'],
          ]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
        $total_price += $member['member_total'];
      }
    }
    $form['payment_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment method'),
      '#options' => SimpleConregOptions::paymentMethod(),
      '#default_value' => "Cash",
      '#required' => TRUE,
    ];
    $form['payment_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Payment reference'),
    ];
    $form['total'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Total to pay @symbol@total', [
        '@symbol' => $symbol,
        '@total' => $total_price,
      ]),
      '#prefix' => '<div><h4>',
      '#suffix' => '</h4></div>',
    ];
    $form['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm Cash Payment'),
      '#submit' => [[$this, 'confirmPayCash']],
    ];
    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelAction']],
    ];
    return $form;
  }

  /**
   * Set up markup fields to display check-in confirm.
   */
  public function buildConfirmForm($eid, $toPay) {
    $config = $this->config('simple_conreg.settings.' . $eid);
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
        if (!(isset($member['is_confirmed']) && $member['is_approved'])) {
          $update['is_approved'] = 1;
        }
        if (isset($member['member_no']) && $member['member_no']) {
          $member_no = $member['member_no'];
        }
        else {
          $member_no = ++$maxMemberNo;
          // Add member number to loaded member entry.
          $member['member_no'] = $member_no;
          // Add to update record so it will be saved.
          $update['member_no'] = $member_no;
        }
        SimpleConregStorage::update($update);
        $form['member' . $mid] = [
          '#type' => 'markup',
          '#markup' => $this->t('Badge number @memberno for @first @last',
          [
            '@memberno' => $this->showBadgeNumber($member, $config),
            '@first' => $member['first_name'],
            '@last' => $member['last_name'],
          ]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
      }
    }
    $form['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm Check-In'),
      '#submit' => [[$this, 'confirmCheckInSubmit']],
    ];
    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelAction']],
    ];
    return $form;
  }

  /**
   * Callback function for "display" drop down.
   */
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    // Return new form.
    return $form;
  }

  /**
   * Callback for search.
   */
  public function search(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Callback to add a member.
   */
  public function addMember(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();
    // Assign random key for payment URL.
    $rand_key = mt_rand();
    if (!empty($form_values['unpaid']['add']['badge_name'])) {
      $badge_name = trim($form_values['unpaid']['add']['badge_name']);
    }
    else {
      $badge_name = trim($form_values['unpaid']['add']['first_name'] . ' ' . $form_values['unpaid']['add']['last_name']);
    }
    // Work out price.
    $memberType = $form_values['unpaid']['add']['memberType'];
    $price = $types->types[$memberType]->price;
    $daysPrice = 0;
    $memberDays = '';
    $daysSel = [];
    $daysDescs = [];
    foreach ($form_values['unpaid']['add']['days'] as $key => $val) {
      if (!empty($val) && isset($types->types[$memberType]->days[$key])) {
        $daysPrice += $types->types[$memberType]->days[$key]->price;
        $daysSel[] = $key;
        $daysDescs[] = $days[$key];
      }
    }

    if ($daysPrice > 0 and $daysPrice < $price) {
      $price = $daysPrice;
      $memberDays = implode('|', $daysSel);
    }
    // Save the submitted entry.
    $entry = [
      'eid' => $eid,
      'lead_mid' => 0,
      'random_key' => $rand_key,
      'member_type' => $memberType,
      'days' => $memberDays,
      'first_name' => $form_values['unpaid']['add']['first_name'],
      'last_name' => $form_values['unpaid']['add']['last_name'],
      'badge_name' => $badge_name,
      'badge_type' => 'A',
      'display' => $config->get('checkin.display'),
      'communication_method' => $config->get('checkin.communication_method'),
      'email' => $form_values['unpaid']['add']['email'],
      'member_price' => $price,
      'member_total' => $price,
      'add_on_price' => 0,
      'payment_amount' => $price,
      'join_date' => time(),
      'update_date' => time(),
    ];
    // Insert to database table.
    $return = SimpleConregStorage::insert($entry);

    if ($return) {
      // Update member with own member ID as lead member ID.
      $update = ['mid' => $return, 'lead_mid' => $return];
      $return = SimpleConregStorage::update($update);
      // Clear form fields.
      $form_state->setUserInput([]);
    }
    $form_state->setRebuild();
  }

  /**
   * Callback for submit button.
   */
  public function checkInSubmit(array &$form, FormStateInterface $form_state) {
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

  /**
   * Callback for pay cash button.
   */
  public function payCash(array &$form, FormStateInterface $form_state) {
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

  /**
   * Confirm button callback.
   */
  public function confirmPayCash(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    $payment_amount = 0;
    $lead_mid = 0;
    $toPay = $form_state->get("topay");
    // Loop through selected members to get lead and total price.
    foreach ($toPay as $mid) {
      if ($member = SimpleConregStorage::load(['mid' => $mid])) {
        // Make first member lead member.
        if ($lead_mid == 0) {
          $lead_mid = $mid;
        }
        $payment_amount += $member['member_total'];
      }
    }
    // Loop again to update members.
    foreach ($toPay as $mid) {
      $update = [
        'mid' => $mid,
        'lead_mid' => $lead_mid,
        'payment_amount' => $payment_amount,
        'payment_method' => $form_values['payment_method'],
        'payment_id' => $form_values['payment_id'],
        'is_paid' => 1,
      ];
      SimpleConregStorage::update($update);
    }
    $form_state->set('action', 'checkIn');
    $form_state->setRebuild();
  }

  /**
   * Callback for check in button.
   */
  public function confirmCheckInSubmit(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get("eid");
    $config = $this->config('simple_conreg.settings.' . $eid);
    $toPay = $form_state->get("topay");
    $uid = $this->currentUser->id();
    // Loop through members and mark checked in.
    foreach ($toPay as $mid) {
      $update = [
        'mid' => $mid,
        'is_checked_in' => 1,
        'check_in_date' => time(),
        'check_in_by' => $uid,
      ];
      SimpleConregStorage::update($update);
      if ($member = SimpleConregStorage::load(['mid' => $mid])) {
        $this->messenger()->addMessage($this->t("Member %badge_no - %badge_name checked in.", [
          '%badge_no' => $this->showBadgeNumber($member, $config),
          '%badge_name' => $member['badge_name'],
        ]));
      }
    }
    // Form may have checked in member in URL. Redirect to clear.
    $form_state->setRedirect('simple_conreg_admin_checkin', ['eid' => $eid]);
  }

  /**
   * Callback function for pay by card.
   */
  public function payCard(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    // Create a payment object.jumpgate.
    $payment = new SimpleConregPayment();

    $payment_amount = 0;
    $lead_mid = 0;
    // Loop through selected members to get lead and total price.
    foreach ($form_values["unpaid"] as $mid => $member) {
      if (isset($member["is_selected"]) && $member["is_selected"]) {
        if ($member = SimpleConregStorage::load(['mid' => $mid])) {
          // Add member to payment.
          $payment->add(new SimpleConregPaymentLine($mid,
            'member',
            $this->t("Member registration for @first_name @last_name",
          [
            '@first_name' => $member['first_name'],
            '@last_name' => $member['last_name'],
          ]),
            $member['member_price']));
          // Make first member lead member.
          if ($lead_mid == 0) {
            $lead_mid = $mid;
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
    if ($lead_mid) {
      // Save the payment.
      $payid = $payment->save();
    }
    // Redirect to payment form.
    $form_state->setRedirect('simple_conreg_checkin_checkout',
      ['payid' => $payid, 'key' => $payment->randomKey]
    );
  }

  /**
   * Callback for cancel button.
   */
  public function cancelAction(array &$form, FormStateInterface $form_state) {
    $form_state->set('action', '');
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $form_values = $form_state->getValues();
    $saved_members = SimpleConregStorage::loadAllMemberNos($eid);
    $uid = $this->currentUser->id();
    foreach ($form_values["table"] as $mid => $member) {
      if ($member["is_checked_in"] != $saved_members[$mid]["is_checked_in"]) {
        if ($member["is_checked_in"]) {
          $entry = [
            'mid' => $mid,
            'is_checked_in' => $member["is_checked_in"],
            'check_in_date' => time(),
            'check_in_by' => $uid,
          ];
        }
        else {
          $entry = ['mid' => $mid, 'is_checked_in' => $member["is_checked_in"]];
        }
        SimpleConregStorage::update($entry);
      }
    }
    Cache::invalidateTags(['simple-conreg-member-list']);
  }

  protected function showBadgeNumber(array $member, ImmutableConfig $config): string {
    if (!$member['member_no']) {
      return '';
    }
    $digits = $config->get('member_no_digits');
    $badge_type = trim($member['badge_type']);
    $member_no = sprintf("%0" . $digits . "d", $member['member_no']);
    return $badge_type . $member_no;
  }

}
