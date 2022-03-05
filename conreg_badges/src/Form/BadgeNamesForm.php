<?php

/**
 * @file
 * Contains \Drupal\conreg_badges\Form\BadgeNamesForm
 */

namespace Drupal\conreg_badges\Form;

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
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\SimpleConregStorage;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class BadgeNamesForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormID()
  {
    return 'simple_conreg_badge_names';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $selection = 0)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = SimpleConregConfig::getConfig($eid);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $digits = $config->get('member_no_digits');

    $form = [];

    $form['message'] = [
      '#markup' => $this->t('Here is a list of all paid convention members...'),
    ];

    $rows = [];
    $headers = [
      'member_no' => ['data' => $this->t('Member no'), 'field' => 'm.member_no', 'sort' => 'asc'],
      'first_name' => ['data' => $this->t('First name'), 'field' => 'm.first_name'],
      'last_name' => ['data' => $this->t('Last name'), 'field' => 'm.last_name'],
      'badge_name' => ['data' => $this->t('Badge name'), 'field' => 'm.badge_name'],
      'badge_type' => ['data' => $this->t('Badge type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
      'days' => ['data' => $this->t('Days'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
    ];

    foreach ($entries = SimpleConregStorage::adminMemberBadges($eid) as $entry) {
      $row = [];
      $row['member_no'] =
        empty($entry['member_no']) ? 
        "" :
        $entry['badge_type'] . sprintf("%0" . $digits . "d", $entry['member_no']);
      $row['first_name'] = $entry['first_name'];
      $row['last_name'] = $entry['last_name'];
      $row['badge_name'] = $entry['badge_name'];
      $row['badge_type'] = isset($badgeTypes[$entry['badge_type']]) ? $badgeTypes[$entry['badge_type']] : $entry['badge_type'];
      $dayDescs = [];
      if (!empty($entry['days'])) {
        foreach (explode('|', $entry['days']) as $day) {
          $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
        }
      }
      $row['days'] = implode(', ', $dayDescs);
      // Sanitize each entry.
      $rows[] = $row;
    }
    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];
    // Don't cache this page.
    $form['#cache']['max-age'] = 0;

    return $form;
  }

  // Callback function for "display" drop down.
  public function updateDisplayCallback(array $form, FormStateInterface $form_state)
  {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }

}
