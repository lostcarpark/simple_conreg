<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\Form\EventMemberClassesForm
 */
namespace Drupal\simple_conreg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregOptions;

/**
 * Configure simple_conreg settings for this site.
 */
class EventMemberClassesForm extends ConfigFormBase {
  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_config_memberclasses';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'simple_conreg.memberclasses',
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

    $memberClasses = $form_state->get('member_classes');
    if (!isset($memberClasses)) {
      $memberClasses = SimpleConregOptions::memberClasses($eid);
      $form_state->set('member_classes', $memberClasses);
    }
    
    $cloneMemberClassID = $form_state->get('clone_member_class_id');
    if (!empty($cloneMemberClassID)) {
      return $this->buildCloneForm($form, $form_state, $cloneMemberClassID, $memberClasses);
    }

    $deleteMemberClassID = $form_state->get('delete_member_class_id');
    if (!empty($deleteMemberClassID)) {
      return $this->buildDeleteForm($form, $form_state, $deleteMemberClassID, $memberClasses);
    }

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-simple_conreg_event',
    );

    foreach ($memberClasses->classes as $classRef => $class) {
      $className = isset($class->name) ? $class->name : $classRef;
      $form[$classRef] = array(
        '#type' => 'details',
        '#title' => $className,
        '#tree' => TRUE,
        '#group' => 'admin',
      );

      $form[$classRef]['class'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Member Class Details'),
      );
      
      $form[$classRef]['class']['name'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Class name'),
        '#default_value' => $class->name,
        '#required' => TRUE,
      );

      // Field labels.

      $form[$classRef]['labels'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Field Labels'),
      );
      
      $fieldLabels = [
        'first_name' => (object) ['label' => 'First name label (required)', 'type' => 'textfield', 'required' => TRUE],
        'last_name' => (object) ['label' => 'Last name label (required)', 'type' => 'textfield', 'required' => TRUE],
        'name_description' => (object) ['label' => 'Name description (discription to appear under both name fields)', 'type' => 'textarea', 'required' => FALSE],
        'email' => (object) ['label' => 'Email address label (required)', 'type' => 'textfield', 'required' => TRUE],
        'membership_type' => (object) ['label' => 'Type of membership label (required)', 'type' => 'textfield', 'required' => TRUE],
        'membership_type_description' => (object) ['label' => 'Description for membership type field', 'type' => 'textarea', 'required' => FALSE],
        'membership_days' => (object) ['label' => 'Membership days label (required)', 'type' => 'textfield', 'required' => TRUE],
        'membership_days_description' => (object) ['label' => 'Membership days description', 'type' => 'textarea', 'required' => FALSE],
        'badge_name_option' => (object) ['label' => 'Badge name option label (required)', 'type' => 'textfield', 'required' => TRUE],
        'badge_name' => (object) ['label' => 'Badge name label (required)', 'type' => 'textfield', 'required' => TRUE],
        'badge_name_description' => (object) ['label' => 'Badge name description', 'type' => 'textarea', 'required' => FALSE],
        'display' => (object) ['label' => 'Display name on membership list label (leave blank if member type not to be displayed)', 'type' => 'textfield', 'required' => FALSE],
        'display_description' => (object) ['label' => 'Display name on membership list description (description below display name field; if display name blank, this text will be displayed in place of the field)', 'type' => 'textarea', 'required' => FALSE],
        'communication_method' => (object) ['label' => 'Communication method label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'communication_method_description' => (object) ['label' => 'Communication method description (leave empty for no description)', 'type' => 'textarea', 'required' => FALSE],
        'same_address' => (object) ['label' => 'Same address as member 1 label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'street' => (object) ['label' => 'Street address label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'street2' => (object) ['label' => 'Street address line 2 label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'city' => (object) ['label' => 'Town/city label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'county' => (object) ['label' => 'County/state label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'postcode' => (object) ['label' => 'Postal code label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'country' => (object) ['label' => 'Country label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'phone' => (object) ['label' => 'Phone number label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'birth_date' => (object) ['label' => 'Date of birth label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'age' => (object) ['label' => 'Age label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'age_min' => (object) ['label' => 'Minimum age', 'type' => 'number', 'required' => FALSE],
        'age_max' => (object) ['label' => 'Maximum age', 'type' => 'number', 'required' => FALSE],
      ];

      foreach ($fieldLabels as $fieldName => $field) {
        $form[$classRef]['labels'][$fieldName] = array(
          '#type' => $field->type,
          '#title' => $this->t($field->label),
          '#default_value' => isset($class->fields->$fieldName) ? $class->fields->$fieldName : '',
          '#required' => $field->required,
        );
      }
      

      // Mandatory fields.

      $form[$classRef]['mandatory'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Mandatory Fields'),
      );

      $mandatoryLabels = [
        'first_name' => 'First name mandatory',
        'last_name' => 'Last name mandatory',
        'street' => 'Street address mandatory',
        'street2' => 'Street address 2 mandatory',
        'city' => 'Town/City mandatory',
        'county' => 'County/State mandatory',
        'postcode' => 'Postal code mandatory',
        'country' => 'Country mandatory',
        'birth_date' => 'Date of birth mandatory',
        'age' => 'Age mandatory',
      ];

      foreach ($mandatoryLabels as $fieldName => $label) {
        $form[$classRef]['mandatory'][$fieldName] = array(
          '#type' => 'checkbox',
          '#title' => $this->t($label),
          '#default_value' => isset($class->mandatory->$fieldName) ? $class->mandatory->$fieldName : FALSE,
        );
      }

      // Field max lengths.

      $form[$classRef]['max_length'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Maximum Lengths'),
        '#tree' => TRUE,
      );

      $form[$classRef]['max_length']['markup'] = array(
        '#type' => 'markup',
        '#markup' => $this->t('Specify maximum length of input fields. Leave blank for unlimited.'),
      );

      $maxLengthLabels = [
        'first_name' => 'First name maximum length',
        'last_name' => 'Last name maximum length',
        'badge_name' => 'Badge name maximum length',
        'street' => 'Street address maximum length',
        'street2' => 'Street address 2 maximum length',
        'city' => 'Town/City maximum length',
        'county' => 'County/State maximum length',
        'postcode' => 'Postal code maximum length',
      ];

      foreach ($maxLengthLabels as $fieldName => $label) {
        $form[$classRef]['max_length'][$fieldName] = array(
          '#type' => 'number',
          '#title' => $this->t($label),
          '#default_value' => isset($class->max_length->$fieldName) ? $class->max_length->$fieldName : '',
        );
      }

      // Extra flags.

      $form[$classRef]['extras'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Extra Flags'),
        '#tree' => TRUE,
      );

      $extraFlagLabels = [
        'flag1' => 'Extra Flag 1 label',
        'flag2' => 'Extra Flag 2 label',
      ];

      foreach ($extraFlagLabels as $fieldName => $label) {
        $form[$classRef]['extras'][$fieldName] = array(
          '#type' => 'textfield',
          '#title' => $this->t($label),
          '#default_value' => isset($class->extras->$fieldName) ? $class->extras->$fieldName : '',
        );
      }

      $form[$classRef]['clone'] = array(
        '#type' => 'submit',
        '#value' => t('Clone @name', ['@name' => $className]),
        '#submit' => [[$this, 'cloneSubmit']],
        '#attributes' => array('id' => "submitBtn"),
      );

      $form[$classRef]['delete'] = array(
        '#type' => 'submit',
        '#value' => t('Delete @name', ['@name' => $className]),
        '#submit' => [[$this, 'deleteSubmit']],
        '#attributes' => array('id' => "submitBtn"),
      );

    }

dpm($memberClasses, "Classes");

    return parent::buildForm($form, $form_state);
  }

  //
  // Set up markup fields to display check-in confirm.
  //
  public function buildCloneForm($form, FormStateInterface $form_state, $cloneMemberClassID, $memberClasses)
  {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone Member Class'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $className = isset($memberClasses->classes[$cloneMemberClassID]->name) ? $memberClasses->classes[$cloneMemberClassID]->name : $cloneMemberClassID;
    $form['clone_from'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone from: @class', ['@class' => $className]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['clone_to'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name of new member class'),
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
  public function buildDeleteForm($form, FormStateInterface $form_state, $deleteMemberClassID, $memberClasses)
  {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete Member Class'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $className = isset($memberClasses->classes[$deleteMemberClassID]->name) ? $memberClasses->classes[$deleteMemberClassID]->name : $deleteMemberClassID;
    $form['to_delete'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete member class: @class', ['@class' => $className]),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $memberClasses = $form_state->get('member_classes');

    $vals = $form_state->getValues();
    $this->updateMemberClasses($memberClasses, $vals);
    SimpleConregOptions::saveMemberClasses($eid, $memberClasses);

    parent::submitForm($form, $form_state);
  }
  
  public function cloneSubmit(array &$form, FormStateInterface $form_state)
  {
    $memberClasses = $form_state->get('member_classes');

    // Get the parent of the button that was triggered.
    $cloneMemberClassID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('clone_member_class_id', $cloneMemberClassID);

    // Update the member classes stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberClasses($memberClasses, $vals);
    $form_state->set('member_classes', $memberClasses);

    $form_state->setRebuild();
  }

  public function confirmCloneSubmit(array &$form, FormStateInterface $form_state)
  {
    $memberClasses = $form_state->get('member_classes');

    // Get source class.
    $cloneMemberClassID = $form_state->get('clone_member_class_id');

    // Update the 
    $vals = $form_state->getValues();
    $cloneTo = $vals['clone_to'];
    if (array_key_exists($cloneTo, $memberClasses->classes)) {
      \Drupal::messenger()->addMessage($this->t('@class already exists. Choose a different name.', ['@class' => $cloneTo]), 'error');
    }
    else {
      $memberClasses->classes[$cloneTo] = clone $memberClasses->classes[$cloneMemberClassID];
      $memberClasses->classes[$cloneTo]->name = $cloneTo;
      $memberClasses->options[$cloneTo] = $cloneTo;
      $form_state->set('clone_member_class_id', NULL);
    }
    $form_state->set('member_classes', $memberClasses);

    $form_state->setRebuild();
  }

  public function deleteSubmit(array &$form, FormStateInterface $form_state)
  {
    $memberClasses = $form_state->get('member_classes');

    // Get the parent of the button that was triggered.
    $deleteMemberClassID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('delete_member_class_id', $deleteMemberClassID);

    // Update the member classes stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberClasses($memberClasses, $vals);
    $form_state->set('member_classes', $memberClasses);

    $form_state->setRebuild();
  }

  public function confirmDeleteSubmit(array &$form, FormStateInterface $form_state)
  {
    // Get source class.
    $deleteMemberClassID = $form_state->get('delete_member_class_id');

    // Load member classes from form state, delete class, write back to form state.
    $memberClasses = $form_state->get('member_classes');
    unset($memberClasses->classes[$deleteMemberClassID]);
    unset($memberClasses->options[$deleteMemberClassID]);
    $form_state->set('member_classes', $memberClasses);
    
    // Deletion complete, so remove deletion flag from form state.
    $form_state->set('delete_member_class_id', NULL);

    $form_state->setRebuild();
  }

  public function cancelAction(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('clone_member_class_id', NULL);
    $form_state->set('delete_member_class_id', NULL);

    $form_state->setRebuild();
  }

  private function updateMemberClasses(&$memberClasses, $vals)
  {
    foreach ($memberClasses->classes as $classRef => $class) {
      $memberClasses->classes[$classRef]->name = $vals[$classRef]['class']['name'];
      foreach ($class->fields as $fieldName => $oldVal) {
        $memberClasses->classes[$classRef]->fields->$fieldName = $vals[$classRef]['labels'][$fieldName];
      }
      foreach ($class->mandatory as $fieldName => $oldVal) {
        $memberClasses->classes[$classRef]->mandatory->$fieldName = $vals[$classRef]['mandatory'][$fieldName];
      }
      foreach ($class->max_length as $fieldName => $oldVal) {
        $memberClasses->classes[$classRef]->max_length->$fieldName = $vals[$classRef]['max_length'][$fieldName];
      }
      foreach ($class->extras as $fieldName => $oldVal) {
        $memberClasses->classes[$classRef]->extras->$fieldName = $vals[$classRef]['extras'][$fieldName];
      }
    }
  }
}
