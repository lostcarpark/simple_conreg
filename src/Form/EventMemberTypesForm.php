<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\Form\EventMemberTypesForm
 */
namespace Drupal\simple_conreg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregOptions;

/**
 * Configure simple_conreg settings for this site.
 */
class EventMemberTypesForm extends ConfigFormBase {
  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_config_membertypes';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'simple_conreg.membertypes',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
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

    $memberTypes = $form_state->get('member_types');
    if (!isset($memberTypes)) {
      $memberTypes = SimpleConregOptions::memberTypes($eid);
      $form_state->set('member_types', $memberTypes);
    }

    $cloneMemberTypeID = $form_state->get('clone_member_type_id');
    if (!empty($cloneMemberTypeID)) {
      return $this->buildCloneForm($form, $form_state, $cloneMemberTypeID, $memberTypes);
    }

    $deleteMemberTypeID = $form_state->get('delete_member_type_id');
    if (!empty($deleteMemberTypeID)) {
      return $this->buildDeleteForm($form, $form_state, $deleteMemberTypeID, $memberTypes);
    }

    $badgeTypes = SimpleConregOptions::badgeTypes($eid);
    $memberClasses = SimpleConregOptions::memberClasses($eid);
    $days = SimpleConregOptions::days($eid);

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-simple_conreg_event',
    );

    foreach ($memberTypes->types as $typeRef => $type) {
      $typeName = isset($type->name) ? $type->name : $typeRef;
      $form[$typeRef] = [
        '#type' => 'details',
        '#title' => $typeName,
        '#tree' => TRUE,
        '#group' => 'admin',
      ];

      $form[$typeRef]['type'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Member Type Details'),
      ];
      
      $form[$typeRef]['type']['code'] = [
        '#type' => 'markup',
        '#prefix' => $this->t('<div><label>Type code</label>'),
        '#suffix' => '</div>',
        '#markup' => $typeRef,
      ];
      $form[$typeRef]['type']['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type name'),
        '#default_value' => $type->name,
        '#required' => TRUE,
      ];
      $form[$typeRef]['type']['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type description'),
        '#default_value' => $type->description,
        '#required' => TRUE,
      ];
      $form[$typeRef]['type']['price'] = [
        '#type' => 'number',
        '#title' => $this->t('Price'),
        '#default_value' => $type->price,
        '#required' => TRUE,
      ];
      $form[$typeRef]['type']['badgeType'] = [
        '#type' => 'select',
        '#title' => $this->t('Badge type'),
        '#options' => $badgeTypes,
        '#default_value' => $type->badgeType,
        '#required' => TRUE,
      ];
      $form[$typeRef]['type']['memberClass'] = [
        '#type' => 'select',
        '#title' => $this->t('Member class'),
        '#options' => $memberClasses->options,
        '#default_value' => $type->memberClass,
        '#required' => TRUE,
      ];
      $form[$typeRef]['type']['allowFirst'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('May be first member (if unchecked, member type will not be available for first member on registration form).'),
        '#default_value' => $type->allowFirst,
      ];
      $form[$typeRef]['type']['active'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Active (if unchecked, member type will be hidden on registration form, but may be assigned by administrators).'),
        '#default_value' => $type->active,
      ];
      $form[$typeRef]['type']['defaultDays'] = [
        '#type' => 'select',
        '#title' => $this->t('Default days option'),
        '#options' => $days,
        '#default_value' => $type->defaultDays,
        '#required' => TRUE,
      ];
    
      $form[$typeRef]['days'] = $this->buildDaysTable($typeRef, $type->days, $days);

      $form[$typeRef]['upgrades'] = $this->buildUpgradePathTable($typeRef, $type->upgrades, $memberTypes, $badgeTypes, $days);

      $form[$typeRef]['clone'] = array(
        '#type' => 'submit',
        '#value' => t('Clone @name', ['@name' => $typeName]),
        '#submit' => [[$this, 'cloneSubmit']],
        '#attributes' => array('id' => "submitBtn"),
      );

      $form[$typeRef]['delete'] = array(
        '#type' => 'submit',
        '#value' => t('Delete @name', ['@name' => $typeName]),
        '#submit' => [[$this, 'deleteSubmit']],
        '#attributes' => array('id' => "submitBtn"),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  //
  // Set up markup fields to display check-in confirm.
  //
  public function buildCloneForm($form, FormStateInterface $form_state, $cloneMemberTypeID, $memberTypes)
  {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone Member Type'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $typeName = isset($memberTypes->types[$cloneMemberTypeID]->name) ? $memberTypes->types[$cloneMemberTypeID]->name : $cloneMemberTypeID;
    $form['clone_from'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone from: @type', ['@type' => $typeName]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['clone_to_code'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('New type code'),
      '#required' => TRUE,
    );
    $form['clone_to_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('New type name'),
      '#required' => TRUE,
    );
    $form['confirm'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Confirm Clone'),
      '#submit' => [[$this, 'confirmCloneSubmit']],
    );  
    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelAction']],
    );
    return parent::buildForm($form, $form_state);
  }

  //
  // Set up markup fields to display check-in confirm.
  //
  public function buildDeleteForm($form, FormStateInterface $form_state, $deleteMemberTypeID, $memberTypes)
  {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete Member Type'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $typeName = isset($memberTypes->types[$deleteMemberTypeID]->name) ? $memberTypes->types[$deleteMemberTypeID]->name : $deleteMemberTypeID;
    $form['to_delete'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete member type: @type', ['@type' => $typeName]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['confirm'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Confirm Delete'),
      '#submit' => [[$this, 'confirmDeleteSubmit']],
    );  
    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelAction']],
    );
    return parent::buildForm($form, $form_state);
  }

  /** 
   * Build table for days table.
   */
  public function buildDaysTable($typeRef, $typeDays, $days) {

    $daysForm = [
      '#type' => 'fieldset',
      '#title' => $this->t('Days'),
    ];

    $headers = array(
      'day' => ['data' => t('Day')],
      'enable' => ['data' => t('Enable')],
      'description' => ['data' => t('Description')],
      'price' => ['data' => t('Price')],
    );

    $daysForm['daysTable']  = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'conreg-member-type-'.$type.'-days'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );

    foreach ($days as $dayRef => $day) {
      $row = [];
      $row['day'] = array(
        '#markup' => Html::escape($day),
      );
      $row["enable"] = array(
        '#type' => 'checkbox',
        '#title' => t('Enable'),
        '#default_value' => isset($typeDays[$dayRef]) ? TRUE : FALSE,
      );
      $row["description"] = array(
        '#type' => 'textfield',
        '#default_value' => $typeDays[$dayRef]->description,
        '#size' => 10,
      );
      $row["price"] = array(
        '#type' => 'number',
        '#default_value' => $typeDays[$dayRef]->price,
      );
      $daysForm['daysTable'][$dayRef] = $row;
    }
    
    return $daysForm;
  }

  /** 
   * Build table for upgrade paths table.
   */
  public function buildUpgradePathTable($typeRef, $upgrades, $memberTypes, $badgeTypes, $days) {

    $upgradeForm = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upgrades from @type', ['@type' => $memberTypes->types[$typeRef]->name]),
    ];

    $toTypes = [0 => '<None>'];
    foreach ($memberTypes->types as $toRef => $toType) {
      if ($typeRef != $toRef) {
        $toTypes[$toRef] = $toType->name;
      }
    }
    
    $badgeTypes = array_merge([0 => '<None>'], $badgeTypes);

    $headers = array(
      'from_days' => ['data' => t('From Days')],
      'to_type' => ['data' => t('To Type')],
      'to_days' => ['data' => t('To Days')],
      'new_badge' => ['data' => t('New Badge Type')],
      'description' => ['data' => t('Description')],
      'price' => ['data' => t('Price')],
    );

    $upgradeForm['upgradeTable']  = array(
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => array('id' => 'conreg-member-type-'.$type.'-days'),
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );

    /*
    foreach ($days as $dayRef => $day) {
      $row = [];
      $row['day'] = array(
        '#markup' => Html::escape($day),
      );
      $row["enable"] = array(
        '#type' => 'checkbox',
        '#title' => t('Enable'),
        '#default_value' => isset($typeDays[$dayRef]) ? TRUE : FALSE,
      );
      $row["description"] = array(
        '#type' => 'textfield',
        '#default_value' => $typeDays[$dayRef]->description,
        '#size' => 10,
      );
      $row["price"] = array(
        '#type' => 'number',
        '#default_value' => $typeDays[$dayRef]->price,
      );
      $upgradeForm['upgradeTable'][$dayRef] = $row;
    }
    */
    
    $row = [
      'from_days' => ['#type' => 'checkboxes', '#options' => $days],
      'to_type' => ['#type' => 'select', '#options' => $toTypes],
      'to_days' => ['#type' => 'checkboxes', '#options' => $days],
      'new_badge' => ['#type' => 'select', '#options' => $badgeTypes],
      'description' => ['#type' => 'textfield', '#default_value' => $typeDays[$dayRef]->description, '#size' => 15],
      'price' => ['#type' => 'number', '#default_value' => $typeDays[$dayRef]->price, '#size' => 10],
    ];
    $upgradeForm['upgradeTable']['new'] = $row;
    
    return $upgradeForm;
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $memberTypes = $form_state->get('member_types');

    $vals = $form_state->getValues();
dpm($vals);
    $this->updateMemberTypes($memberTypes, $vals);
    SimpleConregOptions::saveMemberTypes($eid, $memberTypes);

    parent::submitForm($form, $form_state);
  }

  public function cloneSubmit(array &$form, FormStateInterface $form_state)
  {
    $memberTypes = $form_state->get('member_types');

    // Get the parent of the button that was triggered.
    $cloneMemberTypeID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('clone_member_type_id', $cloneMemberTypeID);

    // Update the member types stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberTypes($memberTypes, $vals);
    $form_state->set('member_types', $memberTypes);

    $form_state->setRebuild();
  }

  public function confirmCloneSubmit(array &$form, FormStateInterface $form_state)
  {
    $memberTypes = $form_state->get('member_types');

    // Get source type.
    $cloneMemberTypeID = $form_state->get('clone_member_type_id');

    // Update the 
    $vals = $form_state->getValues();
    $cloneTo = $vals['clone_to_code'];
    if (array_key_exists($cloneTo, $memberTypes->types)) {
      \Drupal::messenger()->addMessage($this->t('@type already exists. Choose a different name.', ['@type' => $cloneTo]), 'error');
    }
    else {
      $memberTypes->types[$cloneTo] = clone $memberTypes->types[$cloneMemberTypeID];
      $memberTypes->types[$cloneTo]->name = $vals['clone_to_name'];
      $memberTypes->options[$cloneTo] = $vals['clone_to_name'];
      $form_state->set('clone_member_type_id', NULL);
    }
    $form_state->set('member_types', $memberTypes);

    $form_state->setRebuild();
  }

  public function deleteSubmit(array &$form, FormStateInterface $form_state)
  {
    $memberTypes = $form_state->get('member_types');

    // Get the parent of the button that was triggered.
    $deleteMemberTypeID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('delete_member_type_id', $deleteMemberTypeID);

    // Update the member types stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberTypes($memberTypes, $vals);
    $form_state->set('member_types', $memberTypes);

    $form_state->setRebuild();
  }

  public function confirmDeleteSubmit(array &$form, FormStateInterface $form_state)
  {
    // Get source type.
    $deleteMemberTypeID = $form_state->get('delete_member_type_id');

    // Load member types from form state, delete type, write back to form state.
    $memberTypes = $form_state->get('member_types');
    unset($memberTypes->types[$deleteMemberTypeID]);
    unset($memberTypes->options[$deleteMemberTypeID]);
    $form_state->set('member_types', $memberTypes);
    
    // Deletion complete, so remove deletion flag from form state.
    $form_state->set('delete_member_type_id', NULL);

    $form_state->setRebuild();
  }

  public function cancelAction(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('clone_member_type_id', NULL);
    $form_state->set('delete_member_type_id', NULL);

    $form_state->setRebuild();
  }

  private function updateMemberTypes(&$memberTypes, $vals)
  {
    foreach ($memberTypes->types as $typeCode => $type) {
      foreach ($vals[$typeCode]['type'] as $fieldName => $val) {
        $memberTypes->types[$typeCode]->$fieldName = $val;
      }
      foreach ($vals[$typeCode]['days']['daysTable'] as $dayRef => $dayVals) {
        if ($dayVals['enable']) {
          $memberTypes->types[$typeCode]->days[$dayRef]->description = $dayVals['description'];
          $memberTypes->types[$typeCode]->days[$dayRef]->price = $dayVals['price'];
          $memberTypes->types[$typeCode]->dayOptions[$dayRef] = $dayVals['description'];
        }
        else {
          unset($memberTypes->types[$typeCode]->days[$dayRef]);
          unset($memberTypes->types[$typeCode]->dayOptions[$dayRef]);
        }
      }
    }
  }
}
