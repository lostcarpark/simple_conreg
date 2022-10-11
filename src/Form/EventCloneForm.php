<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\Form\EventCloneForm
 */
namespace Drupal\simple_conreg\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregTokens;

/**
 * Clone a simple_conreg event.
 */
class EventCloneForm extends FormBase
{
  /** 
   * {@inheritdoc}
   */
  public function getFormId() 
  {
    return 'simple_conreg_admin_event_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    foreach ($config->get() as $key => $val) {
    }
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    $form['event_name'] = array(
      '#type' => 'textfield',
      '#title' => 'Name of new event',
      '#maxlength' => 256,
      '#required' => TRUE,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Clone'),
    );

    $form['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#limit_validation_errors' => [],
      '#submit' => [[$this, 'submitFormCancel']],
    );
    return $form;
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) 
  {
    $oldEid = $form_state->get('eid');
    $vals = $form_state->getValues();
    // Create new event in events table.
    $event = [
      'event_name' => $vals['event_name'],
      'is_open' => 1
    ];
    $newEid = SimpleConregEventStorage::insert($event);
    // Load source event configuration, then save as new event's configuration.
    $oldConfig = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$oldEid);
    $newConfig = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$newEid);
    foreach ($oldConfig->get() as $key => $val) {
      $newConfig->set($key, $val);
    }
    $newConfig->save();
    // Show message confirming event creation.
    \Drupal::messenger()->addMessage($this->t(
      'New event created for @event_name.',
      ['@event_name' => $vals['event_name']]
    ));
    // Redirect back to event list.
    $form_state->setRedirect('simple_conreg_event_list');
  }

  public function submitFormCancel(array &$form, FormStateInterface $form_state)
  {
    // Cancelling clone, so just redirect back to event list.
    $form_state->setRedirect('simple_conreg_event_list');
  }
  
}