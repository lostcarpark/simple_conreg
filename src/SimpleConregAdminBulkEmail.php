<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMemberDelete
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\node\NodeInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminBulkEmail extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_email';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);
    
    $config = $this->config('simple_conreg.settings.'.$eid);

    $form = array(
      '#tree' => TRUE,
      '#prefix' => '<div id="bulkmailform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_bulkemail')
      ),
    );


    $form['sending'] = [
      '#prefix' => '<div id="upload">',
      '#suffix' => '</div>',
    ];
    $form['sending']['ids'] = [
      '#id' => 'edit-sending-ids',
      '#type' => 'textarea',
      '#title' => $this->t('IDs to upload'),
    ];

    // Fields for writing email message.
    $form['bulkemail'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Email message'),
      '#prefix' => '<div id="message">',
      '#suffix' => '</div>',
    );

    $form['bulkemail']['from_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('From email name'),
      '#description' => $this->t('Name that confirmation email is sent from.'),
      '#default_value' => $config->get('bulkemail.from_name'),
    );  

    $form['bulkemail']['from_email'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('From email address'),
      '#description' => $this->t('Email address that confirmation email is sent from (if you check the above box, a copy will also be sent to this address).'),
      '#default_value' => $config->get('bulkemail.from_email'),
    );  

    $form['bulkemail']['template_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Bulk email subject'),
      '#default_value' => $config->get('bulkemail.template_subject'),
    );

    $form['bulkemail']['template_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Bulk email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
      '#default_value' => $config->get('bulkemail.template_body'),
      '#format' => $config->get('bulkemail.template_format'),
    );  

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save Template'),
    );

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sending Options'),
    ];
    $form['options']['member_no_from'] = [
      '#type' => 'number',
      '#title' => $this->t('From'),
    ];
    $form['options']['member_no_to'] = [
      '#type' => 'number',
      '#title' => $this->t('To'),
    ];
    $form['options']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay (in miliseconds)'),
      '#default_value' => '10000',
    ];

    $form['do_prepare'] = [
      '#type' => 'button',
      '#value' => $this->t('Prepare'),
      '#ajax' => array(
        'wrapper' => 'upload',
        'callback' => array($this, 'prepareBulkSend'),
        'event' => 'click',
      ),
    ];
    
    $form['do_sending'] = [
      '#type' => 'button',
      '#value' => $this->t('Send bulk emails'),
      '#attributes' => ['onclick' => 'return (false);'],
    ];
    
    return $form;
  }

  /*
   * Submit handler for member email form.
   */

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $vals = $form_state->getValues();
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    $config->set('bulkemail.from_name', $vals['bulkemail']['from_name']);
    $config->set('bulkemail.from_email', $vals['bulkemail']['from_email']);
    $config->set('bulkemail.template_subject', $vals['bulkemail']['template_subject']);
    $config->set('bulkemail.template_body', $vals['bulkemail']['template_body']['value']);
    $config->set('bulkemail.template_format', $vals['bulkemail']['template_body']['format']);
    $config->save();
  }

  // Callback function preparing send.
  public function prepareBulkSend(array $form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $vals = $form_state->getValues();
    $ids = '';
    $options = [];
    $options['member_no_from'] = (isset($vals['options']['member_no_from']) ? $vals['options']['member_no_from'] : 0);
    $options['member_no_to'] = (isset($vals['options']['member_no_to']) ? $vals['options']['member_no_to'] : 0);
    foreach(SimpleConregStorage::adminMemberBadges($eid, FALSE, $options) as $member) {
      $ids .= $member['mid'] . "\n";
    }
    $form['sending']['ids']['#value'] = $ids;
    return $form['sending'];
  }

}
