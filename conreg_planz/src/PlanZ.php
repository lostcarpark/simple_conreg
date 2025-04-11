<?php

namespace Drupal\conreg_planz;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\Database\Database;
use Drupal\simple_conreg\Member;
use Drupal\simple_conreg\SimpleConregTokens;

/**
 *
 */
class PlanZ {
  public readonly string $target;
  public readonly BadgeIdSource $badgeIdSource;
  public readonly string $prefix;
  public readonly int $digits;
  public readonly bool $generatePassword;
  public readonly array $roles;
  public readonly bool $interestedDefault;
  public readonly string $planZUrl;
  public readonly array $optionFields;
  public readonly bool $autoEnabled;
  public readonly bool $autoWhenConfirmed;
  public readonly string $emailTemplateSubject;
  public readonly string $emailTemplateBody;
  public readonly string $emailTemplateFormat;

  /**
   * Constructs a new Member object.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   */
  public function __construct(ImmutableConfig|NULL $config = NULL) {
    $this->target = $config->get('target') ?: 'default';
    $this->badgeIdSource = BadgeIdSource::from($config->get('badge_id_source') ?: 'mno');
    $this->prefix = $config->get('prefix') ?: '';
    $this->digits = $config->get('digits') ?: 4;
    $this->generatePassword = $config->get('generate_password') ?: FALSE;
    $this->roles = $config->get('roles') ?: [];
    $this->interestedDefault = $config->get('interested_default') ?: FALSE;
    $this->planZUrl = $config->get('url') ?: '';
    $this->optionFields = $config->get('option_fields') ?? [];
    $this->autoEnabled = $config->get('auto.enabled') ?: FALSE;
    $this->autoWhenConfirmed = $config->get('auto.when_confirmed') ?: FALSE;
    $this->emailTemplateSubject = $config->get('email.template_subject') ?: '';
    $this->emailTemplateBody = $config->get('email.template_body') ?: '';
    $this->emailTemplateFormat = $config->get('email.template_format') ?: '';
  }

  /**
   * Get the connection to the PlanZ database.
   *
   * @return \Drupal\Core\Database\Connection
   */
  public function getPlanZConnection(): Connection {
    return Database::getConnection($this->target, 'planz');
  }

  /**
   * Test the connection and check number of records in CongoDump table.
   *
   * @param int &$count
   *   Count of members.
   *
   * @return boolean TRUE for valid connection, FALSE for connected but tables not found, NULL for invalid connection
   */
  public function test(int &$count) {
    try {
      $con = $this->getPlanZConnection();
      $count = $con->select('CongoDump', 'C')
        ->fields('C')
        ->countQuery()
        ->execute()
        ->fetchField();
      $result = TRUE;
    }
    catch (ConnectionNotDefinedException $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $result = FALSE;
    }
    catch (\PDOException $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $result = NULL;
    }
    return $result;
  }

  /**
   * Get the permission roles present on PlanZ.
   *
   * @return array The array of permission roles
   */
  public function getPermissionRoles(): array {
    $con = $this->getPlanZConnection();
    $select = $con->select('PermissionRoles', 'P');
    $select->addField('P', 'permroleid');
    $select->addField('P', 'permrolename');
    $select->orderBy('P.display_order');
    return $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Create PlanZ badge ID for member.
   *
   * @param \Drupal\simple_conreg\Member $member
   *
   * @return string
   */
  public function createBadgeId(Member $member): string {
    $memberRef = match($this->badgeIdSource) {
      BadgeIdSource::MemberID => $member->mid,
      BadgeIdSource::MemberNumber => $member->member_no,
      default => $member->mid
    };
    $badgeId = $this->prefix . str_pad($memberRef, $this->digits, "0", STR_PAD_LEFT);
    return $badgeId;
  }

  /**
   * Send email invite to new PlanZ user.
   *
   * @param PlanZUser $user
   *   The PlanZ user to send invite to.
   */
  public function sendInviteEmail(PlanZUser $user) {
    // Look up member to get email.
    $member = Member::loadMember($user->mid);

    // Get ConReg tokens, so we can add PlanZ tokens.
    $tokens = new SimpleConregTokens($member->eid, $user->mid);
    $extraTokens = [
      '[planz_user]' => $user->badgeId,
      '[planz_url]' => $this->planZUrl,
    ];
    if (isset($user->password)) {
      $extraTokens['[planz_password]'] = $user->password;
    }
    $tokens->addExtraTokens($extraTokens);

    // Set up parameters for receipt email.
    $params = ['eid' => $member->eid, 'mid' => $user->mid, 'tokens' => $tokens];
    $params['subject'] = $this->emailTemplateSubject;
    $params['body'] = $this->emailTemplateBody;
    $params['body_format'] = $this->emailTemplateFormat;
    $module = "simple_conreg";
    $key = "template";
    $to = $member->email;
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    // Send confirmation email to member.
    if (!empty($member->email)) {
      return \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);
    }

    return FALSE;
  }

}
