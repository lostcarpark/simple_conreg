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
    list($typeOptions, $typeVals) = SimpleConregOptions::memberTypes($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $pageSize = $config->get('display.page_size');

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
        $order = 'email';
        $pageOptions['order'] = 'Email';
        break;
      default:
        $order = 'member_no';
        break;
    }

    $options = ['not_checked' => $this->t('Members to be checked in'),
                'checked_in' => $this->t('Already checked in members'),
                'all' => $this->t('All members'),
                'custom' => $this->t('Custom search'),
               ];

    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['display']))
      $display = $form_values['display'];
    elseif (empty($form_values['display'])) {
      // If display not submitted from form or passed in through URL, take last value from session.
      $display = $tempstore->get('check_in_display');
    }
    if (empty($display) || !array_key_exists($display, $options))
      $display = key($options); // If still no display specified, or invalid option, default to first key in displayOptions.

    // Save the display options.
    $tempstore->set('check_in_display', $display);
    $tempstore->set('check_in_page', $page);


    if (isset($form_values['search']))
      $search = $form_values['search'];
    else
      $search = $tempstore->get('search');
    $tempstore->set('search', $search);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $this->CheckInSummary($eid, $form);

    $form['display'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select '),
      '#options' => $options,
      '#default_value' => $display,
      '#required' => TRUE,
      '#ajax' => array(
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ),
    );

    $headers = array(
      'member_no' => ['data' => t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'registered_by' =>  ['data' => t('Registered By'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'badge_type' =>  ['data' => t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'comment' =>  ['data' => t('Comment'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Paid'),
      t('Checked In'),
      t('Action'),
    );

    // If display 
    if ($display == 'custom') {
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
    }

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );      

    if ($display != 'custom')
      list($pages, $entries) = SimpleConregStorage::adminMemberCheckInListLoad($eid, $display, NULL, $page, $pageSize, $order, $direction);
    elseif (!empty(trim($search)))
      list($pages, $entries) = SimpleConregStorage::adminMemberCheckInListLoad($eid, $display, $search, $page, $pageSize, $order, $direction);
    else {
      $pages = 0;
      $entries = [];
    }
    
    // Check if current page greater than number of pages...
    if ($page > $pages) {
      // Look at making this redirect so correct page is in the URL, but tricky because we're in AJAX callback. For now just show last page.
      $page = $pages;
      // Refetch page data.
      if ($display != 'custom')
        list($pages, $entries) = SimpleConregStorage::adminMemberCheckInListLoad($eid, $display, NULL, $page, $pageSize, $order, $direction);
      elseif (!empty(trim($search)))
        list($pages, $entries) = SimpleConregStorage::adminMemberCheckInListLoad($eid, $display, $search, $page, $pageSize, $order, $direction);
      // Page doesn't exist for current selection criteria, so go to last page of query.
      // $form_state->setRedirect('simple_conreg_admin_members', ['display' => $display, 'page' => $pages], ['query' => $pageOptions]);
      //return;
    }

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
      $row['display'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['registered_by']),
      );
      $memberType = trim($entry['member_type']);
      $row['member_type'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($typeVals[$memberType]['name']) ? $typeVals[$memberType]['name'] : $memberType),
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
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute ('simple_conreg_admin_members_edit', ['eid' => $eid, 'mid' => $mid]),
          ),
          'delete_button' => array(
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('simple_conreg_admin_members_delete', ['eid' => $eid, 'mid' => $mid]),
          ),
          'transfer_button' => array(
            'title' => $this->t('Transfer'),
            'url' => Url::fromRoute('simple_conreg_admin_members_transfer', ['eid' => $eid, 'mid' => $mid]),
          ),
          'email_button' => array(
            'title' => $this->t('Send email'),
            'url' => Url::fromRoute('simple_conreg_admin_members_email', ['eid' => $eid, 'mid' => $mid]),
          ),
        ),
      );*/

      $form['table'][$mid] = $row;
    }
    
    $form['pager'] = array(
      '#markup' => $this->t('Page:'),
      '#prefix' => '<div id="pager">',
      '#suffix' => '</div>',
    );
    for ($p = 1; $p <= $pages; $p++) {
      if ($p == $page)
        $form['pager']['page'.$p]['#markup'] = $p;
      else
        $form['pager']['page'.$p] = Link::createFromRoute($p, 'simple_conreg_admin_checkin', ['eid' => $eid, 'display' => $display, 'page' => $p], ['query' => $pageOptions])->toRenderable();
      $form['pager']['page'.$p]['#prefix'] = ' <span>';
      $form['pager']['page'.$p]['#suffix'] = '</span> ';
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save Changes'),
      '#attributes' => array('id' => "submitBtn"),
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

