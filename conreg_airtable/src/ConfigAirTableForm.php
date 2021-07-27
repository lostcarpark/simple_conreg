<?php
/**
 * @file
 * Contains \Drupal\conreg_airtable\ConfigAirTableForm
 */
namespace Drupal\conreg_airtable;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\FieldOptions;
use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class ConfigAirTableForm extends ConfigFormBase
{
  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'conreg_config_clickup_options';
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

    $config = SimpleConregConfig::getConfig($eid);

    $fieldOptions = FieldOptions::getFieldOptions($eid);

    $api_url = $config->get('airtable.api_url');
    $api_key = $config->get('airtable.api_key');

    $form['#attached'] = [
      'library' => ['simple_conreg/conreg_admin'],
    ];

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-new_addon',
    );

    $form['airtable'] = array(
      '#type' => 'details',
      '#title' => $this->t('AirTable Details'),
      '#group' => 'admin',
      '#weight' => -100,
    );

    $form['airtable']['airtable_authenticate'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('AirTable Authorization'),
      '#tree' => TRUE,
    );

    $form['airtable']['airtable_authenticate']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('To connect AirTable, fill out API URL for the table and API Key below.'),
    );

    $form['airtable']['airtable_authenticate']['api_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#default_value' => $api_url,
    );

    $form['airtable']['airtable_authenticate']['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $api_key,
    );

    if (!empty($api_url) && !empty($api_key)) {
      $form['airtable']['airtable_test'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('AirTable Connection Test'),
        '#tree' => TRUE,
      );

      try {
        $form['airtable']['airtable_test']['test_output'] = array(
          '#type' => 'markup',
          '#prefix' => '<div class="conreg_info">',
          '#suffix' => '</div>',
          '#markup' => 'Test output: ' . AirTable::test($eid),
        );
      }
      catch (Exception $e) {
        $form['airtable']['airtable_test']['test_output'] = array(
          '#type' => 'markup',
          '#prefix' => '<div class="conreg_info">',
          '#suffix' => '</div>',
          '#markup' => 'Test error: ' . $e->getMessage(),
        );
      }
    }

    /**
     * Field mappings for members.
     */

    $form['mapping'] = array(
      '#type' => 'details',
      '#title' => $this->t('Field mappings'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );
    
    $form['mapping']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => 'Set the names of AirTable fields that ConReg values will map to. Leave any field blank to exclude field from AirTable.',
    );
    
    $fields = ['mid',
               'member_no',
               'member_type',
               'days',
               'communication_method',
               'is_approved',
               'first_name',
               'last_name',
               'badge_name',
               'badge_type',
               'display',
               'email',
               'street',
               'street2',
               'city',
               'county',
               'postcode',
               'country',
               'phone',
               'birth_date',
               'age',
               'member_price',
               'member_total',
               'is_paid',
               'payment_method',
               'payment_id',
               'comment',
               'extra_flag1',
               'extra_flag2',
               'join_date',
               'update_date'];

    foreach ($fields as $field) {
      $form['mapping'][$field] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Column to map "@field" to', ['@field' => $field]),
        '#default_value' => $config->get('airtable.mappings.'.$field),
      );
    }


    /**
     * Field mappings for option groups.
     */

    $form['option_groups'] = array(
      '#type' => 'details',
      '#title' => $this->t('Option Groups'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['option_groups']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Option groups may be mapped to AitTable columns. All options selected will be inserted in the column, but if details are entered, they will not. It is recommended to use Option Fields for options with details.'),
    );
    
    foreach ($fieldOptions->groups as $group) {
      $form['option_groups'][$group->groupId] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Column to map "@group" to', ['@group' => $group->title]),
        '#default_value' => $config->get('airtable.option_groups.'.$group->groupId),
      );
    }


    /**
     * Field mappings for option fields.
     */

    $form['option_fields'] = array(
      '#type' => 'details',
      '#title' => $this->t('Option Fields'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['option_fields']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Option fields will map an individual option and it\'s details to an AirTable column.'),
    );

    foreach ($fieldOptions->options as $option) {
      $form['option_fields'][$option->optionId] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Column to map "@group" to', ['@group' => $option->title]),
        '#default_value' => $config->get('airtable.option_fields.'.$option->optionId),
      );
    }


    /**
     * Field mappings for members.
     */

    $form['bulk'] = array(
      '#type' => 'details',
      '#title' => $this->t('Bulk add'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['bulk']['info_add'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('This button will add all members who aren\'t already on AirTable. Useful if you add AirTable to a convention with existing members.'),
    );

    $form['bulk']['submit_add'] = array(
      '#type' => 'submit',
      '#value' => t('Bulk add missing members'),
      '#submit' => [[$this, 'submitBulkAdd']],
      '#attributes' => array('id' => "submitBulkAdd"),
    );

    $form['bulk']['info_update'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('This button will update details for all members on AirTable. Useful if you add extra columns to an AirTable and need to populate them.'),
    );

    $form['bulk']['submit_update'] = array(
      '#type' => 'submit',
      '#value' => t('Bulk update all members'),
      '#submit' => [[$this, 'submitBulkUpdate']],
      '#attributes' => array('id' => "submitBulkUpdate"),
    );

    return parent::buildForm($form, $form_state);
  }

  // Handler for Bulk Add button.

  public function submitBulkAdd(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $vals = $form_state->getValues();

    $connection = \Drupal::database();
    $query = $connection->select('conreg_members', 'm');
    $query->leftJoin('conreg_airtable_members', 'a', 'a.mid = m.mid');
    $query->addField('m', 'mid');
    $query->condition('m.eid', $eid);
    $query->condition('m.is_deleted', 0);
    $query->condition('m.is_paid', 1);
    $query->isNull('a.mid');
    
    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $mids = [];
    foreach ($result as $mid) {
      $mids[] = $mid['mid'];
      if (count($mids) >= 10) {
        AirTable::addMembers($eid, $mids);
        $mids = [];
      }
    }
    if (count($mids)) {
      AirTable::addMembers($eid, $mids);
    }

    $form_state->setRebuild();
  }

  // Handler for Bulk Update button.

  public function submitBulkUpdate(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $vals = $form_state->getValues();

    $connection = \Drupal::database();
    $query = $connection->select('conreg_members', 'm');
    $query->join('conreg_airtable_members', 'a', 'a.mid = m.mid');
    $query->addField('m', 'mid');
    $query->addField('a', 'airtable_id');
    $query->condition('m.eid', $eid);
    $query->condition('m.is_deleted', 0);
    $query->condition('m.is_paid', 1);

    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $airtable_ids = [];
    foreach ($result as $mid) {
      $airtable_ids[$mid['mid']] = $mid['airtable_id'];
      if (count($airtable_ids) >= 10) {
        AirTable::updateMembers($eid, $airtable_ids);
        $airtable_ids = [];
      }
    }
    if (count($airtable_ids)) {
      AirTable::updateMembers($eid, $airtable_ids);
    }

    $form_state->setRebuild();
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $vals = $form_state->getValues();
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    $config->set('airtable.api_url', $vals['airtable_authenticate']['api_url']);
    $config->set('airtable.api_key', $vals['airtable_authenticate']['api_key']);
    foreach ($vals['mapping'] as $key => $val) {
      $config->set('airtable.mappings.'.$key, $val);
    }
    foreach ($vals['option_groups'] as $key => $val) {
      $config->set('airtable.option_groups.'.$key, $val);
    }
    foreach ($vals['option_fields'] as $key => $val) {
      $config->set('airtable.option_fields.'.$key, $val);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}



