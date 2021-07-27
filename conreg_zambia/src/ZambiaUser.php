<?php

/**
 * @file
 * Contains \Drupal\conreg_zambia\ZambiaUser.
 */

namespace Drupal\conreg_zambia;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Drupal\simple_conreg\SimpleConregConfig;

class ZambiaUser
{
  public $zambia;
  public $mid;
  public $badgeId;
  public $password;
  public $hashedPassword;

  /**
   * Constructs a new Member object.
   */
  public function __construct($zambia)
  {
    $this->zambia = $zambia;
  }
  
  public function load($mid)
  {
    // Get regular Drupal DB connection.
    $connection = \Drupal::database();
    $select = $connection->select('conreg_zambia', 'z');
    $select->addField('z', 'badgeid');
    $select->condition('z.mid', $mid);
    $badgeId = $select->execute()->fetchField();
    if (empty($badgeId)) {
      return FALSE;
    }

    $this->id = $mid;
    $this->badgeId = $badgeId;
    
    // Get the Zambia connection to get the Participant table.
    $zambiaCon = $this->zambia->getZambiaConnection();
    $select = $zambiaCon->select('Participants', 'P');
    $select->addField('P', 'password');
    $select->condition('P.badgeid', $this->badgeId);
    $this->hashedPassword = $select->execute()->fetchField();
    return TRUE;
  }
  
  public function save($member, $reset = FALSE)
  {
    $this->mid = $member->mid;
    
    if ($reset) {
      $this->hashedPassword = NULL;
    }
    
    if (empty($this->badgeId)) {
      $this->badgeId = $this->zambia->createBadgeId($member->member_no);

      // First save to Drupal database;
      $connection = \Drupal::database();
      $return_value = $connection->upsert('conreg_zambia')
          ->fields(['mid' => $this->mid, 'badgeid' => $this->badgeId, 'update_date' => time()])
          ->key('mid')
          ->execute();
    }

    $zambiaCon = $this->zambia->getZambiaConnection();
    
    // Always save latest member details to Zambia.
    $this->saveCongoDump($zambiaCon, $member);
    
    // Only save other tables if password not already set.
    if (empty($this->hashedPassword)) {
      // Save the participant to set the password.
      $this->saveParticipant($zambiaCon);
      // Loop through Zambia permissions, and save the ones set in config.
      foreach ($this->zambia->roles as $role => $value) {
        if ($value) {
          $this->saveUserRole($zambiaCon, $role);
        }
      }
    }
  }

  private function saveCongoDump($zambiaCon, $member)
  {
    $return_value = $zambiaCon->upsert('CongoDump')
        ->fields([
                'badgeid' => $this->badgeId,
                'firstname' => $member->first_name,
                'lastname' => $member->last_name,
                'badgename' => $member->badge_name,
                'phone' => $member->phone,
                'email' => $member->email,
                'postaddress1' => $member->street,
                'postaddress2' => $member->street2,
                'postcity' => $member->city,
                'poststate' => $member->county,
                'postzip' => $member->postcode,
                'postcountry' => $member->country,
                ])
        ->key('badgeid')
        ->execute();  
  }

  private function saveParticipant($zambiaCon)
  {
    if (empty($this->hashedPassword)) {
      $this->password = $this->generatePassword(12);
      $this->hashedPassword = password_hash(trim($this->password), PASSWORD_DEFAULT);
    }
    
    $return_value = $zambiaCon->upsert('Participants')
        ->fields([
                'badgeid' => $this->badgeId,
                'password' => $this->hashedPassword,
                'share_email' => 1,
                'data_retention' => 0,
                ])
        ->key('badgeid')
        ->execute();  
  }

  private function saveUserRole($zambiaCon, $role)
  {

    $return_value = $zambiaCon->upsert('UserHasPermissionRole')
        ->fields([
                'badgeid' => $this->badgeId,
                'permroleid' => $role,
                ])
        ->key('badgeid')
        ->execute();  
  }

  function generatePassword($chars) 
  {
    $data = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz!%^&*()[]{};:@#,/<>';
    return substr(str_shuffle($data), 0, $chars);
  }
}
