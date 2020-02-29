<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregForm
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class SimpleConregConfigAddOnsForm extends ConfigFormBase
{
  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'simple_conreg_config_addons';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'simple_conreg.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    // Fetch event name from Event table.
    if (count($event = SimpleConregEventStorage::load(['eid' => $eid])) < 3) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return parent::buildForm($form, $form_state);
    }

    // Get config for event and fieldset.    
    $config = SimpleConregConfig::getConfig($eid);

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-new_addon',
    );

    $form['new_addon'] = array(
      '#type' => 'details',
      '#title' => $this->t('New Add-On'),
      '#tree' => TRUE,
      '#group' => 'admin',
      '#weight' => -100,
    );

    $form['new_addon']['addon_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Add-on name'),
    );

    /*
     * Placeholder for options for each add-on.
     */
    $form['addons'] = array(
      '#tree' => TRUE,
    );

    /*
     * Loop through each add-on and add to form.
     */

    foreach ($config->get('add-ons') as $addOnId => $addOnVals) {
//    dpm($addOnVals, "Loading $addOnId");
      
      /*
       * Fields for add on choices and options.
       */
      $form['addons'][$addOnId] = array(
        '#type' => 'details',
        '#title' => $addOnId,
        '#group' => 'admin',
        '#weight' => (!empty($addOnVals['weight']) ? $addOnVals['weight'] : 0),
      );

      $form['addons'][$addOnId]['addon'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Add-on Options'),
        '#tree' => TRUE,
      );

      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);

      $form['addons'][$addOnId]['addon']['active'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Active (uncheck to hide option from form)'),
        '#default_value' => (isset($addon['active']) ? $addon['active'] : ''),
      );

      $form['addons'][$addOnId]['addon']['global'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Global add-on (uncheck for add-on per member)'),
        '#default_value' => (isset($addon['global']) ? $addon['global'] : ''),
      );

      $form['addons'][$addOnId]['addon']['label'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => (isset($addon['label']) ? $addon['label'] : ''),
      );  

      $form['addons'][$addOnId]['addon']['description'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => (isset($addon['description']) ? $addon['description'] : ''),
      );  

      $form['addons'][$addOnId]['addon']['options'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Options'),
        '#description' => $this->t('Put each option on a line with name, description and price separated by | character (e.g. "tshirt|Include a T-shirt|10").'),
        '#default_value' => (isset($addon['options']) ? $addon['options'] : ''),
      );  

      $form['addons'][$addOnId]['addon']['weight'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Weight'),
        '#default_value' => (isset($addon['weight']) ? $addon['weight'] : '0'),
      );  

      $info = (isset($addOnVals['info']) ? $addOnVals['info'] : []);

      $form['addons'][$addOnId]['info'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Add-on Information'),
        '#tree' => TRUE,
      );

      $form['addons'][$addOnId]['info']['label'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#description' => t('If you would like to capture optional information about the add-on, please provide label and description for the information field.'),
        '#default_value' => (isset($info['label']) ? $info['label'] : ''),
      );  

      $form['addons'][$addOnId]['info']['description'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => (isset($info['description']) ? $info['description'] : ''),
      );  

      $free = (isset($addOnVals['free']) ? $addOnVals['free'] : []);

      $form['addons'][$addOnId]['free'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Add-on Free Input Amount'),
        '#tree' => TRUE,
      );

      $form['addons'][$addOnId]['free']['label'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => (isset($free['label']) ? $free['label'] : ''),
      );  

      $form['addons'][$addOnId]['free']['description'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => (isset($free['description']) ? $free['description'] : ''),
      );  
    }

    return parent::buildForm($form, $form_state);
  }

  // Callback function for "fieldset" drop down.
  public function updateFieldsetCallback(array $form, FormStateInterface $form_state) {
    $fieldset = $form_state->getValue(['simple_conreg_fieldsets', 'fieldset']);
    if (empty($fieldset))
      $fieldset = 0;
    $fieldsetContainer = "fieldset_container_$fieldset";
    return $form['simple_conreg_fieldsets'][$fieldsetContainer];
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $vals = $form_state->getValues();

    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    // If new add-on populated, create an empty array.
    if (!empty($vals['new_addon']['addon_name'])) {
      $config->set('add-ons.'.$vals['new_addon']['addon_name'], []);
    }
    // Loop through add-ons and save to config.
    foreach ($vals['addons'] as $addOnId => $addOnVals) {
//    dpm($addOnVals, $addOnId);
      $config->set('add-ons.'.$addOnId.'.addon.active', $addOnVals['addon']['active']);
      $config->set('add-ons.'.$addOnId.'.addon.global', $addOnVals['addon']['global']);
      $config->set('add-ons.'.$addOnId.'.addon.label', $addOnVals['addon']['label']);
      $config->set('add-ons.'.$addOnId.'.addon.description', $addOnVals['addon']['description']);
      $config->set('add-ons.'.$addOnId.'.addon.options', $addOnVals['addon']['options']);
      $config->set('add-ons.'.$addOnId.'.addon.weight', $addOnVals['addon']['weight']);
      $config->set('add-ons.'.$addOnId.'.info.label', $addOnVals['info']['label']);
      $config->set('add-ons.'.$addOnId.'.info.description', $addOnVals['info']['description']);
      $config->set('add-ons.'.$addOnId.'.free.label', $addOnVals['free']['label']);
      $config->set('add-ons.'.$addOnId.'.free.description', $addOnVals['free']['description']);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
