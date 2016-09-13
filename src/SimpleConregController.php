<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregController.
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\SafeMarkup;

/**
 * Controller for Simple Convention Registration.
 */
class SimpleConregController extends ControllerBase {

  /**
   * Display simple thank you page.
   */
  public function registrationThanks() {
    $config = $this->config('simple_conreg.settings');
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
  public function memberList() {
    $config = $this->config('simple_conreg.settings');
    $countryOptions = $this->getMemberCountries($config);
    $types = SimpleConregOptions::badgeTypes();
    $digits = $config->get('member_no_digits');

    switch($_GET['sort']) {
      case 'desc':
        $direction = 'DESC';
        break;
      default:
        $direction = 'ASC';
        break;
    }
    switch($_GET['order']) {
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

    foreach ($entries = SimpleConregStorage::adminPublicListLoad() as $entry) {
      // Sanitize each entry.
      $member_no = sprintf("%0".$digits."d", $entry['member_no']);
      $member = ['member_no' => $entry['badge_type'] . $member_no];
      switch ($entry['display']) {
        case 'F':
          $fullname = trim($entry['first_name']) . ' ' . trim($entry['last_name']);
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
      $member['badge_type'] = trim($types[$entry['badge_type']]);
      $member['country'] = trim($countryOptions[$entry['country']]);

      // Set key to field to be sorted by.
      if ($order == 'member_no')
        $key = $member_no;
      else
        $key = $member[$order] . $member_no;  // Append member number to ensure uniqueness.
      $rows[$key] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $member);
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
    foreach ($entries = SimpleConregStorage::adminMemberCountrySummaryLoad() as $entry) {
      // Sanitize each entry.
      $entry['country'] = trim($countryOptions[$entry['country']]);
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      $total += $entry['num'];
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
  public function memberAdminMemberListSummary(&$content) {

    list($types, $typeNames, $prices, $default) = SimpleConregOptions::memberTypes();
    $rows = array();
    $headers = array(
      t('Member Type'), 
      t('Number of members'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberSummaryLoad() as $entry) {
      // Replace type code with description.
      if (isset($types[$entry['member_type']]))
        $entry['member_type'] = $typeNames[$entry['member_type']];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      $total += $entry['num'];
    }
    //Add a row for the total.
    $rows[] = array(t("Total"), $total);
    $content['summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );

    return $content;
  }

  /**
   * Add a summary by payment method to render array.
   */
  public function memberAdminMemberListBadgeSummary(&$content) {

    $types = SimpleConregOptions::badgeTypes();
    $rows = array();
    $headers = array(
      t('Badge Type'), 
      t('Number of members'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberBadgeSummaryLoad() as $entry) {
      // Replace type code with description.
      if (isset($types[$entry['badge_type']]))
        $entry['badge_type'] = $types[$entry['badge_type']];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      $total += $entry['num'];
    }
    //Add a row for the total.
    $rows[] = array(t("Total"), $total);
    $content['badge_summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );

    return $content;
  }

  /**
   * Add a summary by badge type to render array.
   */
  public function memberAdminMemberListPaymentMethodSummary(&$content) {

    $rows = array();
    $headers = array(
      t('Payment Method'),
      t('Number of members'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberPaymentMethodSummaryLoad() as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      $total += $entry['num'];
    }
    //Add a row for the total.
    $rows[] = array(t("Total"), $total);
    $content['payment_method_summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );

    return $content;
  }

  /**
   * Add a summary by badge type to render array.
   */
  public function memberAdminMemberListAmountPaidSummary(&$content) {

    $rows = array();
    $headers = array(
      t('Amount Paid'), 
      t('Number of members'), 
      t('Total Paid'),
    );
    $total = 0;
    $totalAmount = 0;
    foreach ($entries = SimpleConregStorage::adminMemberAmountPaidSummaryLoad() as $entry) {
      // Calculate total received at that rate.
      $entry['total_paid'] = $entry['member_price'] * $entry['num'];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      $total += $entry['num'];
      $totalAmount += $entry['total_paid'];
    }
    //Add a row for the total.
    $rows[] = array(t("Total"), $total, $totalAmount);
    $content['amount_paid_summary'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    );

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminMemberList() {
    $content = array();

    $pageOptions = [];
    switch($_GET['sort']) {
      case 'desc':
        $direction = 'DESC';
        $pageOptions['sort'] = 'desc';
        break;
      default:
        $direction = 'ASC';
        break;
    }
    switch($_GET['order']) {
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

    $this->memberAdminMemberListSummary($content);

    $rows = array();
    $headers = array(
      'mid' => ['data' => t('MID'), 'field' => 'm.mid'],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
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
      'display' =>  ['data' => t('Display'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Communication Method'),
      t('Paid'),
      t('Price'),
      t('Approved'),
      t('Date joined'),
    );

    foreach ($entries = SimpleConregStorage::adminPaidMemberListLoad($direction, $order) as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
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
  public function memberAdminMemberSummary() {
    $content = array();

    $content['message_member'] = array(
      '#markup' => $this->t('Summary by member type.'),
    );
    $this->memberAdminMemberListSummary($content);

    $content['message_badge_tyoe'] = array(
      '#markup' => $this->t('Summary by badge type.'),
    );
    $this->memberAdminMemberListBadgeSummary($content);
    
    $content['message_payment_method'] = array(
      '#markup' => $this->t('Summary by payment method.'),
    );
    $this->memberAdminMemberListPaymentMethodSummary($content);

    $content['message_amount_paid'] = array(
      '#markup' => $this->t('Summary by amount paid.'),
    );
    $this->memberAdminMemberListAmountPaidSummary($content);

    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  public function memberAdminZZ9List() {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all Lazlar Lyricon 3 members of ZZ9.'),
    );

    $table = 0;
    $zz9_option = "";
    $rows = array();
    $headers = array(
      t('MID'), 
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

    foreach ($entries = SimpleConregStorage::adminZZ9MemberListLoad() as $entry) {
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
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
    }
    $this->memberAdminZZ9ListTable($content, $zz9_option, $headers, $rows, $table++);
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

  /**
   * Render a list of paid convention members in the database.
   */
  public function memberAdminProgrammeList() {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all members who ticked the "programme participant" checkbox.'),
    );

    $rows = array();
    $headers = array(
      t('MID'), 
      t('Type'), 
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('Email'),
      t('Badge Name'),
      t('Paid'),
      t('Approved'),
    );

    foreach ($entries = SimpleConregStorage::adminProgrammeMemberListLoad() as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
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
  public function memberAdminVolunteerList() {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all members who ticked the "volunteer" checkbox.'),
    );

    $rows = array();
    $headers = array(
      t('MID'), 
      t('Type'), 
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('Email'),
      t('Badge Name'),
      t('Paid'),
      t('Approved'),
    );

    foreach ($entries = SimpleConregStorage::adminVolunteerMemberListLoad() as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
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
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $entry);
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

}
