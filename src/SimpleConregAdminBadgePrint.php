<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\DBTNExampleAddForm
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminBadgePrint extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_badges';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
    $config = SimpleConregConfig::getConfig($eid);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $digits = $config->get('member_no_digits');

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    
    $max_num_badges = 10;
    if (isset($form_values['view']['max_num_badges']))
      $max_num_badges = $form_values['view']['max_num_badges'];

    $form['#attached'] = [
      'library' => ['simple_conreg/conreg_badges'],
    ];
    $form['#tree'] = TRUE;
    $form['view'] = [
      '#prefix' => '<div id="badge-form">',
      '#suffix' => '</div>',
    ];
    $form['view']['by_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select by date updated'),
    ];
    $form['view']['date_from'] = [
      '#type' => 'datetime',
      '#title' => $this->t('From'),
    ];
    $form['view']['date_to'] = [
      '#type' => 'datetime',
      '#title' => $this->t('To'),
    ];

    $form['view']['by_member_no'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select by member number'),
    ];
    $form['view']['member_no_from'] = [
      '#type' => 'number',
      '#title' => $this->t('From'),
    ];
    $form['view']['member_no_to'] = [
      '#type' => 'number',
      '#title' => $this->t('To'),
    ];

    $form['view']['by_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select by name'),
    ];

    $form['view']['max_badges'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit maximum number of badges'),
      '#default_value' => TRUE,
    ];
    $form['view']['max_num_badges'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit number of badges printed to'),
      '#default_value' => $max_num_badges,
    ];

    $form['view']['update'] = [
      '#type' => 'button',
      '#value' => $this->t('Update'),
    ];
    foreach(SimpleConregStorage::adminMemberBadges($eid, $max_num_badges) as $member) {
      $badge_type = isset($badgeTypes[$member['badge_type']]) ? $badgeTypes[$member['badge_type']] : $member['badge_type'];
      $member_no = $member['badge_type'] . sprintf("%0".$digits."d", $member['member_no']);
      if (!empty($member['days'])) {
        $dayDescs = [];
        foreach(explode('|', $member['days']) as $day) {
          $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
        }
        $member_days = implode(', ', $dayDescs);
      }      
      $form['member'.$member['mid']] = [
        '#markup' => 
'<div id="mid'.$member['mid'].'" class="badge">
  <div class="badge-side badge-left">
    <div class="badge-type">'.$badge_type.'</div>
    <div class="badge-number">'.$member_no.'</div>
    <div class="badge-name">'.$member['badge_name'].'</div>
    <div class="badge-days">'.$member_days.'</div>
  </div><div class="badge-side badge-right">
    <div class="badge-type">'.$badge_type.'</div>
    <div class="badge-number">'.$member_no.'</div>
    <div class="badge-name">'.$member['badge_name'].'</div>
    <div class="badge-days">'.$member_days.'</div>
  </div>
</div>',
      ];
    }
    return $form;
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
