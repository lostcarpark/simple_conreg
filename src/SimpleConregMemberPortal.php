<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregMemberPortal
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
use Drupal\Component\Utility\Html;
use Drupal\devel;


/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregMemberPortal extends FormBase {


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
    $upgrades = SimpleConregOptions::memberUpgrades($eid, $config);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $communicationMethods = SimpleConregOptions::communicationMethod($eid, $config, TRUE);
    $pageSize = $config->get('display.page_size');

    $user = \Drupal::currentUser();
    $email = $user->getEmail();

    $form = array(
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $headers = array(
      'member_no' => ['data' => t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'email' => ['data' => t('Email'), 'field' => 'm.email'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'member_type' =>  ['data' => t('Member type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' =>  ['data' => t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Paid'),
      t('Edit'),
    );

    $entries = SimpleConregStorage::adminMemberPortalListLoad($eid, $email, TRUE);

    // Only show table if members found.
    if (count($entries)) {
      $form['table'] = array(
        '#type' => 'table',
        '#header' => $headers,
        '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
        '#empty' => t('No entries available.'),
        '#sticky' => TRUE,
      );      

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
        $row['is_paid'] = array(
          '#markup' => $is_paid ? $this->t('Yes') : $this->t('No'),
        );
        $row['link'] = array(
          '#type' => 'dropbutton',
          '#links' => array(
            'edit_button' => array(
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute ('simple_conreg_portal_edit', ['eid' => $eid, 'mid' => $mid]),
            ),
          ),
        );

        $form['table'][$mid] = $row;
      }
    }

    // Load all members for email address (paid and unpaid)...
    $entries = SimpleConregStorage::adminMemberPortalListLoad($eid, $email);

    // Default to keep unpaid grid hidden.
    $display_unpaid = FALSE;
    $headers = array(
      'payment_type' => ['data' => t('Payment Type')],
      'name' => ['data' => t('Name'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'email' => ['data' => t('Email'), 'field' => 'm.email', 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'detail' =>  ['data' => t('Detail')],
      'price' => ['data' => t('Price'), 'field' => 'm.member_total'],
    );

    $unpaid = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
  
    foreach ($entries as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $is_paid = $entry['is_paid'];
      
      if (!$is_paid) {
        $row = [];
        $row['type'] = ['#markup' => t('Member')];
        $row['name'] = ['#markup' => Html::escape($entry['first_name'] . ' ' . $entry['last_name'])];
        $row['email'] = ['#markup' => Html::escape($entry['email'])];
        $memberType = isset($types->types[trim($entry['member_type'])]->name) ? $types->types[trim($entry['member_type'])]->name : trim($entry['member_type']);
        $row['member_type'] = ['#markup' => Html::escape($memberType)];
        $row['price'] = ['#markup' => Html::escape($entry['member_price'])];
        $unpaid[$mid] = $row;
        $display_unpaid = TRUE;
      }
      
      foreach (SimpleConregAddonStorage::loadAll(['mid' =>$mid, 'is_paid' => 0]) as $addon) {
        $row = [];
        $row['type'] = ['#markup' => t('Add-on')];
        $row['name'] = ['#markup' => Html::escape($entry['first_name'] . ' ' . $entry['last_name'])];
        $row['email'] = ['#markup' => Html::escape($entry['email'])];
        $row['member_type'] = ['#markup' => Html::escape($addon['addon_name'] . (isset($addon['addon_option']) ? ' - ' . $addon['addon_option'] : ''))];
        $row['price'] = ['#markup' => Html::escape($addon['addon_amount'])];
        $unpaid['addon_'.$addon['addonid']] = $row;
        $display_unpaid = TRUE;
      }
    }

    // Only show table if unpaid members found.
    if ($display_unpaid) {
      $form['unpaid_title'] = array(
        '#markup' => $this->t('Unpaid Members and Add-ons'),
        '#prefix' => '<h2>',
        '#suffix' => '</h2>',
      );  
    
      $form['unpaid'] = $unpaid;
    }

/*
    $form['add_members_button'] = array(
      '#type' => 'submit',
      '#value' => t('Add Members'),
      '#attributes' => array('id' => "addBtn"),
      '#validate' => array(),
      '#submit' => array('::addMembers'),
    );
*/
    $form['card'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Pay Credit Card'),
      '#submit' => [[$this, 'payCard']],
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
    $form_state->setRedirect('simple_conreg_portal_register',
      array('eid' => $eid)
    );
  }
  
  /***
   * Get the Member ID of the currently logged in user.
   */
  private function getUserLeadMid($eid)
  {
    $user = \Drupal::currentUser();
    $user_email = $user->getEmail();
    if ($member = SimpleConregStorage::load(['eid' => $eid, 'email' => $user_email])) {
      return $member['mid'];
    }
  }
    
  
  // If any member upgrades selected, save them so they can be charged.
  public function saveUpgrades($eid, $form_values, &$upgrade_price, SimpleConregPayment &$payment)
  {
    $mgr = new SimpleConregUpgradeManager($eid);

    $lead_mid = $this->getUserLeadMid($eid); // Get lead MID from .
    foreach ($form_values["table"] as $mid => $memberRow) {
      $upgrade = new SimpleConregUpgrade($eid, $mid, $memberRow["member_type"], $lead_mid);
      // Only save upgrade if price is not null.
      if (isset($upgrade->upgradePrice)) {
        $mgr->Add($upgrade);
        $member = SimpleConregStorage::load(['mid' => $mid]);
        $payment->add(new SimpleConregPaymentLine($mid,
                                                  'upgrade',
                                                  t("Upgrade for @first_name @last_name", array('@first_name' => $member['first_name'], '@last_name' => $member['last_name'])),
                                                  $upgrade->upgradePrice));
      }
    }
    $upgrade_price = $mgr->getTotalPrice();
    $lead_mid = $mgr->saveUpgrades();
    return $lead_mid;
  }
  
  // Callback for "Pay by Card" button. Sets up members to be paid and transfers to Credit Card form.
  public function payCard(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $form_values = $form_state->getValues();

    // Create a payment.
    $payment = new SimpleConregPayment();

    // Save any member upgrades.
    $upgrade_price = 0;
    $lead_mid = self::saveUpgrades($eid, $form_values, $upgrade_price, $payment);

    $payment_amount = 0;
    // Loop through selected members to get lead and total price.
    foreach ($form_values['unpaid'] as $mid => $member) {
      if (isset($member['is_selected']) && $member['is_selected']) {
        if ($member = SimpleConregStorage::load(['mid' => $mid])) {
          // Make first member lead member.
          if (empty($lead_mid))
            $lead_mid = $mid;
          $payment_amount += $member['member_price'];
        }
      }
    }

    // Get the user email address.
    $user = \Drupal::currentUser();
    $email = $user->getEmail();

    // Load all members for email address (paid and unpaid)...
    $entries = SimpleConregStorage::adminMemberPortalListLoad($eid, $email);

    foreach ($entries as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $is_paid = $entry['is_paid'];
      
      // If member unpaid, add to payment.
      if (!$is_paid) {
        $payment->add(new SimpleConregPaymentLine($mid,
                                                  'member',
                                                  t("Member registration for @first_name @last_name", array('@first_name' => $entry['first_name'], '@last_name' => $entry['last_name'])),
                                                  $entry['member_price']));
        $payment_amount += $entry['member_price'];
      }
      
      // Loop through unpaid upgrades for member, and add those to payment.
      foreach (SimpleConregAddonStorage::loadAll(['mid' =>$mid, 'is_paid' => 0]) as $addon) {
        $payment->add(new SimpleConregPaymentLine($mid,
                                                 'addon',
                                                 t("Add-on @add_on for @first_name @last_name",
                                                    ['@add_on' => $addon['addon_name'],
                                                    '@first_name' => $entry['first_name'],
                                                    '@last_name' => $entry['last_name']]),
                                                 $addon['addon_amount']));
        // Update addon to set the payment ID.
        SimpleConregAddonStorage::update(['addonid' => $addon['addonid'], 'payid' => $payment->getId()]);
        $payment_amount += $addon['addon_amount'];
      }
    }

    // Assuming there are members/upgrades to pay for, redirect to payment form.
    if ($payment_amount > 0 || $upgrade_price > 0) {
      // Get the Lead Member key...
      //$member = SimpleConregStorage::load(['mid' => $lead_mid]);
      //$lead_key = $member['random_key'];
      // Redirect to payment form.
      $payment->save();
      $form_state->setRedirect('simple_conreg_portal_checkout',
        ['payid' => $payment->payId, 'key' => $payment->randomKey]
      );
    }
    else {
      \Drupal::messenger()->addMessage(t('Nothing to pay for.'), 'warning');
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
}    

