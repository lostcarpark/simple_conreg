<?php
/**
 * @file
 * Contains \Drupal\conreg_clickup\ConregConfigClickUpOptionsForm
 */
namespace Drupal\conreg_clickup;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_conreg\SimpleConregStorage;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\FieldOptions;
use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class ConregConfigClickUpOptionsForm extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'conreg_config_clickup_options';
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
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
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

    // Get config for event and fieldset.
    $config = SimpleConregConfig::getConfig($eid);

    $optionTitles = FieldOptions::getFieldOptionsTitles($eid, $config);

    $memberNames = [];
    $teams = ConregClickUp::getTeam();
    foreach ($teams['teams'] as $team) {
      foreach ($team['members'] as $member) {
        $memberNames[$member['user']['id']] = $member['user']['username'];
      }
    }

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-new_addon',
    );

    $form['new_group'] = array(
      '#type' => 'details',
      '#title' => $this->t('New Option Group'),
      '#tree' => TRUE,
      '#group' => 'admin',
      '#weight' => -100,
    );

    $form['new_group']['group_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Option group name'),
    );

    $form['new_group']['submit_add_group'] = array(
      '#type' => 'submit',
      '#value' => t('Add Group'),
      '#submit' => [[$this, 'addGroup']],
      '#attributes' => array('id' => "submitBtn"),
    );

    /*
     * Placeholder for options for each add-on.
     */
    $form['groups'] = array(
      '#tree' => TRUE,
    );

    $groupOptions = [];
    $buttonGroups = [];

    /*
     * Loop through each option group and add to form.
     */
    foreach ($config->get('clickup_option_groups') as $groupName => $groupVals) {
      $form['groups'][$groupName] = array(
        '#type' => 'details',
        '#title' => $groupName,
        '#group' => 'admin',
      );

      $form['groups'][$groupName]['list_id'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('List ID'),
        '#description' => $this->t('The ClickUp ID of the list to add to.'),
        '#default_value' => (isset($groupVals['list_id']) ? $groupVals['list_id'] : ''),
      );

      $form['groups'][$groupName]['task_title'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Task Title Template'),
        '#description' => $this->t('Title of created task. [name] will replace mamber name.'),
        '#default_value' => (isset($groupVals['task_title']) ? $groupVals['task_title'] : ''),
      );

      $form['groups'][$groupName]['task_description'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Task Description Template'),
        '#description' => $this->t('Description of created task. Use replacement patterns [name], [options], [link].'),
        '#default_value' => (isset($groupVals['task_description']) ? $groupVals['task_description'] : ''),
      );

      $form['groups'][$groupName]['link_text'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Link text'),
        '#description' => $this->t('Text for the link.'),
        '#default_value' => (isset($groupVals['link_text']) ? $groupVals['link_text'] : ''),
      );

      $form['groups'][$groupName]['link_url'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Link URL'),
        '#description' => $this->t('URL that the link points to.'),
        '#default_value' => (isset($groupVals['link_url']) ? $groupVals['link_url'] : ''),
      );

      $form['groups'][$groupName]['task_status'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Task Status'),
        '#description' => $this->t('Text of the ClickUp status for the task (note: invalid status will cause an error).'),
        '#default_value' => (isset($groupVals['task_status']) ? $groupVals['task_status'] : ''),
      );

      $mapping = (isset($groupVals['option_mapping']) ? $groupVals['option_mapping'] : '');
      $form['groups'][$groupName]['option_mapping'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Option Mapping to ClickUp Members'),
        '#description' => $this->t('On each line place Conreg Option ID followed by ClickUp Member IDs, separated by |. If mapping to multiple Members, separate by commas. E.g. "1|4793987,4793985".'),
        '#default_value' => $mapping,
      );

      $form['groups'][$groupName]['mapping_test'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Option Mapping Test'),
      );

      $i = 1;
      $options = [];
      foreach (explode("\n", $mapping) as $mappingLine) {
        $fields = explode('|', $mappingLine);
        $options[] = $fields[0];
        $optionName = $optionTitles[$fields[0]];
        $members = [];
        foreach (explode(',', trim($fields[1])) as $memberId) {
          $members[] = $memberNames[$memberId];
        }
        $form['groups'][$groupName]['mapping_test'][$i++] = array(
          '#markup' => $this->t('@option => @users', ['@option' => $optionName, '@users' => implode(", ", $members)]),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        );
      }
      $groupOptions[$groupName] = $options;

      $form['groups'][$groupName]['create'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Create tasks for existing members'),
      );

      $count = ConregClickUp::getMembersWithoutTasks($eid, $options, TRUE);

      $form['groups'][$groupName]['create']['count'] = array(
        '#markup' => $this->t('@count users have options without ClickUp tasks.', ['@count' => $count]),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      );

      $form['groups'][$groupName]['create']['info'] = array(
        '#markup' => $this->t('Click below to create tasks for the next 10 members. Please wait 1 minute between presses to avoid flooding ClickUp.'),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      );

      $buttonName = str_replace(' ', '_', $groupName);
      $buttonGroups[$buttonName] = $groupName;

      $form['groups'][$groupName]['create']['submit_create_tasks'] = array(
        '#type' => 'submit',
        '#value' => t('Create Tasks for @name', ['@name' => $groupName]),
        '#name' => $buttonName,
        '#submit' => [[$this, 'createMemberTasks']],
        '#attributes' => array('id' => "submitBtn"),
      );
    }

    $form_state->set('groupOptions', $groupOptions);
    $form_state->set('buttonGroups', $buttonGroups);

    return parent::buildForm($form, $form_state);
  }


  // Handler for Add Group button.

  public function addGroup(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $groupName = $form_state->getValue(['new_group', 'group_name']);

    if (!empty($vals['new_group']['group_name'])) {
      $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
      $configGroupName = 'clickup_option_groups.' . $groupName;
      // Only add group if not already present.
      if (empty($config->get($configGroupName))) {
        $config->set($configGroupName, []);
        $config->save();
        \Drupal::messenger()->addMessage($this->t('Option group @name has been added.', ['@name' => $groupName]));
      }
      $form_state->setValue(['groups', $groupName, '#value'], '');
    }

    $form_state->setRebuild();
  }

  // Handler for Create Tasks button.

  public function createMemberTasks(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $config = SimpleConregConfig::getConfig($eid);

    $groupOptions = $form_state->get('groupOptions');
    $buttonGroups = $form_state->get('buttonGroups');
    $vals = $form_state->getValues();
    $group = $buttonGroups[$form_state->getTriggeringElement()['#name']];
    $members = ConregClickUp::getMembersWithoutTasks($eid, $groupOptions[$group], FALSE);
    \Drupal::messenger()->addMessage($this->t('Creating tasks for @name.', ['@name' => $group]));
    $i = 0;
    foreach ($members as $member) {
      if ($i++ >= 10) break;
      $memberRec = SimpleConregStorage::load(['mid' => $member['mid']]);
      $optionVals = FieldOptions::getMemberOptionValues($member['mid']);
      ConregClickUp::createMemberTasks($eid, $member['mid'], $optionVals, $config);
      \Drupal::messenger()->addMessage($this->t('Tasks created for member @name.', ['@name' => $memberRec['first_name'] . ' ' . $memberRec['last_name']]));
    }
    \Drupal::messenger()->addMessage($this->t('Created tasks for @number members.', ['@number' => $i]));

    $form_state->setRebuild();
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $vals = $form_state->getValues();
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    foreach ($vals['groups'] as $groupName => $groupVals) {
      $config->set('clickup_option_groups.'.$groupName.'.list_id', $groupVals['list_id']);
      $config->set('clickup_option_groups.'.$groupName.'.task_title', $groupVals['task_title']);
      $config->set('clickup_option_groups.'.$groupName.'.task_description', $groupVals['task_description']);
      $config->set('clickup_option_groups.'.$groupName.'.link_text', $groupVals['link_text']);
      $config->set('clickup_option_groups.'.$groupName.'.link_url', $groupVals['link_url']);
      $config->set('clickup_option_groups.'.$groupName.'.task_status', $groupVals['task_status']);
      $config->set('clickup_option_groups.'.$groupName.'.option_mapping', $groupVals['option_mapping']);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }


}
