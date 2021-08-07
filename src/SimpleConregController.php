<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregController.
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Html;
use Drupal\user\Entity\User;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Controller for Simple Convention Registration.
 */
class SimpleConregController extends ControllerBase {

  /**
   * Display simple thank you page.
   */
  public function registrationThanks($eid = 1) {
    $config = $this->config('simple_conreg.settings.'.$eid);
    $countryOptions = $this->getMemberCountries($config);

    $content = array();

    $content['message'] = array(
      '#markup' => $config->get('thanks.thank_you_message'),
    );
  
    return $content;
  }

  /**
   * Render a list of entries in the database.
   */
  public function memberList($eid = 1) {
    $config = $this->config('simple_conreg.settings.'.$eid);
    $countryOptions = $this->getMemberCountries($config);
    $types = SimpleConregOptions::badgeTypes($eid, $config);
    $digits = $config->get('member_no_digits');

    switch(isset($_GET['sort']) ? $_GET['sort'] : '') {
      case 'desc':
        $direction = 'DESC';
        break;
      default:
        $direction = 'ASC';
        break;
    }
    switch(isset($_GET['order']) ? $_GET['order'] : '') {
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

    $content = array();

    //$content['#markup'] = $this->t('Unpaid Members');

    $content['message'] = array(
      '#cache' => ['tags' => ['simple-conreg-member-list']],
      '#markup' => $this->t('Members\' public details are listed below.'),
    );

    $rows = [];
    $headers = [
      'member_no' => ['data' => t('Member No'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'member_name' =>  ['data' => t('Name'), 'field' => 'name'],
      'badge_type' =>  ['data' => t('Type'), 'field' => 'm.badge_type', 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'member_country' =>  ['data' => t('Country'), 'field' => 'm.country', 'class' => [RESPONSIVE_PRIORITY_MEDIUM]],
    ];
    $total = 0;

    foreach ($entries = SimpleConregStorage::adminPublicListLoad($eid) as $entry) {
      // Sanitize each entry.
      $badge_type = trim($entry['badge_type']);
      $member_no = sprintf("%0".$digits."d", $entry['member_no']);
      $member = ['member_no' => $badge_type . $member_no];
      switch ($entry['display']) {
        case 'F':
          $fullname = trim(trim($entry['first_name']) . ' ' . trim($entry['last_name']));
          if ($fullname != trim($entry['badge_name']))
            $fullname .= ' (' . trim($entry['badge_name']) . ')';
          $member['name'] = $fullname;
          break;
        case 'B':
          $member['name'] = trim($entry['badge_name']);
          break;
        case 'N':
          $member['name'] = t('Name withheld');
          break;
      }
      $member['badge_type'] = trim(isset($types[$badge_type]) ? $types[$badge_type] : $badge_type);
      $member['country'] = trim(isset($countryOptions[$entry['country']]) ? $countryOptions[$entry['country']] : $entry['country']);

      // Set key to field to be sorted by.
      if ($order == 'member_no')
        $key = $member_no;
      else
        $key = $member[$order] . $member_no;  // Append member number to ensure uniqueness.
      if (!empty($entry['display']) && $entry['display'] != 'N' && !empty($entry['country'])) {
        $rows[$key] = array_map('Drupal\Component\Utility\Html::escape', $member);
      }
      $total++;
    }
    
    // Sort array by key.
    if ($direction == 'DESC')
      krsort($rows);
    else
      ksort($rows);
    
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      //'#footer' => array(t("Total")),
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );

    $content['summary_heading'] = ['#markup' => $this->t('<h2>Country Breakdown</h2>')];

    $rows = array();
    $headers = array(
      t('Country'), 
      t('Number of members'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberCountrySummaryLoad($eid) as $entry) {
      if (!empty($entry['country'])) {
        // Sanitize each entry.
        $entry['country'] = trim($countryOptions[$entry['country']]);
        $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
        $total += $entry['num'];
      }
    }
    //Add a row for the total.
    $rows[] = array(t("Total"), $total);
    $content['summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );
    // Don't cache this page.
    //$content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Add a summary by member type to render array.
   */
  public function memberAdminMemberListSummary($eid, &$content)
  {
    $types = SimpleConregOptions::memberTypes($eid);
    $headers = array(
      t('Member Type'), 
      t('Number of members'),
    );
    $content['summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $content['summary'][] = [
        ['#markup' => Html::escape(isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type'])],
        ['#markup' => $entry['num']],
      ];
      $total += $entry['num'];
    }
    //Add a row for the total.
    $content['summary']['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
    ];

    return $content;
  }

  /**
   * Add a summary by payment method to render array.
   */
  public function memberAdminMemberListBadgeSummary($eid, &$content)
  {
    $types = SimpleConregOptions::badgeTypes($eid);
    $headers = array(
      t('Badge Type'), 
      t('Number of members'),
    );
    $content['badge_summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberBadgeSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      $content['badge_summary'][] = [
        ['#markup' => Html::escape(isset($types[trim($entry['badge_type'])]) ? $types[trim($entry['badge_type'])] : $entry['badge_type'])],
        ['#markup' => $entry['num']],
      ];
      $total += $entry['num'];
    }
    //Add a row for the total.
    $content['badge_summary']['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
    ];

    return $content;
  }

  /**
   * Add a summary by payment method to render array.
   */
  public function memberAdminMemberListDaysSummary($eid, &$content)
  {
    $days = SimpleConregOptions::days($eid);

    $dayTotals = [];
    foreach($days as $key=>$val) {
      $dayTotals[$key] = 0;
    }
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberDaysSummaryLoad($eid) as $entry) {
      // Sanitize each entry.
      foreach (explode('|', $entry['days']) as $day)
        $dayTotals[$day] += $entry['num'];
      $total += $entry['num'];
    }

    $headers = array(
      t('Days'),
      t('Number of members'),
    );
    $content['days_summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    );
    foreach ($dayTotals as $key=>$val) {
      // Sanitize each entry.
      $content['days_summary'][] = [
        ['#markup' => Html::escape(isset($days[$key]) ? $days[$key] : $key)],
        ['#markup' => $val],
      ];
    }
    //Add a row for the total.
    $content['days_summary']['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
    ];

    return $content;
  }

  /**
   * Add a summary by badge type to render array.
   */
  public function memberAdminMemberListPaymentMethodSummary($eid, &$content)
  {
    $headers = [
      t('Payment Method'),
      t('Number of members'),
    ];
    // Set up table.
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    ];
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberPaymentMethodSummaryLoad($eid) as $entry) {
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => Html::escape($entry['payment_method'])],
        ['#markup' => $entry['num']],
      ];
      $total += $entry['num'];
    }
    //Add a row for the total.
    $rows['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
    ];
    $content['payment_method_summary'] = $rows;

    return $content;
  }

  /**
   * Add a summary by badge type to render array.
   */
  public function memberAdminMemberListAmountPaidSummary($eid, &$content)
  {
    $headers = [
      t('Amount Paid'), 
      t('Number of members'), 
      t('Total Paid'),
    ];
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    ];
    $total = 0;
    $total_amount = 0;
    foreach ($entries = SimpleConregStorage::adminMemberAmountPaidSummaryLoad($eid) as $entry) {
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
    //Add a row for the total.
    $rows['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => number_format($total_amount, 2), '#wrapper_attributes' => ['class' => ['table-total']]],
    ];
    $content['amount_paid_summary'] = $rows;

    return $content;
  }

  /**
   * Add a summary by member type and amount paid to render array.
   */
  public function memberAdminMemberListAmountPaidByTypeSummary($eid, &$content)
  {
    $types = SimpleConregOptions::memberTypes($eid);
    $headers = [
      t('Member Type'), 
      t('Amount Paid'), 
      t('Number of members'), 
      t('Total Paid'),
    ];
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    ];
    $total = 0;
    $total_amount = 0;
    foreach ($entries = SimpleConregStorage::adminMemberAmountPaidByTypeSummaryLoad($eid) as $entry) {
      // Replace type code with description.
      if (isset($types->types[$entry['member_type']]))
        $entry['member_type'] = (isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type']);
      // Calculate total received at that rate.
      $total_paid = $entry['member_price'] * $entry['num'];
      $entry['total_paid'] = number_format($total_paid, 2);
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => Html::escape(isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type'])],
        ['#markup' => $entry['member_price']],
        ['#markup' => $entry['num']],
        ['#markup' => number_format($total_paid, 2)],
      ];

      // Add to totals.
      $total += $entry['num'];
      $total_amount += $total_paid;
    }
    //Add a row for the total.
    $rows['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => '', '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => number_format($total_amount, 2), '#wrapper_attributes' => ['class' => ['table-total']]],
    ];
    $content['type_amount_paid_summary'] = $rows;

    return $content;
  }
  
  /**
   * Add a summary by date joined to render array.
   */
  public function memberAdminMemberListByDateSummary($eid, &$content)
  {
    $months = DateHelper::monthNames();
    $headers = [
      t('Year'), 
      t('Month'), 
      t('Number of members'), 
      t('Total Paid'),
      t('Cumulative members'), 
      t('Cumulative Total Paid'),
    ];
    $rows = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => t('No entries available.'),
    ];
    $total = 0;
    $total_amount = 0;
    foreach ($entries = SimpleConregStorage::adminMemberByDateSummaryLoad($eid) as $entry) {
      // Convert month to name.
      $entry['month'] = $months[$entry['month']];
      $total += $entry['num'];
      $total_amount += $entry['total_paid'];
      // Sanitize each entry.
      $rows[] = [
        ['#markup' => Html::escape($entry['year'])],
        ['#markup' => Html::escape($entry['month'])],
        ['#markup' => $entry['num']],
        ['#markup' => number_format($entry['total_paid'], 2)],
        ['#markup' => $total],
        ['#markup' => number_format($total_amount, 2)],
      ];
    }
    //Add a row for the total.
    $rows['total'] = [
      ['#markup' => t("Total"), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => '', '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => number_format($total_amount, 2), '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => $total, '#wrapper_attributes' => ['class' => ['table-total']]],
      ['#markup' => number_format($total_amount, 2), '#wrapper_attributes' => ['class' => ['table-total']]],
    ];
    $content['by_date_summary'] = $rows;

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminMemberList($eid)
  {
    $config = SimpleConregConfig::getConfig($eid);
    $countryOptions = $this->getMemberCountries($config);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $communicationsOptions = SimpleConregOptions::communicationMethod($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $yesNo = SimpleConregOptions::yesNo();
    $digits = $config->get('member_no_digits');

    $content = [
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ]
    ];

    $pageOptions = [];
    switch(isset($_GET['sort']) ? $_GET['sort'] : '') {
      case 'desc':
        $direction = 'DESC';
        $pageOptions['sort'] = 'desc';
        break;
      default:
        $direction = 'ASC';
        break;
    }
    switch(isset($_GET['order']) ? $_GET['order'] : '') {
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

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all paid convention members.'),
    );

    $this->memberAdminMemberListSummary($eid, $content);

    $rows = array();
    $headers = array(
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'member_no' => ['data' => t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'badge_type' =>  ['data' => t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Street'),
      t('Street line 2'),
      t('City'),
      t('County'),
      t('Postcode'),
      t('Country'),
      t('Phone'),
      t('Birth Date'),
      t('Age'),
      'display' =>  ['data' => t('Display'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Communication Method'),
      t('Paid'),
      t('Price'),
      t('Comments'),
      t('Approved'),
      'mid' => ['data' => t('Internal ID'), 'field' => 'm.mid', 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Date joined'),
    );

    foreach ($entries = SimpleConregStorage::adminPaidMemberListLoad($eid, $direction, $order) as $entry) {
      if (!empty($entry['member_no']))
        $entry['member_no'] = $entry['badge_type'] . sprintf("%0".$digits."d", $entry['member_no']);
      if (!empty($entry['days'])) {
        $dayDescs = [];
        foreach(explode('|', $entry['days']) as $day) {
          $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
        }
        $entry['days'] = implode(', ', $dayDescs);
      }
      $entry['member_type'] = isset($types->types[$entry['member_type']]) ? $types->types[$entry['member_type']]->name : $entry['member_type'];
      $entry['badge_type'] = isset($badgeTypes[$entry['badge_type']]) ? $badgeTypes[$entry['badge_type']] : $entry['badge_type'];
      $entry['country'] = isset($countryOptions[$entry['country']]) ? $countryOptions[$entry['country']] : $entry['country'];
      $entry['communication_method'] = isset($communicationsOptions[$entry['communication_method']]) ? $communicationsOptions[$entry['communication_method']] : $entry['communication_method'];
      $entry['display'] = isset($displayOptions[$entry['display']]) ? $displayOptions[$entry['display']] : $entry['display'];
      $entry['is_paid'] = isset($yesNo[$entry['is_paid']]) ? $yesNo[$entry['is_paid']] : $entry['is_paid'];
      $entry['is_approved'] = isset($yesNo[$entry['is_approved']]) ? $yesNo[$entry['is_approved']] : $entry['is_approved'];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminMemberSummary($eid) {
    $content = [
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ]
    ];

    $content['message_member'] = array(
      '#markup' => $this->t('Summary by member type'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListSummary($eid, $content);

    $content['message_badge_type'] = array(
      '#markup' => $this->t('Summary by badge type'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListBadgeSummary($eid, $content);

    $content['message_days'] = array(
      '#markup' => $this->t('Summary by day'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListDaysSummary($eid, $content);
    
    $content['message_payment_method'] = array(
      '#markup' => $this->t('Summary by payment method'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListPaymentMethodSummary($eid, $content);

    $content['message_amount_paid'] = array(
      '#markup' => $this->t('Summary by amount paid'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListAmountPaidSummary($eid, $content);

    $content['message_type_amount_paid'] = array(
      '#markup' => $this->t('Summary by member type and amount paid'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListAmountPaidByTypeSummary($eid, $content);

    $content['message_by_date'] = array(
      '#markup' => $this->t('Summary by date joined'),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    );
    $this->memberAdminMemberListByDateSummary($eid, $content);

    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Render a list of Member Badges in the database.
   */
  public function memberAdminBadges($eid) {
    $config = SimpleConregConfig::getConfig($eid);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $digits = $config->get('member_no_digits');

    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all paid convention members.'),
    );

    $rows = array();
    $headers = array(
      'member_no' => ['data' => t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'badge_type' =>  ['data' => t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
    );

    foreach ($entries = SimpleConregStorage::adminMemberBadges($eid) as $entry) {
      if (!empty($entry['member_no']))
        $entry['member_no'] = $entry['badge_type'] . sprintf("%0".$digits."d", $entry['member_no']);
      $entry['badge_type'] = isset($badgeTypes[$entry['badge_type']]) ? $badgeTypes[$entry['badge_type']] : $entry['badge_type'];
      if (!empty($entry['days'])) {
        $dayDescs = [];
        foreach(explode('|', $entry['days']) as $day) {
          $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
        }
        $entry['days'] = implode(', ', $dayDescs);
      }
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  public function memberAdminMemberAddOns($eid) {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('List of members with add-ons.'),
    );

    $table = 0;
    $zz9_option = "";
    $rows = array();
    $headers = array(
      t('First Name'),
      t('Last Name'),
      t('email'),
      t('Add-on Option'),
      t('Add-on Detail'),
      t('Add-on Price'),
    );

    $total = 0;

    foreach ($entries = SimpleConregStorage::adminMemberAddOns($eid) as $entry) {
      $total += $entry['add_on_price'];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    
    $rows[] = [t('Total'), '', '', '', '', number_format($total, 2)];
    
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  public function memberAdminChildMemberAges($eid) {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('List of members with add-ons.'),
    );

    $table = 0;
    $zz9_option = "";
    $rows = array();
    $headers = array(
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('email'),
      t('Member Type'),
      t('Age'),
      t('Parent First Name'),
      t('Parent Last Name'),
      t('Parent email'),
    );

    $total = 0;

    foreach ($entries = SimpleConregStorage::adminMemberChildMembers($eid) as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  public function memberAdminZZ9List($eid) {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all Lazlar Lyricon 3 members of ZZ9.'),
    );

    $table = 0;
    $zz9_option = "";
    $rows = array();
    $headers = array(
      t('First Name'),
      t('Last Name'),
      t('Email'),
      t('Badge Name'),
      t('Street'),
      t('City'),
      t('County'),
      t('Postcode'),
      t('Country'),
      t('Phone'),
      t('Birth Date'),
      t('ZZ9 No'),
      t('Allow Committee'),
      t('Allow Members'),
    );

    foreach ($entries = SimpleConregStorage::adminZZ9MemberListLoad($eid) as $entry) {
      if ($zz9_option != $entry['add_on']) {
        if (!empty($zz9_option)) {
          $this->memberAdminZZ9ListTable($content, $zz9_option, $headers, $rows, $table++);
          $rows = array();
        }
        $zz9_option = $entry['add_on'];
      }
      // Take out the ZZ9 Option column.
      unset($entry['add_on']);
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    $this->memberAdminZZ9ListTable($content, $zz9_option, $headers, $rows, $table++);
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminProgrammeList($eid) {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all members who ticked the "programme participant" checkbox.'),
    );

    $rows = array();
    $headers = array(
      t('Type'), 
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('Email'),
      t('Badge Name'),
      t('Paid'),
      t('Approved'),
    );

    foreach ($entries = SimpleConregStorage::adminProgrammeMemberListLoad($eid) as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminVolunteerList($eid) {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all members who ticked the "volunteer" checkbox.'),
    );

    $rows = array();
    $headers = array(
      t('Type'), 
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('Email'),
      t('Badge Name'),
      t('Paid'),
      t('Approved'),
    );

    foreach ($entries = SimpleConregStorage::adminVolunteerMemberListLoad($eid) as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  public function memberAdminZZ9ListTable(&$content, $zz9_option, $headers, $rows, $tableNo) {
    $content['heading'.$tableNo] = array(
      '#markup' => '<h2>'.$zz9_option.'</h2>',
    );
    $content['table'.$tableNo] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );
  }

  /**
   * Render a filtered list of entries in the database.
   */
  public function entryAdvancedList() {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('A more complex list of entries in the database.') . ' ' .
      $this->t('Only the entries with name = "John" and age older than 18 years are shown, the username of the person who created the entry is also shown.'),
    );

    $headers = array(
      t('Id'),
      t('Created by'),
      t('Name'),
      t('Surname'),
      t('Age'),
    );

    $rows = array();
    foreach ($entries = SimpleConregStorage::advancedLoad() as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', $entry);
    }
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array('id' => 'dbtng-example-advanced-list'),
      '#empty' => t('No entries available.'),
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;
    return $content;
  }

  public function getMemberCountries(&$config) {
    $countries = explode("\n", $config->get('reference.countries')); // One country per line.
    $countryOptions = array();
    foreach ($countries as $country) {
      if (!empty($country)) {
        list($code, $name) = explode('|', $country);
        $countryOptions[$code] = $name;
      }
    }
    return $countryOptions;
  }

  /**
   * Check valid member credentials, and if valid, login and redirect to member portal.
   */
  public function memberLoginAndRedirect($mid, $key, $expiry)
  {

    // Check member credentials valid.
    $member = SimpleConregStorage::load(['mid' => $mid, 'random_key' => $key, 'login_exp_date' => $expiry, 'is_deleted' => 0]);
    if (empty($member['mid'])) {
      $content['markup'.$tableNo] = array(
        '#markup' => '<p>Invalid credentials.</p>',
      );
      return $content;
    }

    // Check if login has expired.
    if (empty($member['login_exp_date'] > \Drupal::time()->getRequestTime())) {
      $content['markup'.$tableNo] = array(
        '#markup' => '<p>Login has expired. Please use Member Check to generate a new login link.</p>',
      );
      return $content;
    }

    // Check if user already exists.
    $user = user_load_by_mail($member['email']);
    
    // If user doesn't exist, create new user.
    if (!$user) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $user = User::create([
        'name' => $member['email'],
        'mail' => $member['email'],
      ]);
      $user->set("langcode", $language);
      $user->set("preferred_langcode", $language);
      $user->set("preferred_admin_langcode", $language);
      // Set the user timezone to the site default timezone.
      $config = \Drupal::config('system.date');
      $config_data_default_timezone = $config->get('timezone.default');
      $user->set('timezone', !empty($config_data_default_timezone) ? $config_data_default_timezone : @date_default_timezone_get());
      $user->activate();// NOTE: login will fail silently if not activated!
      $user->save();
    }
    
    // Login user.
    user_login_finalize($user);
    
    // Redirect to member portal.
    return new RedirectResponse(\Drupal\Core\Url::fromRoute('simple_conreg_portal', ['eid' => $member['eid']])->setAbsolute()->toString());
  }

  /**
   * Function used for badge uploading.
   */
  public function badgeUpload($eid)
  {
    $pngdata = \Drupal::request()->request->get('data');
    if (!empty($pngdata)) {
      list($id, $base64) = explode('|', $pngdata);
      list($type, $data) = explode(';', $base64);
      list(, $data)      = explode(',', $data);
      $pngdata = base64_decode($data);
      $path = 'public://badges/'.$eid;
      \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
      file_save_data($pngdata, $path.'/'.$id.'.png', FileSystemInterface::EXISTS_REPLACE);
    }

    $content['markup'] = array(
      '#markup' => '<p>Badge Upload.</p>',
    );
    return $content;
  }

  /**
   * Function used for badge uploading.
   */
  public function bulksend($eid, $mid)
  {
   $config = SimpleConregConfig::getConfig($eid);
   // Look up email address for member.
    $members = SimpleConregStorage::loadAll(['eid' => $eid, 'mid' => $mid, 'is_deleted' => 0]);
    $member = $members[0];

    // Set up parameters for receipt email.
    $params = ['eid' => $member['eid'], 'mid' => $member['mid']];
    $params['subject'] = $config->get('bulkemail.template_subject');
    $params['body'] = $config->get('bulkemail.template_body');
    $params['body_format'] = $config->get('bulkemail.template_format');
    $module = "simple_conreg";
    $key = "template";
    $to = $member["email"];
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    if (!empty($member["email"]))
      $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);

    $content['markup'] = array(
      '#markup' => '<p>Bulk send.</p>',
    );
    return $content;
  }

}
