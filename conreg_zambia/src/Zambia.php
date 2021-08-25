<?php

/**
 * @file
 * Contains \Drupal\conreg_zambia\Zambia.
 */

namespace Drupal\conreg_zambia;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\Member;
use Drupal\simple_conreg\SimpleConregTokens;

class Zambia
{
  public $config;
  public $target;
  public $prefix;
  public $digits;
  public $roles;
  public $interested_default;

  /**
   * Constructs a new Member object.
   */
  public function __construct($config = NULL)
  {
    $this->config = $config;
    $this->target = $config->get('zambia.target');
    if (empty($this->target)) {
      $this->target = 'default';
    }
    $this->prefix = $config->get('zambia.prefix');
    $this->digits = $config->get('zambia.digits');
    $this->roles = $config->get('zambia.roles');
    $this->interested_default = $config->get('zambia.interested_default');
  }

  /** 
   * Get the connection to the Zambia database.
   */
 public function getZambiaConnection()
  {
    return Database::getConnection($this->target, 'zambia');
  }

  /** 
   * Test the connection and check number of records in CongoDump table.
   * Return values:
   *   NULL: DB Connection not valid.
   *   FALSE: Connected but query failed.
   *   Other: Number of records in CongoDump.
   */
  public function test(&$count)
  {
    try {
      $con = $this->getZambiaConnection();
      $count = $con->select('CongoDump', 'C')
              ->fields('C')
              ->countQuery()
              ->execute()
              ->fetchField();
      $result = TRUE;
    }
    catch (DatabaseException $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $result = FALSE;
    }
    catch (\PDOException $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $result = NULL;
    }
    return $result;
  }
  
  public function getPermissionRoles()
  {
    $con = $this->getZambiaConnection();
    $select = $con->select('PermissionRoles', 'P');
    $select->addField('P', 'permroleid');
    $select->addField('P', 'permrolename');
    $select->orderBy('P.display_order');
    return $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
  
  public function createBadgeId($memberNo)
  {
    $badgeId = $this->prefix . str_pad($memberNo, $this->digits, "0", STR_PAD_LEFT);
    return $badgeId;
  }
  
  public function sendInviteEmail(ZambiaUser $user)
  {
    // Look up member to get email.
    $member = Member::loadMember($user->mid);
    
    // Get ConReg tokens, so we can add Zambia tokens.
    $tokens = new SimpleConregTokens($member->eid, $user->mid);
    $tokens->addExtraTokens(['[zambia_user]' => $user->badgeId,
                             '[zambia_password]' => $user->password,
                             '[zambia_url]' => $this->config->get('zambia.url')]);
    
    // Set up parameters for receipt email.
    $params = ['eid' => $member->eid, 'mid' => $user->mid, 'tokens' => $tokens];
    $params['subject'] = $this->config->get('zambia_auto.template_subject');
    $params['body'] = $this->config->get('zambia_auto.template_body');
    $params['body_format'] = $this->config->get('zambia_auto.template_format');
    $module = "simple_conreg";
    $key = "template";
    $to = $member->email;
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    if (!empty($member->email))
      $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);
  }
}



