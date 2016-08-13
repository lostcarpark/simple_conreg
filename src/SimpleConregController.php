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

    $content = array();

    $content['message'] = array(
      '#cache' => [
        'tags' => ['simple-conreg-member-list'],
      ],
      '#markup' => $this->t('Members\' public details are listed below.'),
    );

    $rows = array();
    $headers = array(t('Member No'), t('Name'), t('Country'));
    $total = 0;

    foreach ($entries = SimpleConregStorage::adminPublicListLoad() as $entry) {
      // Sanitize each entry.
      $member = array('Member No' => $entry['badge_type'] . $entry['member_no']);
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
      $member['country'] = trim($countryOptions[$entry['country']]);
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $member);
      $total++;
    }
    
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
   * Render a list of paid convention members in the database.
   */
  public function memberAdminMemberList() {
    $content = array();

    $content['message'] = array(
      '#markup' => $this->t('Here is a list of all paid convention members.'),
    );

    $rows = array();
    $headers = array(
      t('Type'), 
      t('Number of members'),
    );
    $total = 0;
    foreach ($entries = SimpleConregStorage::adminMemberSummaryLoad() as $entry) {
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

    $rows = array();
    $headers = array(
      t('MID'), 
      t('Type'), 
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('Email'),
      t('Badge Name'),
      t('Street'),
      t('Street line 2'),
      t('City'),
      t('County'),
      t('Postcode'),
      t('Country'),
      t('Phone'),
      t('Birth Date'),
      t('Display'),
      t('Communication Method'),
      t('Paid'),
      t('Price'),
      t('Approved'),
      t('Date joined'),
    );

    foreach ($entries = SimpleConregStorage::adminPaidMemberListLoad() as $entry) {
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
