<?php

namespace Drupal\conreg_planz;

use Drupal\Core\Database\Connection;
use Drupal\simple_conreg\Member;

/**
 *
 */
class PlanZUser {
  private PlanZ $planz;
  public int $mid;
  public string $badgeId;
  public ?string $password;
  public ?string $hashedPassword;
  public bool $existingParticipant = FALSE;
  public ?string $pubsname = NULL;
  public ?string $sortedpubsname = NULL;

  /**
   * Constructs a new Member object.
   */
  public function __construct(PlanZ $planz) {
    $this->planz = $planz;
    $this->password = NULL;
    $this->hashedPassword = NULL;
  }

  /**
   * Load member PlanZ link info.
   *
   * @param int $mid
   *   Member ID.
   *
   * @return bool TRUE if member details found. FALSE if member not present on PlanZ.
   */
  public function load(int $mid): bool {
    // Get regular Drupal DB connection.
    $connection = \Drupal::database();
    $select = $connection->select('conreg_planz', 'z');
    $select->addField('z', 'badgeid');
    $select->condition('z.mid', $mid);
    $badgeId = $select->execute()->fetchField();
    if (empty($badgeId)) {
      // If no linked PlanZ user, check for matching email address on PlanZ users.
      return $this->checkExistingUser($mid);
    }

    $this->mid = $mid;
    $this->badgeId = $badgeId;

    // Get the PlanZ connection to get the Participant table.
    $planZCon = $this->planz->getPlanZConnection();
    $select = $planZCon->select('Participants', 'P');
    $select->addField('P', 'pubsname');
    $select->addField('P', 'sortedpubsname');
    $select->addField('P', 'password');
    $select->condition('P.badgeid', $this->badgeId);
    $record = $select->execute()->fetchObject();
    if ($record) {
      $this->existingParticipant = TRUE;
      $this->pubsname = $record?->pubsname;
      $this->sortedpubsname = $record?->sortedpubsname;
      $this->hashedPassword = $record->password;
    }
    else {
      $this->existingParticipant = FALSE;
    }
    return TRUE;
  }

  /**
   * Check if member is an existing PlanZ user. If found, create on conreg_planz table.
   *
   * @param int $mid
   *   Member ID.
   *
   * @return bool TRUE if member exists on PlanZ
   */
  private function checkExistingUser(int $mid): bool {
    // Get member details.
    $member = Member::loadMember($mid);

    // Get the PlanZ connection to get the Participant table.
    $planZCon = $this->planz->getPlanZConnection();
    $select = $planZCon->select('CongoDump', 'C');
    $select->addField('C', 'badgeid');
    $select->condition('C.email', $member->email);
    $badgeId = $select->execute()->fetchField();

    // If false returned, member is not PlanZ user.
    if (!$badgeId) {
      return FALSE;
    }

    // Check that the user we've found isn't already linked to a different member.
    $connection = \Drupal::database();
    $select = $connection->select('conreg_planz', 'z');
    $select->addField('z', 'mid');
    $select->condition('z.badgeid', $badgeId);
    $select->condition('z.mid', $mid, '<>');
    $foundMid = $select->execute()->fetchField();

    // If member found, user already belongs to another member.
    if ($foundMid) {
      return FALSE;
    }

    // We've found a PlanZ user, and confirmed it's not assigned to another ConReg member, so update member badge ID and save to conreg_planz.
    $this->mid = $mid;
    $this->badgeId = $badgeId;
    $this->saveConregPlanZ();
    // Member is linked to a PlanZ user so return true.
    return TRUE;
  }

  /**
   * Save ConReg member to PlanZ.
   *
   * @param \Drupal\simple_conreg\Member $member
   *   The member object to save.
   * @param bool $reset
   *   If true, reset member password.
   */
  public function save(Member $member, bool $reset = FALSE) {
    $this->mid = $member->mid;

    if ($reset) {
      $this->hashedPassword = NULL;
    }

    if (empty($this->badgeId)) {
      // Member does not have badge ID, so need to create one, and save to conreg_planz table.
      $this->badgeId = $this->planz->createBadgeId($member);
      $this->saveConregPlanZ();
    }

    $planZCon = $this->planz->getPlanZConnection();

    // Always save latest member details to PlanZ.
    $this->saveCongoDump($planZCon, $member);

    // Only save other tables if password not already set.
    if (empty($this->hashedPassword)) {
      // To do: make other options for participant name available.
      $participantName = trim($member->first_name . ' ' . $member->last_name);
      $partSortName = trim($member->last_name . ' ' . $member->first_name);
      // Save the participant to set the password.
      $this->saveParticipant($planZCon, $participantName, $partSortName);
      // Loop through PlanZ permissions, and save the ones set in config.
      foreach ($this->planz->roles as $role => $value) {
        if ($value) {
          $this->saveUserRole($planZCon, $role);
        }
      }
    }
  }

  /**
   * Save the PlanZ badge ID.
   */
  private function saveConregPlanZ(): void {
    $connection = \Drupal::database();
    $connection->upsert('conreg_planz')
      ->fields(['mid' => $this->mid, 'badgeid' => $this->badgeId, 'update_date' => time()])
      ->key('mid')
      ->execute();
  }

  /**
   * Save member to CongoDump table.
   *
   * @param \Drupal\Core\Database\Connection $planZCon
   *   Connection to PlanZ database.
   * @param \Drupal\simple_conreg\Member $member
   *   The member to save.
   *
   * @return int Number of rows affected (usually 1)
   */
  private function saveCongoDump(Connection $planZCon, Member $member): int {
    $return_value = $planZCon->upsert('CongoDump')
      ->fields([
        'badgeid' => $this->badgeId,
        'firstname' => substr($member->first_name, 0, 30),
        'lastname' => substr($member->last_name, 0, 40),
        'badgename' => substr($member->badge_name, 0, 50),
        'phone' => substr($member->phone, 0, 100),
        'email' => substr($member->email, 0, 100),
        'postaddress1' => substr($member->street, 0, 100),
        'postaddress2' => substr($member->street2, 0, 100),
        'postcity' => substr($member->city, 0, 50),
        'poststate' => substr($member->county, 0, 25),
        'postzip' => substr($member->postcode, 0, 10),
        'postcountry' => substr($member->country, 0, 25),
        'regtype' => $member->badge_type,
      ])
      ->key('badgeid')
      ->execute();

    return $return_value;
  }

  /**
   * Save to the Participants table.
   *
   * @param \Drupal\Core\Database\Connection $planZCon
   *   The connection to the PlanZ database.
   * @param string $publicationName
   *   The name to save to the pubsname field.
   * @param string $sortingName
   *   The name to save to the sortedpubsname field.
   *
   * @return int Number of rows affected (normally 1)
   */
  private function saveParticipant(Connection $planZCon, ?string $publicationName = NULL, ?string $sortingName = NULL): int {
    if ($this->planz->generatePassword && empty($this->hashedPassword)) {
      $this->password = $this->generatePassword(12);
      $this->hashedPassword = password_hash(trim($this->password), PASSWORD_DEFAULT);
    }

    $fields = [
      'badgeid' => $this->badgeId,
    ];

    if (empty($this->existingParticipant)) {
      // Member not on participant table, so set default values.
      $fields['share_email'] = 1;
      $fields['data_retention'] = 0;
      $fields['interested'] = $this->planz->interestedDefault ? 1 : 0;
    }

    // Only update password if empty.
    if (!empty($this->hashedPassword)) {
      $fields['password'] = $this->hashedPassword;
    }

    // Only update publication name if none already set.
    if (empty($this->pubsname) && !empty($publicationName)) {
      $fields['pubsname'] = $publicationName;
    }

    if (empty($this->sortedpubsname) && !empty($sortingName)) {
      $fields['sortedpubsname'] = $sortingName;
    }

    $return_value = $planZCon->upsert('Participants')
      ->fields($fields)
      ->key('badgeid')
      ->execute();

    return $return_value;
  }

  /**
   * Save a user role to the UserHasPermissionRole table.
   *
   * @param \Drupal\Core\Database\Connection $planZCon
   *   Connection to PlanZ database.
   * @param int $role
   *   The ID of the role to add.
   *
   * @return int Number of rows updated
   */
  private function saveUserRole(Connection $planZCon, int $role): int {

    $return_value = $planZCon->upsert('UserHasPermissionRole')
      ->fields([
        'badgeid' => $this->badgeId,
        'permroleid' => $role,
      ])
      ->key('badgeid')
      ->execute();

    return $return_value;
  }

  private function savePasswordResetRequest(Connection $planZCon, string $email, string $selector, string $token) {

    $planZCon->update('ParticipantPasswordResetRequests')
      ->fields(['cancelled' => 1])
      ->condition('badgeid', $this->badgeId)
      ->execute();

    $return_value = $planZCon->insert('ParticipantPasswordResetRequests')
      ->fields([
        'badgeid' => $this->badgeId,
        'email' => $email,
        'selector' => $selector,
        'token' => $token,
      ])
      ->execute();

  }

  /**
   * Generate a random password of specified length.
   *
   * @param int $chars
   *   Number of characters length for password.
   *
   * @return string A random password
   */
  public function generatePassword(int $chars): string {
    $data = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz!%^*()[]{};:@#,/';
    return substr(str_shuffle($data), 0, $chars);
  }

}
