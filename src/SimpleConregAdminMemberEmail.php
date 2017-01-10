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
class SimpleConregAdminMemberEmail extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_email';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $mid = NULL) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);
    // Initialise params array with eid and mid.
    $params = ['eid' => $eid, 'mid' => $mid];

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $config = $this->config('simple_conreg.settings.'.$eid);

    // Set up array of from email address options.
    $from_email = $config->get('confirmation.from_email');
    $from_options = [$from_email => $from_email];
    $copy_to = $config->get('confirmation.copy_email_to');
    if (!empty($copy_to))
      $from_options[$copy_to] = $copy_to;
    $user_email = \Drupal::currentUser()->getEmail();
    $from_options[$user_email] = $user_email;
    // Default email to the event from email, unless different address previously selected.
    $from_default = $from_email;

    // Build list of templates.
    $template_config = $this->config('simple_conreg.email_templates');
    $options = [];
    $templates = [];
    if (empty($count = $template_config->get('count'))) {
      $count = 0;
    }
    for ($template = 1; $template <= $count; $template++) {
      $subject = $template_config->get('template'.$template.'subject');
      $body = $template_config->get('template'.$template.'body');
      $options[$template] = $subject;
      $templates[$template] = ['subject' => $subject, 'body' => $body];
    }
    // Store templates to form state.
    $form_state->set('templates', $templates);

    // Check if default template selected.
    if (NULL == $default_template = $form_values['templates']['template_select'])
      $default_template = 1;

    // If form submitted, use submitted values, otherwise use defaults.
    if (empty($params['from'] = $form_values['email']['message']['from_email']))
      $params['from'] = $from_default;

    if (empty($params['subject'] = $form_values['email']['message']['subject']))
      $params['subject'] = $templates[$default_template]['subject'];

    if (empty($params['body'] = $form_values['email']['message']['body']))
      $params['body'] = $templates[$default_template]['body'];

    // If tokens stored in form state, store in params to save looking up again.
    if (NULL != $tokens = $form_state->get('tokens'))
      $params['tokens'] = $tokens;

    $message = [];
    SimpleConregEmailer::createEmail($message, $params);
    $params = $message['params'];
    $form_state->set('params', $params);

    // Check member exists.
    if (!isset($params['mid'])) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Member not found. Please confirm member valid.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return $form;
    }


/*    $variables = [];
    //$user = \Drupal::currentUser();
//dpm($user);
    $data = [];
    //$data['user'] = $user;
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $options['langcode'] = $language_code;
//dpm($options);
    user_mail_tokens($variables, $data, $options);
//dpm($variables, 'Variables');
*/

    $form = array(
      '#tree' => TRUE,
      '#prefix' => '<div id="transferform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_form')
      ),
    );

    $form['member'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Member details'),
    );

    $form['member']['is_approved'] = array(
      '#markup' => $this->t('Approved: @approved', ['@approved' => $params['is_approved']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['member_no'] = array(
      '#markup' => $this->t('Member number: @member_no', ['@member_no' => $params['member_no']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['email'] = array(
      '#markup' => $this->t('Email: @email', ['@email' => $params['email']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['first_name'] = array(
      '#markup' => $this->t('First Name: @first_name', ['@first_name' => $params['first_name']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['last_name'] = array(
      '#markup' => $this->t('Last Name: @last_name', ['@last_name' => $params['last_name']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['badge_name'] = array(
      '#markup' => $this->t('Badge Name: @badge_name', ['@badge_name' => $params['badge_name']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['is_paid'] = array(
      '#markup' => $this->t('Paid: @is_paid', ['@is_paid' => $params['is_paid']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['payment_method'] = array(
      '#markup' => $this->t('Payment method: @payment_method', ['@payment_method' => $params['payment_method']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['member_price'] = array(
      '#markup' => $this->t('Price: @member_price', ['@member_price' => $params['member_price']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['payment_id'] = array(
      '#markup' => $this->t('Payment reference: @payment_id', ['@payment_id' => $params['payment_id']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['member']['comment'] = array(
      '#markup' => $this->t('Comment: @comment', ['@comment' => $params['comment']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );
    
    // Fields for selecting template.
    $form['template'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Template'),
      '#prefix' => '<div id="template">',
      '#suffix' => '</div>',
    );

    // Template selection drop-down.
    $form['template']['template_select'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select template to use (overwrites message)'),
      '#options' => $options,
      '#default_value' => 1,
      '#ajax' => array(
        'wrapper' => 'email',
        'callback' => array($this, 'updateEmailTemplate'),
        'event' => 'change',
      ),
    );

    // Comtainer for message fields and preview.
    $form['email'] = array(
      '#prefix' => '<div id="email">',
      '#suffix' => '</div>',
    );

    // Fields for writing email message.
    $form['email']['message'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Email message'),
      '#prefix' => '<div id="message">',
      '#suffix' => '</div>',
    );

    $form['email']['message']['from_email'] = array(
      '#type' => 'select',
      '#title' => $this->t('Send from email address'),
      '#options' => $from_options,
      '#default_value' => $from_default,
      '#ajax' => array(
        'wrapper' => 'email',
        'callback' => array($this, 'updateEmailPreview'),
        'event' => 'change',
      ),
    );

    if (empty($template = $form_values['template']['template_select'])) {
      $template = 1;
    }
    $form['email']['message']['subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Message subject'),
      '#default_value' => $params['subject'],
      '#ajax' => array(
        'wrapper' => 'preview',
        'callback' => array($this, 'updateEmailPreview'),
        'event' => 'change',
      ),
    );

    $form['email']['message']['body'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Message body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
      '#default_value' => $params['body'],
    );  

    // Fields for writing email message.
    $form['email']['preview'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Preview'),
      '#prefix' => '<div id="preview">',
      '#suffix' => '</div>',
    );

    $form['email']['preview']['from'] = array(
      '#markup' => $this->t('From: @from_email', ['@from_email' => $params['from']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['email']['preview']['to'] = array(
      '#markup' => $this->t('To: @to_email', ['@to_email' => $params['to']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['email']['preview']['subject'] = array(
      '#markup' => $this->t('Subject: @subject', ['@subject' => $message['subject']]),
      '#prefix' => '<div class="field">',
      '#suffix' => '</div><hr />',
    );

    $form['email']['preview']['body'] = array(
      '#markup' => $message['preview'],
      '#prefix' => '<div class="field">',
      '#suffix' => '</div>',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Send email'),
    );

    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#submit' => [[$this, 'submitCancel']],
    );

    $form_state->set('mid', $mid);
    return $form;
  }

  // Callback function for Template drop down - load message fields with template.
  public function updateEmailTemplate(array $form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $templates = $form_state->get('templates');

    if (!empty($template = $form_values['template']['template_select'])) {
      $params = $form_state->get('params');
      $params['subject'] = $templates[$template]['subject'];
      $params['body'] = $templates[$template]['body'];
      $message = [];
      SimpleConregEmailer::createEmail($message, $params);
      $params = $message['params'];
      $form_state->set('params', $params);

      $form['email']['message']['subject']['#value'] = $params['subject'];
      $form['email']['message']['body']['#value'] = $params['body'];
      $form['email']['preview']['subject']['#markup'] = $this->t('Subject: @subject', ['@subject' => $message['subject']]);
      $form['email']['preview']['body']['#markup'] = $message['preview'];
    }
    return $form['email'];
  }

  // Callback function for message fields - update preview.
  public function updateEmailPreview(array $form, FormStateInterface $form_state) {
    return $form['email']['preview'];
  }
  
  /*
   * Submit handler for cancel button.
   */

  public function submitCancel(array &$form, FormStateInterface $form_state) {
    dpm('Cancelled!');
    // Redirect to member list.
    $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
  }

  /*
   * Submit handler for member email form.
   */

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $mid = $form_state->get('mid');

    dpm('Submitted!');

    $form_values = $form_state->getValues();

    $params = $form_state->get('params');
    $params['subject'] = $form_values['email']['message']['subject'];
    $params['body'] = $form_values['email']['message']['body'];
    $module = "simple_conreg";
    $key = "template";
    $to = $params["to"];
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);
    //unset($result['params']);
    //dpm($result);
    
    // Redirect to member list.
    $form_state->setRedirect('simple_conreg_admin_members', ['eid' => $eid, 'display' => $display, 'page' => $page]);
  }

}
