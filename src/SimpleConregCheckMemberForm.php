<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregCheckMemberForm
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregCheckMemberForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a new EmailExampleGetFormPage.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.mail'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_check_member';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    $memberPrices = array();

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
    if (empty($config->get('payments.system'))) {
      // Event not configured. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return parent::buildForm($form, $form_state);
    }

    $form = array(
      '#tree' => TRUE,
      '#cache' => ['max-age' => 0],
      '#title' => $config->get('member_check.title'),
      '#prefix' => '<div id="regform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_form')
      ),
    );

    $form['intro'] = array(
      '#markup' => $config->get('member_check.intro'),
    );

    $form['email'] = array(
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
      '#description' => $this->t('Please provide the email address that was used to register.'),
    );
    
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Check member details'),
      '#attributes' => array("onclick" => "jQuery(this).attr('disabled', TRUE); jQuery(this).parents('form').submit();"),
    );
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);
    $form_values = $form_state->getValues();

    // Set up parameters for receipt email.
    $params = ['eid' => $eid];
    $params['to'] = $form_values['email'];
    $params['subject'] = $config->get('member_check.confirm_subject');

    // Get all members registered by email address.
    $members = SimpleConregStorage::loadAll(['eid' => $eid, 'email' => $form_values['email'], 'is_paid' => 1, 'is_deleted' => 0]);
    
    if (count($members)) {
      $mids = [];
      foreach ($members as $member) {
        $mids[] = $member['mid'];
      }
      $params['mid'] = $mids;
      $params['body'] = $config->get('member_check.confirm_body');
      $params['body_format'] = $config->get('member_check.confirm_format');
      
      $info = 'Member details found and sent to @email.';
    }
    else {
      $params['body'] = $config->get('member_check.unknown_body');
      $params['body_format'] = $config->get('member_check.unknown_format');
      
      $info = 'Member details not found for @email.';
    }

    $module = "simple_conreg";
    $key = "template";
    $to = $form_values['email'];
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);

    // Log an event to show a member check occurred.
    \Drupal::logger('simple_conreg')->info($info, ['@email' => $form_values['email']]);

    // Display a status message to let user know an email has been sent.
    \Drupal::messenger()->addMessage($this->t('An email has been sent to @email. If you don\'t find it, please check your spam folder.', ['@email' => $form_values['email']]));
  }


}
