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
use Drupal\Core\Url;
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
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $display = NULL, $page = NULL) {
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
        $order = 'email';
        $pageOptions['order'] = 'Email';
        break;
      default:
        $order = 'member_no';
        break;
    }

    $options = ['approval' => $this->t('Paid members awaiting approval'),
                'approved' => $this->t('Paid and approved members'),
                'unpaid' => $this->t('Unpaid members'),
                'all' => $this->t('All members'),
                'custom' => $this->t('Custom search'),
               ];

    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['display']))
      $display = $form_values['display'];
    elseif (empty($display)) {
      // If display not submitted from form or passed in through URL, take last value from session.
      $display = $tempstore->get('display');
    }
    if (empty($display) || !array_key_exists($display, $options))
      $display = key($options); // If still no display specified, or invalid option, default to first key in displayOptions.

    $tempstore->set('display', $display);
    $tempstore->set('page', $page);


    if (isset($form_values['search']))
      $search = $form_values['search'];
    else
      $search = $tempstore->get('search');
    $tempstore->set('search', $search);

    $form = array(
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $form['add_link'] = Link::createFromRoute($this->t('Add new member'), 'simple_conreg_admin_members_add', ['eid' => $eid])->toRenderable();
    $form['add_link']['#attributes'] = ['class' => ['button', 'button-action', 'button--primary', 'button--small']];

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
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'display' =>  ['data' => t('Display'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
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
      list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($eid, $display, NULL, $page, $pageSize, $order, $direction);
    elseif (!empty(trim($search)))
      list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($eid, $display, $search, $page, $pageSize, $order, $direction);
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
        list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($eid, $display, NULL, $page, $pageSize, $order, $direction);
      elseif (!empty(trim($search)))
        list($pages, $entries) = SimpleConregStorage::adminMemberListLoad($eid, $display, $search, $page, $pageSize, $order, $direction);
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
          'edit_button' => array(
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('simple_conreg_admin_members_edit', ['eid' => $eid, 'mid' => $mid]),
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
      );

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
        $form['pager']['page'.$p] = Link::createFromRoute($p, 'simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $p], ['query' => $pageOptions])->toRenderable();
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
    foreach ($form_values["table"] as $mid => $memberLine) {
      $member = Member::loadMember($mid);
      if (($memberLine["is_approved"] != $member->is_approved) ||
          ($memberLine["is_approved"] && $memberLine["member_no"] != $member->member_no)) {
        $member->is_approved = $memberLine["is_approved"];
        if ($memberLine["is_approved"]) {
	        if (empty($memberLine["member_no"])) {
	          // No member no specified, so assign next one.
	          $max_member++;
	          $member->member_no = $max_member;
	        } else {
	          // Member no specified. Adjust next member no.
	          $member->member_no = $memberLine["member_no"];
	          if ($member->member_no > $max_member)
	            $max_member = $member->member_no;
	        }
	      } else {
	        // No member number for unapproved members.
	        $member->member_no = 0;
	      }
        $return = $member->saveMember();
      }
    }
    \Drupal\Core\Cache\Cache::invalidateTags(['simple-conreg-member-list']);
  }
}    

