<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMemberDelete
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\node\NodeInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberDelete extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_delete';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $mid = NULL) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $memberPrices = array();

    $config = $this->config('simple_conreg.settings.'.$eid);

    if (isset($mid)) {    
      $member = SimpleConregStorage::load(['eid' => $eid, 'mid' => $mid, 'is_deleted' => 0]);
    } else {
      $member = [];
    }

    // Check member exists.
    if (count($member) < 3) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Member not found. Please confirm member valid.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return parent::buildForm($form, $form_state);
    }

    $form = array(
      '#tree' => TRUE,
      '#prefix' => '<div id="deleteform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_form')
      ),
    );

    $form['member'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Member details'),
    );

    $form['member']['is_approved'] = array(
      '#markup' => $this->t('Approved: @approved', ['@approved' => ($member['is_approved'] ? $this->t('Yes') : $this->t('No'))]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['member_no'] = array(
      '#markup' => $this->t('Member number: @member_no', ['@member_no' => ($member['member_no'] ? $member['member_no'] : '')]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['first_name'] = array(
      '#markup' => $this->t('First Name: @first_name', ['@first_name' => $member['first_name']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['last_name'] = array(
      '#markup' => $this->t('Last Name: @last_name', ['@last_name' => $member['last_name']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['badge_name'] = array(
      '#markup' => $this->t('Badge Name: @badge_name', ['@badge_name' => $member['badge_name']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['is_paid'] = array(
      '#markup' => $this->t('Paid: @is_paid', ['@is_paid' => ($member['is_paid'] ? $this->t('Yes') : $this->t('No'))]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $methods = SimpleConregOptions::paymentMethod();
    $member_method = (isset($methods[$member['payment_method']]) ? $methods[$member['payment_method']] : '');
    $form['member']['payment_method'] = array(
      '#markup' => $this->t('Payment method: @payment_method', ['@payment_method' => $member_method]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['member_price'] = array(
      '#markup' => $this->t('Price: @member_price', ['@member_price' => $member['member_price']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['payment_id'] = array(
      '#markup' => $this->t('Payment reference: @payment_id', ['@payment_id' => $member['payment_id']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['comment'] = array(
      '#markup' => $this->t('Comment: @comment', ['@comment' => $member['comment']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Delete member'),
    );

    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#submit' => [[$this, 'submitCancel']],
    );

    $form_state->set('mid', $mid);
    return $form;
  }

  /*
   * Submit handler for cancel button.
   */

  public function submitCancel(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    // Get session state to return to correct page.
    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    $display = $tempstore->get('display');
    $page = $tempstore->get('page');
    // Redirect to member list.
    $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
  }

  /*
   * Submit handler for member edit form.
   */

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $mid = $form_state->get('mid');
    
    // Save the submitted entry, setting is_deleted to 1.
    if (isset($mid)) {
      $entry = array(
        'is_deleted' => 1,
        'mid' => $mid,
      );
      // Update the member record.
      $return = SimpleConregStorage::update($entry);
    }
    
    // Get session state to return to correct page.
    $tempstore = \Drupal::service('user.private_tempstore')->get('simple_conreg');
    $display = $tempstore->get('display');
    $page = $tempstore->get('page');
    // Redirect to member list.
    $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
  }
}
