<?php

namespace Drupal\simple_conreg\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheTagsInvalidator;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\SimpleConregTokens;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure simple_conreg settings for this site.
 */
class EventMemberTypesForm extends ConfigFormBase {

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
      $form['simple_conreg_event'] = [
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      ];
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

    $form['admin'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-simple_conreg_event',
    ];

    foreach ($memberTypes->types as $typeRef => $type) {
      $typeName = $type->name ?? $typeRef;
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
        '#step' => '0.01',
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

      $typeDays = $type->days ?? [];
      $form[$typeRef]['days'] = $this->buildDaysTable($typeRef, $typeDays, $days);

      $form[$typeRef]['confirmation'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Member Type Details'),
      ];
      $form[$typeRef]['confirmation']['override'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override confirmation email.'),
        '#default_value' => $type->confirmation->override,
      ];
      $form[$typeRef]['confirmation']['template_subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Confiramtion email subject'),
        '#default_value' => $type->confirmation->template_subject,
      ];
      $form[$typeRef]['confirmation']['template_body'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Confiramtion email body'),
        '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
        '#default_value' => $type->confirmation->template_body,
        '#format' => $type->confirmation->template_format,
      ];

      $form[$typeRef]['clone'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clone @name', ['@name' => $typeName]),
        '#submit' => [[$this, 'cloneSubmit']],
        '#attributes' => ['id' => "submitBtn"],
      ];

      $form[$typeRef]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete @name', ['@name' => $typeName]),
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
  public function buildCloneForm($form, FormStateInterface $form_state, $cloneMemberTypeID, $memberTypes) {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone Member Type'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $typeName = $memberTypes->types[$cloneMemberTypeID]->name ?? $cloneMemberTypeID;
    $form['clone_from'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Clone from: @type', ['@type' => $typeName]),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['clone_to_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New type code'),
      '#required' => TRUE,
    ];
    $form['clone_to_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New type name'),
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
  public function buildDeleteForm($form, FormStateInterface $form_state, $deleteMemberTypeID, $memberTypes) {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete Member Type'),
      '#prefix' => '<div><h3>',
      '#suffix' => '</h3></div>',
    ];
    $typeName = $memberTypes->types[$deleteMemberTypeID]->name ?? $deleteMemberTypeID;
    $form['to_delete'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Delete member type: @type', ['@type' => $typeName]),
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
   * Build table for days table.
   */
  public function buildDaysTable($typeRef, $typeDays, $days) {

    $daysForm = [
      '#type' => 'fieldset',
      '#title' => $this->t('Days'),
    ];

    $headers = [
      'day' => ['data' => $this->t('Day')],
      'enable' => ['data' => $this->t('Enable')],
      'description' => ['data' => $this->t('Description')],
      'price' => ['data' => $this->t('Price')],
    ];

    $daysForm['daysTable'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => ['id' => 'conreg-member-type-' . $typeRef . '-days'],
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    foreach ($days as $dayRef => $day) {
      $row = [];
      $row['day'] = [
        '#markup' => Html::escape($day),
      ];
      $row["enable"] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable'),
        '#default_value' => isset($typeDays[$dayRef]) ? TRUE : FALSE,
      ];
      $row["description"] = [
        '#type' => 'textfield',
        '#default_value' => $typeDays[$dayRef]->description ?? '',
        '#size' => 10,
      ];
      $row["price"] = [
        '#type' => 'number',
        '#default_value' => $typeDays[$dayRef]->price ?? '',
        '#step' => '0.01',
      ];
      $daysForm['daysTable'][$dayRef] = $row;
    }

    return $daysForm;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $memberTypes = $form_state->get('member_types');

    $vals = $form_state->getValues();
    $this->updateMemberTypes($memberTypes, $vals, $eid);
    SimpleConregOptions::saveMemberTypes($eid, $memberTypes);

    parent::submitForm($form, $form_state);
  }

  /**
   * Callback to clone a member type.
   */
  public function cloneSubmit(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $memberTypes = $form_state->get('member_types');

    // Get the parent of the button that was triggered.
    $cloneMemberTypeID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('clone_member_type_id', $cloneMemberTypeID);

    // Update the member types stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberTypes($memberTypes, $vals, $eid);
    $form_state->set('member_types', $memberTypes);

    $form_state->setRebuild();
  }

  /**
   * Callback for clone confirm button.
   */
  public function confirmCloneSubmit(array &$form, FormStateInterface $form_state) {
    $memberTypes = $form_state->get('member_types');

    // Get source type.
    $cloneMemberTypeID = $form_state->get('clone_member_type_id');

    // Update the.
    $vals = $form_state->getValues();
    $cloneTo = $vals['clone_to_code'];
    if (array_key_exists($cloneTo, $memberTypes->types)) {
      $this->messenger()->addMessage($this->t('@type already exists. Choose a different name.', ['@type' => $cloneTo]), 'error');
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

  /**
   * Callback for delete button.
   */
  public function deleteSubmit(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $memberTypes = $form_state->get('member_types');

    // Get the parent of the button that was triggered.
    $deleteMemberTypeID = $form_state->getTriggeringElement()['#parents'][0];
    $form_state->set('delete_member_type_id', $deleteMemberTypeID);

    // Update the member types stored in the form.
    $vals = $form_state->getValues();
    $this->updateMemberTypes($memberTypes, $vals, $eid);
    $form_state->set('member_types', $memberTypes);

    $form_state->setRebuild();
  }

  /**
   * Call back for confirm delete button.
   */
  public function confirmDeleteSubmit(array &$form, FormStateInterface $form_state) {
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

  /**
   * Callback for cancel button.
   */
  public function cancelAction(array &$form, FormStateInterface $form_state) {
    $form_state->set('clone_member_type_id', NULL);
    $form_state->set('delete_member_type_id', NULL);

    $form_state->setRebuild();
  }

  /**
   * Save the membership types.
   */
  private function updateMemberTypes(&$memberTypes, $vals, $eid) {
    foreach ($memberTypes->types as $typeCode => $type) {
      foreach ($vals[$typeCode]['type'] as $fieldName => $val) {
        $memberTypes->types[$typeCode]->$fieldName = $val;
      }
      foreach ($vals[$typeCode]['days']['daysTable'] as $dayRef => $dayVals) {
        if ($dayVals['enable']) {
          $memberTypes->types[$typeCode]->days[$dayRef] = new \stdClass();
          $memberTypes->types[$typeCode]->days[$dayRef]->description = $dayVals['description'];
          $memberTypes->types[$typeCode]->days[$dayRef]->price = $dayVals['price'];
          $memberTypes->types[$typeCode]->dayOptions[$dayRef] = $dayVals['description'];
        }
        else {
          unset($memberTypes->types[$typeCode]->days[$dayRef]);
          unset($memberTypes->types[$typeCode]->dayOptions[$dayRef]);
        }
      }
      $memberTypes->types[$typeCode]->confirmation = (object)[
        'override' => $vals[$typeCode]['confirmation']['override'],
        'template_subject' => $vals[$typeCode]['confirmation']['template_subject'],
        'template_body' => $vals[$typeCode]['confirmation']['template_body']['value'],
        'template_format' => $vals[$typeCode]['confirmation']['template_body']['format'],
      ];
    }
    $this->cacheInvalidator->invalidateTags(['event:' . $eid . ':registration']);
  }

}
