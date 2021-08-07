<?php
/**
 * @file
 * Contains \Drupal\conreg_clickup\ConregConfigClickUpForm
 */
namespace Drupal\conreg_clickup;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\devel;

/**
 * Configure conreg_clickup settings for this site.
 */
class ConregConfigClickUpForm extends ConfigFormBase
{
  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'conreg_config_clickup';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'conreg_clickup.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = \Drupal::config('simple_conreg.clickup');

    $clientId = $config->get('clickup.client_id');
    $clientSecret = $config->get('clickup.client_secret');

    // Check if code returned from ClickUp...
    $code = \Drupal::request()->query->get('code');
    if (!empty($code)) {
      $token = ConregClickUp::getToken($clientId, $clientSecret, $code);

      $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.clickup');
      $config->set('clickup.token', $token);
      $config->save();

      // Redirect to remove code from URL.
      return $this->redirect('conreg_config_clickup');
    }

    $code = $config->get('clickup.code');
    $token = $config->get('clickup.token');

    $return_url = Url::fromRoute('conreg_config_clickup', [], ['absolute' => TRUE]);
    $url = Url::fromUri('https://app.clickup.com/api', ['query' => ['client_id' => $clientId, 'redirect_uri' => $return_url->toString()]]);
dpm($url->toString());
    //$external_link = \Drupal::l(t('Authenticate link to ClickUp'), $url);
    $external_link = Link::fromTextAndUrl(t('Authenticate link to ClickUp'), $url);


    $form['simple_conreg_authenticate'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('ClickUp Authorization'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_authenticate']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => 'To connect ClickUp, fill out Client ID and Secret below, save, then click this link.',
    );

    $form['simple_conreg_authenticate']['link'] = array(
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => $external_link,
    );

    $form['simple_conreg_clickup'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('ClickUp Details'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_clickup']['client_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $clientId,
    );

    $form['simple_conreg_clickup']['client_secret'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $clientSecret,
    );

    $form['simple_conreg_clickup']['token'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#default_value' => $token,
    );

    if (!empty($token)) {
      $teams = ConregClickUp::getTeam($token);
      
      foreach ($teams['teams'] as $team) {
        $form['simple_conreg_clickup_team_'.$team['id']] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Team: @team', ['@team' => $team['name']]),
        );

        $form['simple_conreg_clickup_lists_'.$team['id']] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Lists for: @team', ['@team' => $team['name']]),
        );
        $form['simple_conreg_clickup_folders_'.$team['id']] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Folders for: @team', ['@team' => $team['name']]),
        );
        foreach ($team['members'] as $member) {
          $form['simple_conreg_clickup_team_'.$team['id']]['member_'.$member['user']['id']] = array(
            '#markup' => $this->t('@id: @username', ['@id' => $member['user']['id'], '@username' => $member['user']['username']]),
            '#prefix' => '<div>',
            '#suffix' => '</div>',
          );
        }
        $spaces = ConregClickUp::getSpaces($team['id'], $token);
        foreach ($spaces['spaces'] as $space) {
          $lists = ConregClickUp::getLists($space['id'], $token);
          foreach ($lists['lists'] as $list) {
            $form['simple_conreg_clickup_lists_'.$team['id']]['list_'.$list['id']] = array(
              '#markup' => $this->t('@id: @listname', ['@id' => $list['id'], '@listname' => $list['name']]),
              '#prefix' => '<div>',
              '#suffix' => '</div>',
            );
          }
          $folders = ConregClickUp::getFolders($space['id'], $token);
          foreach ($folders['folders'] as $folder) {
            $form['simple_conreg_clickup_folders_'.$team['id']]['folder_'.$folder['id']] = array(
              '#markup' => $this->t('Folder: @foldername', ['@foldername' => $folder['name']]),
              '#prefix' => '<h3>',
              '#suffix' => '</h3>',
            );
            foreach ($folder['lists'] as $folderList) {
              $form['simple_conreg_clickup_folders_'.$team['id']]['list_'.$folderList['id']] = array(
                '#markup' => $this->t('@id: @listname', ['@id' => $folderList['id'], '@listname' => $folderList['name']]),
                '#prefix' => '<div>',
                '#suffix' => '</div>',
              );
            }
          }
        }
      }
    }

    $form['simple_conreg_clickup_test'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('ClickUp Test'),
      '#tree' => TRUE,
    );

    $form['simple_conreg_clickup_test']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => 'To create a test task, fill out the details below and press "Create".',
    );

    $form['simple_conreg_clickup_test']['list_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('List ID'),
    );

    $form['simple_conreg_clickup_test']['assignees'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Assignee Member IDs'),
    );

    $form['simple_conreg_clickup_test']['title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Task Title'),
    );

    $form['simple_conreg_clickup_test']['description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Task Description'),
    );

    $form['simple_conreg_clickup_test']['status'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Task Status'),
    );

    $form['simple_conreg_clickup_test']['submit_test'] = array(
      '#type' => 'submit',
      '#value' => t('Create Task'),
      '#submit' => [[$this, 'createTask']],
      '#attributes' => array('id' => "submitBtn"),
    );

    return parent::buildForm($form, $form_state);
  }

  // Handler for Create Task button.

  public function createTask(array &$form, FormStateInterface $form_state)
  {
    $vals = $form_state->getValues();

    $message = '';
    $taskId = ConregClickUp::createTask(
              $vals['simple_conreg_clickup_test']['list_id'],
              $vals['simple_conreg_clickup_test']['assignees'],
              $vals['simple_conreg_clickup_test']['title'],
              $vals['simple_conreg_clickup_test']['description'],
              $vals['simple_conreg_clickup_test']['status'],
              NULL,
              $message);
    if (!empty($taskId))
      \Drupal::messenger()->addMessage($this->t('Task has been created with ID of @id', ['@id' => $taskId]));
    else
      \Drupal::messenger()->addMessage($message);

    $form_state->setRebuild();
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $vals = $form_state->getValues();
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.clickup');
    $config->set('clickup.client_id', $vals['simple_conreg_clickup']['client_id']);
    $config->set('clickup.client_secret', $vals['simple_conreg_clickup']['client_secret']);
    $config->set('clickup.token', $vals['simple_conreg_clickup']['token']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
