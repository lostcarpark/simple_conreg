<?php

namespace Drupal\simple_conreg\Form;

use Drupal\Core\Cache\CacheTagsInvalidator;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure simple_conreg settings for this site.
 */
class EventMemberClassesForm extends ConfigFormBase {

  /**
   * The cache invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidator
   */
  protected CacheTagsInvalidator $cacheInvalidator;

  /**
   * Constructor for member lookup form.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidator $cacheInvalidator
   *   The cache invalidator.
   */
  public function __construct(CacheTagsInvalidator $cacheInvalidator) {
    $this->cacheInvalidator = $cacheInvalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('cache_tags.invalidator')
    );
  }

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
      $form['simple_conreg_event'] = [
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      ];
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

    $form['admin'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-simple_conreg_event',
    ];

    foreach ($memberClasses->classes as $classRef => $class) {
      $className = $class->name ?? $classRef;
      $form[$classRef] = [
        '#type' => 'details',
        '#title' => $className,
        '#tree' => TRUE,
        '#group' => 'admin',
      ];

      $form[$classRef]['class'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Member Class Details'),
      ];

      $form[$classRef]['class']['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Class name'),
        '#default_value' => $class->name,
        '#required' => TRUE,
      ];

      // Field labels.
      $form[$classRef]['labels'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Labels'),
      ];

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
        'country_description' => (object) ['label' => 'Country description (displayed below country. If country optional, should explain how to deselect country)', 'type' => 'textarea', 'required' => FALSE],
        'phone' => (object) ['label' => 'Phone number label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'birth_date' => (object) ['label' => 'Date of birth label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'age' => (object) ['label' => 'Age label (leave empty to remove field)', 'type' => 'textfield', 'required' => FALSE],
        'age_min' => (object) ['label' => 'Minimum age', 'type' => 'number', 'required' => FALSE],
        'age_max' => (object) ['label' => 'Maximum age', 'type' => 'number', 'required' => FALSE],
      ];
      $form_state->set('fieldLabels', $fieldLabels);

      foreach ($fieldLabels as $fieldName => $field) {
        $form[$classRef]['labels'][$fieldName] = [
          '#type' => $field->type,
          '#title' => $this->t($field->label),
          '#default_value' => $class->fields->$fieldName ?? '',
          '#required' => $field->required,
        ];
      }

      // Mandatory fields.
      $form[$classRef]['mandatory'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Mandatory Fields'),
      ];

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
      $form_state->set('mandatoryLabels', $mandatoryLabels);

      foreach ($mandatoryLabels as $fieldName => $label) {
        $form[$classRef]['mandatory'][$fieldName] = [
          '#type' => 'checkbox',
          '#title' => $this->t($label),
          '#default_value' => $class->mandatory->$fieldName ?? FALSE,
        ];
      }

      // Field max lengths.
      $form[$classRef]['max_length'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Maximum Lengths'),
        '#tree' => TRUE,
      ];

      $form[$classRef]['max_length']['markup'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Specify maximum length of input fields. Leave blank for unlimited.'),
      ];

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
      $form_state->set('maxLengthLabels', $maxLengthLabels);

      foreach ($maxLengthLabels as $fieldName => $label) {
        $form[$classRef]['max_length'][$fieldName] = [
          '#type' => 'number',
          '#title' => $this->t($label),
          '#default_value' => $class->max_length->$fieldName ?? '',
        ];
      }

      // Extra flags.
      $form[$classRef]['extras'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Extra Flags'),
        '#tree' => TRUE,
      ];

      $extraFlagLabels = [
        'flag1' => 'Extra Flag 1 label',
        'flag2' => 'Extra Flag 2 label',
      ];

      foreach ($extraFlagLabels as $fieldName => $label) {
        $form[$classRef]['extras'][$fieldName] = [
          '#type' => 'textfield',
          '#title' => $this->t($label),
          '#default_value' => $class->extras->$fieldName ?? '',
        ];
      }

      $form[$classRef]['clone'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clone @name', ['@name' => $className]),
        '#submit' => [[$this, 'cloneSubmit']],
        '#attributes' => ['id' => "submitBtn"],
      ];

      $form[$classRef]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete @name', ['@name' => $className]),
        '#submit' => [[$this, 'deleteSubmit']],
        '#limit_validation_errors' => [],
        '#attributes' => ['id' => "submitBtn"],
      ];

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Set up markup fields to display check-in confirm.
   */
  public function buildCloneForm($form, FormStateInterface $form_state, $cloneMemberClassID, $memberClasses) {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone Member Class'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $className = $memberClasses->classes[$cloneMemberClassID]->name ?? $cloneMemberClassID;
    $form['clone_from'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone from: @class', ['@class' => $className]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['clone_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of new member class'),
      '#required' => TRUE,
    ];
    $form['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm Clone'),
      '#submit' => [[$this, 'confirmCloneSubmit']],
    ];
    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#limit_validation_errors' => [],
      '#submit' => [[$this, 'cancelAction']],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Set up markup fields to display check-in confirm.
   */
  public function buildDeleteForm($form, FormStateInterface $form_state, $deleteMemberClassID, $memberClasses) {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete Member Class'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $className = $memberClasses->classes[$deleteMemberClassID]->name ?? $deleteMemberClassID;
    $form['to_delete'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete member class: @class', ['@class' => $className]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm Delete'),
      '#submit' => [[$this, 'confirmDeleteSubmit']],
    ];
    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#limit_validation_errors' => [],
      '#submit' => [[$this, 'cancelAction']],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $memberClasses = $form_state->get('member_classes');

    $vals = $form_state->getValues();
    $this->updateMemberClasses($memberClasses, $vals, $form_state);
    SimpleConregOptions::saveMemberClasses($eid, $memberClasses);

    parent::submitForm($form, $form_state);
  }

  /**
   * Callback for clone button.
   */
  public function cloneSubmit(array &$form, FormStateInterface $form_state) {
    $memberClasses = $form_state->get('member_classes');

    // Get the parent of the button that was triggered.
    $cloneMemberClassID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('clone_member_class_id', $cloneMemberClassID);

    // Update the member classes stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberClasses($memberClasses, $vals, $form_state);
    $form_state->set('member_classes', $memberClasses);

    $form_state->setRebuild();
  }

  /**
   * Callback for confirm on clone button.
   */
  public function confirmCloneSubmit(array &$form, FormStateInterface $form_state) {
    $memberClasses = $form_state->get('member_classes');

    // Get source class.
    $cloneMemberClassID = $form_state->get('clone_member_class_id');

    // Update the.
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

  /**
   * Callback for delete button.
   */
  public function deleteSubmit(array &$form, FormStateInterface $form_state) {
    $memberClasses = $form_state->get('member_classes');

    // Get the parent of the button that was triggered.
    $deleteMemberClassID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('delete_member_class_id', $deleteMemberClassID);

    // Update the member classes stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberClasses($memberClasses, $vals, $form_state);
    $form_state->set('member_classes', $memberClasses);

    $form_state->setRebuild();
  }

  /**
   * Callback for delete button.
   */
  public function confirmDeleteSubmit(array &$form, FormStateInterface $form_state) {
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

  /**
   * Callback for cancel button.
   */
  public function cancelAction(array &$form, FormStateInterface $form_state) {
    $form_state->set('clone_member_class_id', NULL);
    $form_state->set('delete_member_class_id', NULL);

    $form_state->setRebuild();
  }

  /**
   * Save the new member classes.
   */
  private function updateMemberClasses(&$memberClasses, $vals, $form_state) {
    $eid = $form_state->get('eid');
    $fieldLabels = $form_state->get('fieldLabels');
    $mandatoryLabels = $form_state->get('mandatoryLabels');
    $maxLengthLabels = $form_state->get('maxLengthLabels');

    foreach ($memberClasses->classes as $classRef => $class) {
      $memberClasses->classes[$classRef]->name = $vals[$classRef]['class']['name'];
      foreach ($fieldLabels as $fieldName => $field) {
        $value = $vals[$classRef]['labels'][$fieldName];
        if ($value == '') {
          $memberClasses->classes[$classRef]->fields->$fieldName = NULL;
        }
        elseif ($fieldName == 'age_min' || $fieldName == 'age_max') {
          $memberClasses->classes[$classRef]->fields->$fieldName = intval($value);
        }
        else {
          $memberClasses->classes[$classRef]->fields->$fieldName = $value;
        }
      }
      foreach ($mandatoryLabels as $fieldName => $label) {
        $memberClasses->classes[$classRef]->mandatory->$fieldName = $vals[$classRef]['mandatory'][$fieldName];
      }
      foreach ($maxLengthLabels as $fieldName => $label) {
        $max_len = $vals[$classRef]['max_length'][$fieldName];
        if (empty($max_len)) {
          $memberClasses->classes[$classRef]->max_length->$fieldName = NULL;
        }
        else {
          $memberClasses->classes[$classRef]->max_length->$fieldName = intval($max_len);
        }
      }
      foreach ($class->extras as $fieldName => $oldVal) {
        $memberClasses->classes[$classRef]->extras->$fieldName = $vals[$classRef]['extras'][$fieldName];
      }
    }
    $this->cacheInvalidator->invalidateTags(['event:' . $eid . ':registration']);
  }

}
