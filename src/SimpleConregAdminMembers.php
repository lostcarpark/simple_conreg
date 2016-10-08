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
class SimpleConregAdminMembers extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_members';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $display = NULL, $page = NULL) {
    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings');
    list($typeOptions, $typeNames, $typePrices) = SimpleConregOptions::memberTypes($config);
    $badgeTypes = SimpleConregOptions::badgeTypes($config);
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
        $order = 'email';
        $pageOptions['order'] = 'Email';
        break;
      default:
        $order = 'member_no';
        break;
    }

    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['display']))
      $display = $form_values['display'];
    elseif (empty($display)) {
      // If display not submitted from form or passed in through URL, take last value from session.
      $display = $tempstore->get('display');
      if (empty($display))
        $display = 'approval'; // If still no display specified, default to awaiting approval.
    }
    $tempstore->set('display', $display);

    $tempstore->set('page', $page);


    if (isset($form_values['search']))
      $search = $form_values['search'];
    else
      $search = $tempstore->get('search');
    $tempstore->set('search', $search);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $form['add_link'] = Link::createFromRoute($this->t('Add new member'), 'simple_conreg_admin_members_add', ['mid' => $mid])->toRenderable();
    $form['add_link']['#attributes'] = ['class' => ['button', 'button-action', 'button--primary', 'button--small']];

    $form['display'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select '),
      '#options' => array('approval' => $this->t('Paid members awaiting approval'),
                          'approved' => $this->t('Paid and approved members'),
                          'unpaid' => $this->t('Unpaid members'),
                          'all' => $this->t('All members'),
                          'custom' => $this->t('Custom search'),
                          ),
      '#default_value' => $display,
      '#required' => TRUE,
      '#ajax' => array(
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ),
    );

    $headers = array(
      'mid' => ['data' => t('MID'), 'field' => 'm.mid'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'display' =>  ['data' => t('Display'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'badge_type' =>  ['data' => t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Paid'),
      t('Approved'),
      'member_no' => ['data' => t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      t('Update'),
    );

    // If display 
    if ($display == 'custom') {
      $form['search'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Custom search term'),
        '#default_value' => $search,
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
      list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($display, NULL, $page, $pageSize, $order, $direction);
    elseif (!empty(trim($search)))
      list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($display, $search, $page, $pageSize, $order, $direction);
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
        list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($display, NULL, $page, $pageSize, $order, $direction);
      elseif (!empty(trim($search)))
        list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($display, $search, $page, $pageSize, $order, $direction);
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
        '#markup' => SafeMarkup::checkPlain($entry['mid']),
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
        '#markup' => SafeMarkup::checkPlain(isset($displayOptions[$entry['display']]) ? $displayOptions[$entry['display']] : $entry['display']),
      );
      $memberType = trim($entry['member_type']);
      $row['member_type'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($typeNames[$memberType]) ? $typeNames[$memberType] : $memberType),
      );
      $badgeType = trim($entry['badge_type']);
      $row['badge_type'] = array(
        '#markup' => SafeMarkup::checkPlain(isset($badgeTypes[$badgeType]) ? $badgeTypes[$badgeType] : $badgeType),
      );
      $row['is_paid'] = array(
        '#markup' => $is_paid ? $this->t('Yes') : $this->t('No'),
      );
      $row["is_approved"] = array(
        //'#attributes' => array('name' => 'is_approved_'.$mid, 'id' => 'edit_is_approved_'.$mid),
        '#type' => 'checkbox',
        '#title' => t('Is Approved'),
        '#title_display' => 'invisible',
        '#default_value' => $entry['is_approved'],
      );
      if (empty($entry["member_no"]))
        $entry["member_no"] = "";
      $row["member_no"] = array(
        '#type' => 'textfield',
        '#title' => t('Member No'),
        '#title_display' => 'invisible',
        '#size' => 5,
        '#default_value' => $entry['member_no'],
      );
      $row['link'] = array(
        '#type' => 'dropbutton',
        '#links' => array(
          'edit_buttion' => array(
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('simple_conreg_admin_members_edit', ['mid' => $mid]),
          ),
          'delete_buttion' => array(
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('simple_conreg_admin_members_delete', ['mid' => $mid]),
          ),
        ),
      );
      //Link::createFromRoute($this->t('Edit'), 'simple_conreg_admin_members_edit', ['mid' => $mid])->toRenderable();

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
        $form['pager']['page'.$p] = Link::createFromRoute($p, 'simple_conreg_admin_members', ['display' => $display, 'page' => $p], ['query' => $pageOptions])->toRenderable();
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
  	  $saved_members = SimpleConregStorage::loadAllMemberNos();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $saved_members = SimpleConregStorage::loadAllMemberNos();
    $max_member = SimpleConregStorage::loadMaxMemberNo();
    foreach ($form_values["table"] as $mid => $member) {
      if (($member["is_approved"] != $saved_members[$mid]["is_approved"]) ||
          ($member["is_approved"] && $member["member_no"] != $saved_members[$mid]["member_no"])) {
        if ($member["is_approved"]) {
	        if (empty($member["member_no"])) {
	          // No member no specified, so assign next one.
	          $max_member++;
	          $member_no = $max_member;
	        } else {
	          // Member no specified. Adjust next member no.
	          $member_no = $member["member_no"];
	          if ($member_no > $max_member)
	            $max_member = $member_no;
	        }
	      } else {
	        // No member number for unapproved members.
	        $member_no = 0;
	      }
        $entry = array('mid' => $mid, 'is_approved' => $member["is_approved"], 'member_no' => $member_no);
        $return = SimpleConregStorage::update($entry);
      }
    }
    \Drupal\Core\Cache\Cache::invalidateTags(['simple-conreg-member-list']);
  }
}    

/*
  public function oldbuildForm(array $form, FormStateInterface $form_state, $mid = NULL, $key = NULL) {
    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings');

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $form['display'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select '),
      '#options' => array('1' => $this->t('Paid members awaiting approval'),
                          '2' => $this->t('Paid and approved members'),
                          '3' => $this->t('Unpaid members'),
                          '4' => $this->t('All members')),
      '#default_value' => '1',
      '#required' => TRUE,
      '#ajax' => array(
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ),
    );

    $headers = array(
      t('First name'),
      t('Last name'),
      t('Email'),
      t('Badge name'),
      t('Display'),
      t('Type'),
      t('Paid'),
      t('Approved'),
      t('Member no'),
      t('Update'),
    );

    $form['check'] = array(
      '#type' => 'checkbox',
      '#title' => t('Test'),
      '#ajax' => array(
        'callback' => array($this, 'updateTestCallback'),
        'event' => 'change',
      ),
    );
    $form['check2'] = array(
      '#type' => 'checkbox',
      '#title' => t('Test2'),
      '#ajax' => array(
        'callback' => array($this, 'updateTestCallback'),
        'event' => 'change',
      ),
    );

    $form['button'] = array(
      '#type' => 'button',
      '#value' => t('Test Button'),
      '#ajax' => array(
        'callback' => array($this, 'updateTestCallback'),
        'event' => 'click',
      ),
    );

    $form['another'] = array(
      '#type' => 'button',
      '#value' => t('Another'),
      '#ajax' => array(
        'callback' => array($this, 'updateTestCallback'),
        'event' => 'click',
      ),
    );
    $form['table'] = array(
      //'#type' => 'table',
      //'#header' => $headers,
      //'#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      //'#empty' => t('No entries available.'),
      '#prefix' => '<table id="simple-conreg-admin-member-list">',
      '#suffix' => '</table>',
      //'#tree' => TRUE,
    );      

    $form['table']['tbody'] = array(
      //'#type' => 'table',
      //'#header' => $headers,
      //'#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      //'#empty' => t('No entries available.'),
      '#prefix' => '<tbody>',
      '#suffix' => '</tbody>',
    );
    if (isset($form_values['display']))
      $display = $form_values['display'];
    else
      $display = 1;
    foreach ($entries = SimpleConregStorage::adminMemberListLoad($display) as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $is_paid = $entry['is_paid'];
      //$row = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $entry);
      $row = array(
        '#prefix' => '<tr>',
        '#suffix' => '</tr>',
      );
      $row['first_name'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['first_name']),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row['last_name'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['last_name']),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row['email'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['email']),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row['badge_name'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['badge_name']),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row['display'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['display']),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row['member_type'] = array(
        '#markup' => SafeMarkup::checkPlain($entry['member_type']),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row['is_paid'] = array(
        '#markup' => $is_paid ? $this->t('Yes') : $this->t('No'),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row["is_approved_".$mid] = array(
        //'#attributes' => array('name' => 'is_approved_'.$mid, 'id' => 'edit_is_approved_'.$mid),
        '#type' => 'checkbox',
        '#name' => "is_approved_".$mid,
        '#title' => t('Is Approved'),
        '#title_display' => 'invisible',
        '#ajax' => array(
          'callback' => array($this, 'updateTestCallback'),
          'event' => 'change',
        ),
        '#prefix' => '<td>',
        '#suffix' => '</td>',
      );
      $row["member_div"] = array(
        '#prefix' => '<div id="member_no_'.$mid.'">',
        '#suffix' => '</div>',
      );
      $row["member_div"]["member_no"] = array(
        '#type' => 'textfield',
        '#title' => t('Member No'),
        '#title_display' => 'invisible',
        '#size' => 5
      );
      $row["update"] = array(
        '#type' => 'button',
        '#value' => t('Update '.$mid),
        '#ajax' => array(
          'callback' => array($this, 'updateTestCallback'),
          'event' => 'click',
        ),
      );
      $form['table']['tbody'][$mid] = $row;
    }
    return $form;
  }
*/

