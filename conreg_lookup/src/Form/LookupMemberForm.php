<?php

/**
 * @file
 * Contains \Drupal\conreg_lookup\LookupMemberForm
 */

namespace Drupal\conreg_lookup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\SimpleConregStorage;
use Drupal\devel;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class LookupMemberForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_members';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.'.$eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $pageSize = $config->get('display.page_size');
    $digits = $config->get('member_no_digits');

    $tempstore = \Drupal::service('tempstore.private')->get('simple_conreg');
    // If form values submitted, use the search value that was submitted over the saved values.
    if (isset($form_values['search']))
      $search = $form_values['search'];
    else
      $search = $tempstore->get('lookup_search');
    $tempstore->set('lookup_search', $search);

    $form = array(
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $headers = array(
      'member_no' => ['data' => $this->t('Member No'), 'field' => 'm.member_no'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'phone' => ['data' => t('Phone'), 'field' => 'm.phone'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'badge_type' =>  ['data' => t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'registered_by' => ['data' => t('Registered By'), 'field' => 'm.registered_by'],
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

    if (strlen($search) < 3) {
      $form['message'] = [
        '#markup' => $this->t('Please enter at least 3 characters in search box.'),
        '#prefix' => '<div id="memberform">',
        '#suffix' => '</div>',
      ];
      return $form;
    }

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );      

    $entries = $this->adminMemberLookupLoad($eid, $search);

    foreach ($entries as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $is_paid = $entry['is_paid'];
      $row = array();
      if (empty($entry["member_no"])) {
        $member_no = "";
      }
      else {
        $member_no = trim($entry['badge_type']) . sprintf("%0".$digits."d", $entry['member_no']);
      }
      $row["member_no"] = array(
        '#markup' => $member_no,
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
      $row['phone'] = array(
        '#markup' => Html::escape($entry['phone']),
      );
      $row['badge_name'] = array(
        '#markup' => Html::escape($entry['badge_name']),
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
      $row['registered_by'] = array(
        '#markup' => Html::escape($entry['registered_by']),
      );
      $form['table'][$mid] = $row;
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
	  //$saved_members = SimpleConregStorage::loadAllMemberNos($eid);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal\Core\Cache\Cache::invalidateTags(['simple-conreg-member-list']);
  }

  private function adminMemberLookupLoad($eid, $search)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    $select->leftJoin('conreg_members', 'l', 'l.mid = m.mid');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'days');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'member_no');
    $select->addField('m', 'phone');
    $select->addExpression("concat(l.first_name, ' ', l.last_name)", 'registered_by');
    // Add selection criteria.
    $words = explode(' ', trim($search));
    foreach ($words as $word) {
      if ($word != '') {
        // Escape search word to prevent dangerous characters.
        $esc_word = '%' . $connection->escapeLike($word) . '%';
        $likes = $select->orConditionGroup()
          ->condition('m.member_no', $esc_word, 'LIKE')
          ->condition('m.first_name', $esc_word, 'LIKE')
          ->condition('m.last_name', $esc_word, 'LIKE')
          ->condition('m.badge_name', $esc_word, 'LIKE')
          ->condition('m.email', $esc_word, 'LIKE');
        $select->condition($likes);
      }
    }
    $select->condition("m.is_deleted", FALSE);
    $select->condition("m.is_paid", TRUE);
    $select->orderby("member_no");
    // Make sure we only get items 0-49, for scalability reasons.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}    

