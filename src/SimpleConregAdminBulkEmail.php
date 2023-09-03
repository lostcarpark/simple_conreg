<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminBulkEmail extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_admin_member_email';
  }

  /**
   * Construct the form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    $config = $this->config('simple_conreg.settings.' . $eid);

    $form = [
      '#tree' => TRUE,
      '#prefix' => '<div id="bulkmailform">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => ['simple_conreg/conreg_bulkemail'],
      ],
    ];

    // Fields for writing email message.
    $form['bulkemail'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email message'),
      '#prefix' => '<div id="message">',
      '#suffix' => '</div>',
    ];

    $form['bulkemail']['from_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From email name'),
      '#description' => $this->t('Name that confirmation email is sent from.'),
      '#default_value' => $config->get('bulkemail.from_name'),
    ];

    $form['bulkemail']['from_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From email address'),
      '#description' => $this->t('Email address that confirmation email is sent from (if you check the above box, a copy will also be sent to this address).'),
      '#default_value' => $config->get('bulkemail.from_email'),
    ];

    $form['bulkemail']['template_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bulk email subject'),
      '#default_value' => $config->get('bulkemail.template_subject'),
    ];

    $form['bulkemail']['template_body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Bulk email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
      '#default_value' => $config->get('bulkemail.template_body'),
      '#format' => $config->get('bulkemail.template_format'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Template'),
    ];

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sending Options'),
    ];
    $memberTypes = SimpleConregOptions::memberTypes($eid, $config);
    $form['options']['member_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Member types'),
      '#options' => $memberTypes->privateOptions,
    ];
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $form['options']['badge_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Badge types'),
      '#options' => $badgeTypes,
    ];
    $fieldOptions = FieldOptions::getFieldOptionsTitles($eid);
    $form['options']['field_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Options'),
      '#options' => $fieldOptions,
    ];
    $form['options']['member_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Member Number range to email'),
      '#description' => $this->t('Enter Member Nos to send emails to. Use commas (,) to separate ranges and hyphens (-) to separate range limits, e.g. "1,3,5-7".'),
    ];
    $form['options']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay (in miliseconds)'),
      '#default_value' => '1000',
    ];

    $form['do_prepare'] = [
      '#type' => 'button',
      '#value' => $this->t('Prepare'),
      '#ajax' => [
        'wrapper' => 'upload',
        'callback' => [$this, 'prepareBulkSend'],
        'event' => 'click',
      ],
    ];

    $form['do_sending'] = [
      '#type' => 'button',
      '#value' => $this->t('Send bulk emails'),
      '#attributes' => ['onclick' => 'return (false);'],
    ];

    $form['sending'] = [
      '#prefix' => '<div id="upload">',
      '#suffix' => '</div>',
    ];
    $form['sending']['ids'] = [
      '#id' => 'edit-sending-ids',
      '#type' => 'textarea',
      '#title' => $this->t('IDs to upload'),
    ];

    return $form;
  }

  /**
   * Submit handler for member email form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $vals = $form_state->getValues();
    $config = $this->configFactory->getEditable('simple_conreg.settings.' . $eid);
    $config->set('bulkemail.from_name', $vals['bulkemail']['from_name']);
    $config->set('bulkemail.from_email', $vals['bulkemail']['from_email']);
    $config->set('bulkemail.template_subject', $vals['bulkemail']['template_subject']);
    $config->set('bulkemail.template_body', $vals['bulkemail']['template_body']['value']);
    $config->set('bulkemail.template_format', $vals['bulkemail']['template_body']['format']);
    $config->save();
  }

  /**
   * Callback function preparing send.
   */
  public function prepareBulkSend(array $form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $vals = $form_state->getValues();
    // Get list of member types, and remove keys for any that aren't selected.
    $ids = [];
    $options = [];
    $options['member_types'] = array_filter($vals['options']['member_types'] ?? [], fn($item) => !empty($item));
    $options['badge_types'] = array_filter($vals['options']['badge_types'] ?? [], fn($item) => !empty($item));
    $options['field_options'] = array_filter($vals['options']['field_options'] ?? [], fn($item) => !empty($item));
    $options['member_range'] = ($vals['options']['member_range'] ?? 0);
    // For all members in range, if type in selection, add to list.
    foreach (SimpleConregStorage::adminMemberBadges($eid, FALSE, $options) as $member) {
      $ids[] = $member['mid'];
    }
    $form['sending']['ids']['#value'] = implode(" ", $ids);
    return $form['sending'];
  }

}
