<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Simple Convention Registration.
 */
class SimpleConregController extends ControllerBase {

  /**
   * The HTTP request
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructor for member lookup form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   */
  public function __construct(Request $request) {
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Display simple thank you page.
   */
  public function registrationThanks($eid = 1) {
    $config = $this->config('simple_conreg.settings.' . $eid);

    $content = [
      '#title' => $config->get('thanks.title'),
    ];

    $content['message'] = [
      '#markup' => $config->get('thanks.thank_you_message'),
    ];

    return $content;
  }

  /**
   * Render a list of entries in the database.
   */
  public function memberList($eid = 1) {
    $config = $this->config('simple_conreg.settings.' . $eid);
    $countryOptions = SimpleConregOptions::memberCountries($eid, $config);
    $types = SimpleConregOptions::badgeTypes($eid, $config);
    $digits = $config->get('member_no_digits');

    $showMemberList = $config->get('member_listing_page.show_members') ?? TRUE;
    $showCountries = $config->get('member_listing_page.show_countries') ?? TRUE;
    $showSummary = $config->get('member_listing_page.show_summary') ?? TRUE;

    switch ($this->request->query->get('sort') ?? '') {
      case 'desc':
        $direction = 'DESC';
        break;

      default:
        $direction = 'ASC';
        break;
    }
    switch ($this->request->query->get('order') ?? '') {
      case 'Name':
        $order = 'name';
        break;

      case 'Country':
        $order = 'country';
        break;

      case 'Type':
        $order = 'badge_type';
        break;

      default:
        $order = 'member_no';
        break;
    }

    $content = [
      '#cache' => [
        'tags' => ['event:' . $eid . ':members'],
        'contexts' => ['url.query_args:sort', 'url.query_args:order'],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    // If public member list disabled, return message.
    if (!$showMemberList) {
      $content['message'] = [
        '#markup' => $this->t("Public member list is not available."),
      ];
      return $content;
    }

    $content['message'] = [
      '#cache' => ['tags' => ['simple-conreg-member-list'], '#max-age' => 600],
      '#markup' => $this->t("Members' public details are listed below."),
    ];

    $rows = [];
    $headers = [
      'member_no' => [
        'data' => $this->t('Member No'),
        'field' => 'm.member_no',
        'sort' => 'asc',
      ],
      'member_name' => [
        'data' => $this->t('Name'),
        'field' => 'name',
      ],
      'badge_type' => [
        'data' => $this->t('Type'),
        'field' => 'm.badge_type',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    if ($showCountries) {
      $headers['member_country'] = [
        'data' => $this->t('Country'),
        'field' => 'm.country',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ];
    }
    $total = 0;

    foreach (SimpleConregStorage::adminPublicListLoad($eid) as $entry) {
      // Sanitize each entry.
      $badge_type = trim($entry['badge_type']);
      $member_no = sprintf("%0" . $digits . "d", $entry['member_no']);
      $member = ['member_no' => $badge_type . $member_no];
      switch ($entry['display']) {
        case 'F':
          $fullname = trim(trim($entry['first_name']) . ' ' . trim($entry['last_name']));
          if ($fullname != trim($entry['badge_name'])) {
            $fullname .= ' (' . trim($entry['badge_name']) . ')';
          }
          $member['name'] = $fullname;
          break;

        case 'B':
          $member['name'] = trim($entry['badge_name']);
          break;

        case 'N':
          $member['name'] = $this->t('Name withheld');
          break;
      }
      $member['badge_type'] = trim($types[$badge_type] ?? $badge_type);
      if ($showCountries) {
        $member['country'] = trim($countryOptions[$entry['country']] ?? $entry['country']);
      }

      // Set key to field to be sorted by.
      if ($order == 'member_no') {
        $key = $member_no;
      }
      // Append member number to ensure uniqueness.
      else {
        $key = $member[$order] . $member_no;
      }
      if (!empty($entry['display']) && $entry['display'] != 'N' && !empty($entry['country'])) {
        $rows[$key] = $member;
      }
      $total++;
    }

    // Sort array by key.
    if ($direction == 'DESC') {
      krsort($rows);
    }
    else {
      ksort($rows);
    }

    $content['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      // '#footer' => array(t("Total")),
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
    ];

    // Member summary page.
    if ($showSummary) {
      $content['summary_heading'] = [
        '#markup' => $this->t('Country Breakdown'),
        '#prefix' => '<h2>',
        '#suffix' => '</h2>',
      ];

      $rows = [];
      $headers = [
        $this->t('Country'),
        $this->t('Number of members'),
      ];
      $total = 0;
      foreach (SimpleConregStorage::adminMemberCountrySummaryLoad($eid) as $entry) {
        if (!empty($entry['country'])) {
          // Sanitize each entry.
          $entry['country'] = trim($countryOptions[$entry['country']]);
          $rows[] = $entry;
          $total += $entry['num'];
        }
      }
      // Add a row for the total.
      $rows[] = [$this->t("Total"), $total];
      $content['summary'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#empty' => $this->t('No entries available.'),
      ];
    }

    return $content;
  }

  /**
   * Add a summary by member type to render array.
   */
  public function memberAdminMemberListSummary($eid, &$content) {
    $types = SimpleConregOptions::memberTypes($eid);
    $headers = [
      $this->t('Member Type'),
      $this->t('Number of members'),
    ];
    $content['summary'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    $total = 0;
    foreach (SimpleConregStorage::adminMemberSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $content['summary'][] = [
        ['#markup' => isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type']],
        ['#markup' => $entry['num']],
      ];
      $total += $entry['num'];
    }
    // Add a row for the total.
    $content['summary']['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];

    return $content;
  }

  /**
   * Add a summary by payment method to render array.
   */
  public function memberAdminMemberListBadgeSummary($eid, &$content) {
    $types = SimpleConregOptions::badgeTypes($eid);
    $headers = [
      $this->t('Badge Type'),
      $this->t('Number of members'),
    ];
    $content['badge_summary'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    $total = 0;
    foreach (SimpleConregStorage::adminMemberBadgeSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $content['badge_summary'][] = [
        ['#markup' => $types[trim($entry['badge_type'])] ?? $entry['badge_type']],
        ['#markup' => $entry['num']],
      ];
      $total += $entry['num'];
    }
    // Add a row for the total.
    $content['badge_summary']['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];

    return $content;
  }

  /**
   * Add a summary by payment method to render array.
   */
  public function memberAdminMemberListDaysSummary($eid, &$content) {
    $days = SimpleConregOptions::days($eid);

    $dayTotals = [];
    foreach ($days as $key => $val) {
      $dayTotals[$key] = 0;
    }
    $total = 0;
    foreach (SimpleConregStorage::adminMemberDaysSummaryLoad($eid) as $entry) {
      // Sanitize each entry.
      foreach (explode('|', $entry['days']) as $day) {
        $dayTotals[$day] += $entry['num'];
      }
      $total += $entry['num'];
    }

    $headers = [
      $this->t('Days'),
      $this->t('Number of members'),
    ];
    $content['days_summary'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    foreach ($dayTotals as $key => $val) {
      // Sanitize each entry.
      $content['days_summary'][] = [
        ['#markup' => $days[$key] ?? $key],
        ['#markup' => $val],
      ];
    }
    // Add a row for the total.
    $content['days_summary']['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];

    return $content;
  }

  /**
   * Add a summary by badge type to render array.
   */
  public function memberAdminMemberListPaymentMethodSummary($eid, &$content) {
    $headers = [
      $this->t('Payment Method'),
      $this->t('Number of members'),
    ];
    // Set up table.
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    $total = 0;
    foreach (SimpleConregStorage::adminMemberPaymentMethodSummaryLoad($eid) as $entry) {
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => $entry['payment_method']],
        ['#markup' => $entry['num']],
      ];
      $total += $entry['num'];
    }
    // Add a row for the total.
    $rows['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];
    $content['payment_method_summary'] = $rows;

    return $content;
  }

  /**
   * Add a summary by badge type to render array.
   */
  public function memberAdminMemberListAmountPaidSummary($eid, &$content) {
    $headers = [
      $this->t('Amount Paid'),
      $this->t('Number of members'),
      $this->t('Total Paid'),
    ];
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    $total = 0;
    $total_amount = 0;
    foreach (SimpleConregStorage::adminMemberAmountPaidSummaryLoad($eid) as $entry) {
      // Calculate total received at that rate.
      $total_paid = $entry['member_price'] * $entry['num'];
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => $entry['member_price']],
        ['#markup' => $entry['num']],
        ['#markup' => number_format($total_paid, 2)],
      ];
      $total += $entry['num'];
      $total_amount += $total_paid;
    }
    // Add a row for the total.
    $rows['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => number_format($total_amount, 2),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];
    $content['amount_paid_summary'] = $rows;

    return $content;
  }

  /**
   * Add a summary by member type and amount paid to render array.
   */
  public function memberAdminMemberListAmountPaidByTypeSummary($eid, &$content) {
    $types = SimpleConregOptions::memberTypes($eid);
    $headers = [
      $this->t('Member Type'),
      $this->t('Amount Paid'),
      $this->t('Number of members'),
      $this->t('Total Paid'),
    ];
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    $total = 0;
    $total_amount = 0;
    foreach (SimpleConregStorage::adminMemberAmountPaidByTypeSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      if (isset($types->types[$entry['member_type']])) {
        $entry['member_type'] = (isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type']);
      }
      // Calculate total received at that rate.
      $total_paid = $entry['member_price'] * $entry['num'];
      $entry['total_paid'] = number_format($total_paid, 2);
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type']],
        ['#markup' => $entry['member_price']],
        ['#markup' => $entry['num']],
        ['#markup' => number_format($total_paid, 2)],
      ];

      // Add to totals.
      $total += $entry['num'];
      $total_amount += $total_paid;
    }
    // Add a row for the total.
    $rows['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => '',
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => number_format($total_amount, 2),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];
    $content['type_amount_paid_summary'] = $rows;

    return $content;
  }

  /**
   * Add a summary by date joined to render array.
   */
  public function memberAdminMemberListByDateSummary($eid, &$content) {
    $months = DateHelper::monthNames();
    $headers = [
      $this->t('Year'),
      $this->t('Month'),
      $this->t('Number of members'),
      $this->t('Total Paid'),
      $this->t('Cumulative members'),
      $this->t('Cumulative Total Paid'),
    ];
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No entries available.'),
    ];
    $total = 0;
    $total_amount = 0;
    foreach (SimpleConregStorage::adminMemberByDateSummaryLoad($eid) as $entry) {
      // Convert month to name.
      $entry['month'] = $months[$entry['month']];
      $total += $entry['num'];
      $total_amount += $entry['total_paid'];
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => $entry['year']],
        ['#markup' => $entry['month']],
        ['#markup' => $entry['num']],
        ['#markup' => number_format($entry['total_paid'], 2)],
        ['#markup' => $total],
        ['#markup' => number_format($total_amount, 2)],
      ];
    }
    // Add a row for the total.
    $rows['total'] = [
      [
        '#markup' => $this->t("Total"),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => '',
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => number_format($total_amount, 2),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => $total,
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
      [
        '#markup' => number_format($total_amount, 2),
        '#wrapper_attributes' => ['class' => ['table-total']],
      ],
    ];
    $content['by_date_summary'] = $rows;

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminMemberList($eid) {
    $config = SimpleConregConfig::getConfig($eid);
    $countryOptions = SimpleConregOptions::memberCountries($eid, $config);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $communicationsOptions = SimpleConregOptions::communicationMethod($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $yesNo = SimpleConregOptions::yesNo();
    $digits = $config->get('member_no_digits');

    $content = [
      '#cache' => [
        'tags' => ['event:' . $eid . ':members'],
        'contexts' => ['url.query_args:sort', 'url.query_args:order'],
        'max-age' => Cache::PERMANENT,
      ],
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
    ];

    $pageOptions = [];
    switch ($this->request->query->get('sort') ?? '') {
      case 'desc':
        $direction = 'DESC';
        $pageOptions['sort'] = 'desc';
        break;

      default:
        $direction = 'ASC';
        break;
    }
    switch ($this->request->query->get('order') ?? '') {
      case 'MID':
        $order = 'm.mid';
        $pageOptions['order'] = 'MID';
        break;

      case 'First name':
        $order = 'm.first_name';
        $pageOptions['order'] = 'First name';
        break;

      case 'Last name':
        $order = 'm.last_name';
        $pageOptions['order'] = 'Last name';
        break;

      case 'Badge name':
        $order = 'm.badge_name';
        $pageOptions['order'] = 'Badge name';
        break;

      case 'Email':
        $order = 'm.email';
        $pageOptions['order'] = 'Email';
        break;

      default:
        $order = 'member_no';
        break;
    }

    $content['message'] = [
      '#markup' => $this->t('Here is a list of all paid convention members.'),
    ];

    $content['copy'] = [
      '#type' => 'button',
      '#value' => $this->t('Copy to clipboard'),
      '#attributes' => ['class' => ['table-copy']]
    ];

    $this->memberAdminMemberListSummary($eid, $content);

    $rows = [];
    $headers = [
      'member_type' => [
        'data' => $this->t('Member type'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'days' => [
        'data' => $this->t('Days'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'member_no' => [
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
      'badge_type' => [
        'data' => $this->t('Badge type'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'street' => $this->t('Street'),
      'street2' => $this->t('Street line 2'),
      'city' => $this->t('City'),
      'county' => $this->t('County'),
      'postcode' => $this->t('Postcode'),
      'country' => $this->t('Country'),
      'phone' => $this->t('Phone'),
      'dob' => $this->t('Birth Date'),
      'age' => $this->t('Age'),
      'display' => [
        'data' => $this->t('Display'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'comm_method' => $this->t('Communication Method'),
      'paid' => $this->t('Paid'),
      'price' => $this->t('Price'),
      'comments' => $this->t('Comments'),
      'approved' => $this->t('Approved'),
      'mid' => [
        'data' => $this->t('Internal ID'),
        'field' => 'm.mid',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'joined' => $this->t('Date joined'),
    ];

    foreach (SimpleConregStorage::adminPaidMemberListLoad($eid, $direction, $order) as $entry) {
      if (!empty($entry['member_no'])) {
        $entry['member_no'] = $entry['badge_type'] . sprintf("%0" . $digits . "d", $entry['member_no']);
      }
      if (!empty($entry['days'])) {
        $dayDescs = [];
        foreach (explode('|', $entry['days']) as $day) {
          $dayDescs[] = $days[$day] ?? $day;
        }
        $entry['days'] = implode(', ', $dayDescs);
      }
      $entry['member_type'] = isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type'];
      $entry['badge_type'] = $badgeTypes[$entry['badge_type']] ?? $entry['badge_type'];
      $entry['country'] = $countryOptions[$entry['country']] ?? $entry['country'];
      $entry['communication_method'] = $communicationsOptions[$entry['communication_method']] ?? $entry['communication_method'];
      $entry['display'] = $displayOptions[$entry['display']] ?? $entry['display'];
      $entry['is_paid'] = $yesNo[$entry['is_paid']] ?? $entry['is_paid'];
      $entry['is_approved'] = $yesNo[$entry['is_approved']] ?? $entry['is_approved'];
      // Sanitize each entry.
      $rows[] = $entry;
    }
    $content['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Render a summary convention members in the database.
   */
  public function memberAdminMemberSummary($eid) {
    $event = SimpleConregEventStorage::load(['eid' => $eid]);
    $content = [
      '#title' => $this->t('@event_name Member Summary', ['@event_name' => $event['event_name']]),
      '#cache' => [
        'tags' => ['event:' . $eid . ':members'],
        'max-age' => Cache::PERMANENT,
      ],
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
    ];

    $content['copy'] = [
      '#type' => 'button',
      '#value' => $this->t('Copy to clipboard'),
      '#attributes' => ['class' => ['table-copy']]
    ];

    $content['message_member'] = [
      '#markup' => $this->t('Summary by member type'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListSummary($eid, $content);

    $content['message_badge_type'] = [
      '#markup' => $this->t('Summary by badge type'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListBadgeSummary($eid, $content);

    $content['message_days'] = [
      '#markup' => $this->t('Summary by day'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListDaysSummary($eid, $content);

    $content['message_payment_method'] = [
      '#markup' => $this->t('Summary by payment method'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListPaymentMethodSummary($eid, $content);

    $content['message_amount_paid'] = [
      '#markup' => $this->t('Summary by amount paid'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListAmountPaidSummary($eid, $content);

    $content['message_type_amount_paid'] = [
      '#markup' => $this->t('Summary by member type and amount paid'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListAmountPaidByTypeSummary($eid, $content);

    $content['message_by_date'] = [
      '#markup' => $this->t('Summary by date joined'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];
    $this->memberAdminMemberListByDateSummary($eid, $content);

    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Return a list of member add-ons.
   */
  public function memberAdminMemberAddOns($eid) {
    $content = [
      '#cache' => [
        'tags' => ['event:' . $eid . ':members'],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    $content['message'] = [
      '#markup' => $this->t('List of members with add-ons.'),
    ];

    $rows = [];
    $headers = [
      $this->t('First Name'),
      $this->t('Last Name'),
      $this->t('email'),
      $this->t('Add-on Option'),
      $this->t('Add-on Detail'),
      $this->t('Add-on Price'),
    ];

    $total = 0;

    foreach (SimpleConregStorage::adminMemberAddOns($eid) as $entry) {
      $total += $entry['add_on_price'];
      $rows[] = $entry;
    }

    $rows[] = [$this->t('Total'), '', '', '', '', number_format($total, 2)];

    $content['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Display a list of child members and their ages.
   */
  public function memberAdminChildMemberAges($eid) {
    $content = [
      '#cache' => [
        'tags' => ['event:' . $eid . ':members'],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    $content['message'] = [
      '#markup' => $this->t('List of members with add-ons.'),
    ];

    $rows = [];
    $headers = [
      $this->t('Member No'),
      $this->t('First Name'),
      $this->t('Last Name'),
      $this->t('email'),
      $this->t('Member Type'),
      $this->t('Age'),
      $this->t('Parent First Name'),
      $this->t('Parent Last Name'),
      $this->t('Parent email'),
    ];

    foreach (SimpleConregStorage::adminMemberChildMembers($eid) as $entry) {
      // Sanitize each entry.
      $rows[] = $entry;
    }

    $content['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

}
