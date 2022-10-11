<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\Form\EventListForm
 */
namespace Drupal\simple_conreg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregEventStorage;

/**
 * Configure simple_conreg settings for this site.
 */
class EventListForm extends ConfigFormBase
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
  protected function getEditableConfigNames() 
  {
    return [
      'simple_conreg.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) 
  {
    $events = SimpleConregEventStorage::loadAll();

    $headers = [
      'event_name' => ['data' => $this->t('Event name'), 'field' => 'event_name'],
      'state' => ['data' => $this->t('State'), 'field' => 'is_open'],
      $this->t('Update'),
    ];


    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => ['id' => 'simple-conreg-admin-event-list'],
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    foreach ($events as $event) {
      $eid = $event['eid'];
      // Sanitize each entry.
      $row = [];
      $row['event_name'] = [
        '#markup' => Html::escape($event['event_name']),
      ];
      $row['state'] = [
        '#markup' => $event['is_open'] ? $this->t('Open') : $this->t('Closed')
      ];
      $row['link'] = [
        '#type' => 'dropbutton',
        '#links' => [
          'admin_button' => [
            'title' => $this->t('Admin'),
            'url' => Url::fromRoute('simple_conreg_admin_members', ['eid' => $eid]),
          ],
          'config_button' => [
            'title' => $this->t('Configure'),
            'url' => Url::fromRoute('simple_conreg_config', ['eid' => $eid]),
          ],
          'clone_button' => [
            'title' => $this->t('Clone'),
            'url' => Url::fromRoute('simple_conreg_event_clone', ['eid' => $eid]),
          ],
        ],
      ];
      $form['table'][$eid] = $row;
    }

    return $form;
  }

  /** 
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) 
  {
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) 
  {
  }

}
