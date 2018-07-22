<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\DBTNExampleAddForm
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\devel;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregMemberPortalForm extends FormBase {

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
    $user = \Drupal::currentUser();
    $email = $user->getEmail();
    $members = SimpleConregStorage::memberPortalLoad($eid, $email);
    
    // Set up form.
    $form = [];
    
    $headers = array(
      'member_no' => ['data' => t('Member No'), 'field' => 'm.member_no'],
      'first_name' => ['data' => t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => t('Last name'), 'field' => 'm.last_name'],
      'badge_name' => ['data' => t('Badge name'), 'field' => 'm.badge_name'],
      'is_paid' =>  ['data' => t('Is paid'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      t('Update'),
    );

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );   

    foreach ($members as $member) {
      $mid = $member['mid'];
      // Sanitize each entry.
      $row = array();
      $row['member_no'] = array(
        '#markup' => SafeMarkup::checkPlain($member['member_no']),
      );
      $row['first_name'] = array(
        '#markup' => SafeMarkup::checkPlain($member['first_name']),
      );
      $row['last_name'] = array(
        '#markup' => SafeMarkup::checkPlain($member['last_name']),
      );
      $row['badge_name'] = array(
        '#markup' => SafeMarkup::checkPlain($member['badge_name']),
      );
      $row['is_paid'] = array(
        '#markup' => $member['is_paid'] ? $this->t('Yes') : $this->t('No'),
      );
      $row['link'] = array(
        '#type' => 'dropbutton',
        '#links' => array(
          'edit_button' => array(
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('simple_conreg_portal_edit', ['eid' => $eid, 'mid' => $mid]),
          ),
        ),
      );
      $form['table'][$mid] = $row;
    }
    
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save Changes'),
      '#attributes' => array('id' => "submitBtn"),
    );
    return $form;
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
  }

}



