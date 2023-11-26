<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberAddOns extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_admin_member_options';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $selection = 0) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    // Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.' . $eid);

    $addons = $config->get('add-ons');
    $options = [0 => 'All'];
    foreach ($addons as $key => $val) {
      if ($val['addon']['active'] ?? FALSE) {
        $options[$key] = $val['free']['label'] ?: $val['addon']['label'] ?? '';
      }
    }

    $tempstore = \Drupal::service('tempstore.private')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['selAddOn'])) {
      $selection = $form_values['selAddOn'];
    }

    if (empty($selection) || !array_key_exists($selection, $options)) {
      // If still no display specified, or invalid option, default to first key in displayOptions.
      $selection = key($options);
    }

    $tempstore->set('adminMemberSelectedAddOn', $selection);
    $form = [
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#prefix' => '<div id="addonform">',
      '#suffix' => '</div>',
    ];

    $form['selAddOn'] = [
      '#type' => 'select',
      '#title' => $this->t('Select member add-on'),
      '#options' => $options,
      '#default_value' => $selection,
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => 'addonform',
        'callback' => [$this, 'updateDisplayCallback'],
        'event' => 'change',
      ],
    ];

    $memberAddOns = SimpleConregAddonStorage::loadAddOnReport($eid, $selection);

    $rows = [];
    $headers = [
      $this->t('Member No'),
      $this->t('First Name'),
      $this->t('Last Name'),
      $this->t('email'),
      $this->t('Add-on Name'),
      $this->t('Add-on Option'),
      $this->t('Add-on Detail'),
      $this->t('Add-on Amount'),
      $this->t('Payment Ref'),
    ];

    $total = 0;

    foreach ($memberAddOns as $entry) {
      $total += $entry['addon_amount'];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }

    $rows[] = ['', '', '', '', t('Total'), '', '', number_format($total, 2), ''];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    ];
    // Don't cache this page.
    $form['#cache']['max-age'] = 0;

    return $form;
  }

  /**
   * Callback function for "display" drop down.
   */
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
