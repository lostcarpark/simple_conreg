<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMemberAddOns
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
class SimpleConregAdminMemberAddOns extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_options';
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

    $config = $this->config('simple_conreg.settings.'.$eid);

    $addons = $config->get('add-ons');
    $options = [0 => 'All'];
    foreach ($addons as $key=>$val) {
      $options[$key] = (!empty($val['free']['label']) ? $val['free']['label'] : $val['addon']['label']);
    }

    $tempstore = \Drupal::service('tempstore.private')->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over the passed in values.
    if (isset($form_values['selAddOn']))
      $selection = $form_values['selAddOn'];

    if (empty($selection) || !array_key_exists($selection, $options))
      $selection = key($options); // If still no display specified, or invalid option, default to first key in displayOptions.

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
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ],
    ];
    
    $memberAddOns = SimpleConregAddonStorage::loadAddOnReport($eid, $selection);

    $rows = array();
    $headers = array(
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('email'),
      t('Add-on Name'),
      t('Add-on Option'),
      t('Add-on Detail'),
      t('Add-on Amount'),
      t('Payment Ref'),
    );

    $total = 0;

    foreach ($memberAddOns as $entry) {
      $total += $entry['addon_amount'];
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    
    $rows[] = ['', '', '', '', t('Total'), '', '', number_format($total, 2), ''];
    
    $form['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );
    // Don't cache this page.
    $form['#cache']['max-age'] = 0;

    return $form;
  }

  // Callback function for "display" drop down.
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}    

