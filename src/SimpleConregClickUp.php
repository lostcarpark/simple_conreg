<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregClickUp.
 */

namespace Drupal\simple_conreg;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\devel;
use GuzzleHttp\Exception\RequestException;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregClickUp
{

  /**
   * Get a ClickUp Token.
   *
   * Parameters: Event ID.
   */
  public static function getToken($clientId, $clientSecret, $code)
  {
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    $response = $client->post($base_url . '/oauth/token', [
      'verify' => true,
      'form_params' => ['client_id' => $clientId,
                        'client_secret'=> $clientSecret,
                        'code' => $code,],
        'headers' => [
          'Content-type' => 'application/x-www-form-urlencoded',
        ],
    ])->getBody()->getContents();
    $decoded = Json::decode($response);
    
    return $decoded['access_token'];
  }
  
  
  public static function getTeam($token = NULL)
  {
    if (empty($token)) {
      $config = \Drupal::config('simple_conreg.clickup');
      $token = $config->get('clickup.token');
    }
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    $response = $client->get($base_url . '/team', [
      'verify' => true,
      'headers' => [
        'Content-type' => 'application/json',
        'Authorization' => $token,
      ],
    ])->getBody()->getContents();
    $decoded = Json::decode($response);

    return $decoded;
  }


  public static function getSpaces($team, $token = NULL)
  {
    if (empty($token)) {
      $config = \Drupal::config('simple_conreg.clickup');
      $token = $config->get('clickup.token');
    }
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    $response = $client->get($base_url . "/team/$team/space" , [
      'verify' => true,
      'headers' => [
        'Content-type' => 'application/json',
        'Authorization' => $token,
      ],
    ])->getBody()->getContents();
    $decoded = Json::decode($response);

    return $decoded;
  }


  public static function getLists($space, $token = NULL)
  {
    if (empty($token)) {
      $config = \Drupal::config('simple_conreg.clickup');
      $token = $config->get('clickup.token');
    }
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    $response = $client->get($base_url . "/space/$space/list" , [
      'verify' => true,
      'headers' => [
        'Content-type' => 'application/json',
        'Authorization' => $token,
      ],
    ])->getBody()->getContents();
    $decoded = Json::decode($response);

    return $decoded;
  }

  public static function getFolders($space, $token = NULL)
  {
    if (empty($token)) {
      $config = \Drupal::config('simple_conreg.clickup');
      $token = $config->get('clickup.token');
    }
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    $response = $client->get($base_url . "/space/$space/folder?archived=false" , [
      'verify' => true,
      'headers' => [
        'Content-type' => 'application/json',
        'Authorization' => $token,
      ],
    ])->getBody()->getContents();
    $decoded = Json::decode($response);

    return $decoded;
  }

  /**
   * Create a ClickUp task.
   *
   * Parameters: List ID, Assignees, Name, Description. Optional: Token.
   */
  public static function createTask($list, $assignees, $name, $description, $status, $token = NULL, &$message=NULL)
  {
    if (empty($token)) {
      $config = \Drupal::config('simple_conreg.clickup');
      $token = $config->get('clickup.token');
    }
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    if (is_array($assignees))
      $assigneeArray = $assignees;
    else
      $assigneeArray = explode(',', $assignees);
    try {
      $body = [
        'name' => $name,
        'markdown_description' => $description,
        'assignees' => $assigneeArray,
        'status' => $status,
        'priority' => NULL,
        'due_date' => NULL,
        'due_date_time' => false,
        'start_date' => NULL,
        'start_date_time' => false,
        'notify_all' => true,
        'parent' => NULL,
        'links_to' => NULL
      ];
      $response = $client->post($base_url . "/list/$list/task", [
        'verify' => true,
        'body' => Json::encode($body),
        'headers' => [
          'Content-type' => 'application/json',
          'Authorization' => $token,
        ],
      ])->getBody()->getContents();
      $decoded = Json::decode($response);
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
      $response_info = Json::decode($response->getBody()->getContents());
      $message = t('Failed to create ClickUp task with error: @error (@code).', ['@error' => $response_info['err'], '@code' => $response_info['ECODE']]);
      watchdog_exception('Remote API Connection', $e, $message);
      return FALSE;
    }
    
    return $decoded['id'];
  }

  /**
   * Update a ClickUp task.
   *
   * Parameters: List ID, Assignees, Name, Description. Optional: Token.
   */
  public static function updateTask($taskId, $assignees, $name, $description, $status, $token = NULL, &$message=NULL)
  {
    if (empty($token)) {
      $config = \Drupal::config('simple_conreg.clickup');
      $token = $config->get('clickup.token');
    }
    $base_url = 'https://api.clickup.com/api/v2';
    $client = \Drupal::httpClient();
    if (is_array($assignees))
      $assigneeArray = $assignees;
    else
      $assigneeArray = explode(',', $assignees);
    try {
      $body = [
        'name' => $name,
        'markdown_description' => $description,
        'assignees' => ['add' => $assigneeArray],
        'status' => $status,
        'notify_all' => true,
        "archived" => false,
      ];
      $response = $client->put($base_url . "/task/$taskId", [
        'verify' => true,
        'body' => Json::encode($body),
        'headers' => [
          'Content-type' => 'application/json',
          'Authorization' => $token,
        ],
      ])->getBody()->getContents();
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
      $response_info = Json::decode($response->getBody()->getContents());
      $message = t('Failed to create ClickUp task with error: @error (@code).', ['@error' => $response_info['err'], '@code' => $response_info['ECODE']]);
      watchdog_exception('Remote API Connection', $e, $message);
      return FALSE;
    }

    return TRUE; // Success.
  }

  /**
   * Create any required tasks for member.
   *
   * Parameters: Event ID, Member ID, Options array, optional Config for event.
   */
  public static function createMemberTasks($eid, $mid, $options, $config=NULL)
  {
    $clickupConfig = \Drupal::config('simple_conreg.clickup');
    $token = $clickupConfig->get('clickup.token');

    // If event config not passed in, load it.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    
    // Load the member record and get name.
    $member = SimpleConregStorage::load(['eid' => $eid, 'mid' => $mid]);
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    
    $optionTitles = SimpleConregFieldOptions::getFieldOptionsTitles($eid, $config);

    // Loop through each option group and check if any options set.
    foreach ($config->get('clickup_option_groups') as $groupName => $groupVals) {
      $groupMapping = $groupVals[option_mapping];
      
      // Check if ClickUp Task ID stored.
      $clickUpTask = self::getMemberClickupOption($mid, $groupName);

      $assignees = [];
      $taskOptions = [];
      $changed = FALSE;
      foreach (explode("\n", $groupMapping) as $mappingLine) {
        list($optId, $memberIds) = explode('|', $mappingLine);
        if ($options[$optId]['option'] || $options[$optId]['is_selected']) {
          if (isset($options[$optId]['changed']) && $options[$optId]['changed']) {
            $changed = TRUE;
          }
          foreach (explode(',', $memberIds) as $memberId) {
            $assignees[$memberId] = $memberId;
          }
          $taskOptions[$optId] = $optionTitles[$optId];
        }
      }

      // Set up variables for ClickUp task.
      $taskName = str_replace('[name]', $memberName, $groupVals['task_title']);
      $link = t('[@text](@url)', ['@text' => $groupVals['link_text'], '@url' => $groupVals['link_url']]);
      $taskDescription = str_replace(['[name]', '[options]', '[link]'],
                                     [$memberName, implode("\n", $taskOptions), $link], 
                                     $groupVals['task_description']);
      $status = $groupVals['task_status'];
      $assignees = array_values($assignees);

      if (count($assignees)) {
        if (empty($clickUpTask)) {
          $clickUpTask = self::createTask($groupVals['list_id'], $assignees, $taskName, $taskDescription, $status, $token);
          self::insertMemberClickupOption($mid, $groupName, $clickUpTask);
        }
        elseif ($changed) {
          $date = new DrupalDateTime('now');
          self::updateTask($clickUpTask, $assignees, t('@task [updated on @date]', ['@task' => $taskName, '@date' => $date->format('Y-m-d')]), $taskDescription, $status, $token);
          self::updateMemberClickupOption($mid, $groupName, $clickUpTask);
        }
        // if task exists and no changes, don't save.
      }
    }
  }

  /*
   * DB functions for ClickUp table. May move to a separate class later.
   */
  public static function insertMemberClickupOption($mid, $optionGroup, $clickUpTaskId)
  {
    $connection = \Drupal::database();
    
    $connection->insert('conreg_member_clickup_options')
      ->fields([
        'mid' => $mid,
        'option_group' => $optionGroup,
        'clickup_task_id' => $clickUpTaskId,
        'update_date' => time(),
      ])
      ->execute();
  }

  public static function updateMemberClickupOption($mid, $optionGroup, $clickUpTaskId)
  {
    $connection = \Drupal::database();
    
    $connection->update('conreg_member_clickup_options')
      ->fields([
        'clickup_task_id' => $clickUpTaskId,
        'update_date' => time(),
      ])
      ->condition('mid', $mid)
      ->condition('option_group', $optionGroup)
      ->execute();
  }

  public static function getMemberClickupOption($mid, $optionGroup)
  {
    $connection = \Drupal::database();
    
    $select = $connection->select('conreg_member_clickup_options', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'clickup_task_id');
    $select->condition('m.mid', $mid);
    $select->condition('m.option_group', $optionGroup);
    
    return $select->execute()->fetchField(); // Only selecting one field.
  }
  
  public static function getMembersWithoutTasks($eid, $optids, $count) {
    $connection = \Drupal::database();
    
    $select = $connection->select('conreg_members', 'm');
    $select->join('conreg_member_options', 'o', 'm.mid=o.mid');
    $select->leftJoin('conreg_member_clickup_options', 'c', 'm.mid=c.mid');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition('o.optid', $optids, 'IN');
    $select->condition('o.is_selected', 1);
    $select->isNull('c.clickup_task_id');
    $select->distinct();
    
    if ($count)
      return $select->countQuery()->execute()->fetchField(); // Count the number of rows.
    else
      return $select->execute()->fetchAll(\PDO::FETCH_ASSOC); // Only selecting one field.
  }
}
