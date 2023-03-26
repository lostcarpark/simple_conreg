<?php

namespace Drupal\simple_conreg;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Store a member's details.
 */
class Member extends \stdClass {

  use StringTranslationTrait;

  /**
   * Member ID.
   *
   * @var int
   */
  public $mid;

  /**
   * Member options.
   *
   * @var array
   */
  public $options;

  /**
   * Constructs a new Member object.
   */
  public function __construct() {
  }

  /**
   * Create a new member from an array of values.
   *
   * @param array|false $details
   *   Database array containing member details.
   *
   * @return Member|null
   *   The newly created member.
   */
  public static function newMember(array|FALSE $details): Member|NULL {
    if (!is_array($details)) {
      return NULL;
    }
    $member = new Member();
    foreach ($details as $key => $value) {
      $member->$key = $value;
    }
    return $member;
  }

  /**
   * Load member by member ID and create member object.
   *
   * @param int $mid
   *   Member ID to load.
   *
   * @return Member
   *   Loaded member object.
   */
  public static function loadMember(int $mid): Member {
    $member = self::newMember(SimpleConregStorage::load(['mid' => $mid]));

    // Add member options to member object.
    $member->options = MemberOption::loadAllMemberOptions($mid);

    return $member;
  }

  /**
   * Load a member using event and member number and create member object.
   *
   * @param int $eid
   *   Event ID.
   * @param int $memberNo
   *   Member number within event.
   *
   * @return Member|null
   *   Loaded member object.
   */
  public static function loadMemberByMemberNo(int $eid, int $memberNo): Member|null {
    $member = self::newMember(SimpleConregStorage::load([
      'eid' => $eid,
      'member_no' => $memberNo,
      'is_deleted' => 0,
    ]));

    // Add member options to member object.
    if (!empty($member)) {
      $member->options = MemberOption::loadAllMemberOptions($member->mid);
    }

    return $member;
  }

  /**
   * Load a member by their email address.
   *
   * @param int $eid
   *   Event ID.
   * @param string $email
   *   Email of member.
   *
   * @return Member|null
   *   The member object.
   */
  public static function loadMemberByEmail(int $eid, string $email): Member|NULL {
    $row = SimpleConregStorage::load([
      'eid' => $eid,
      'email' => $email,
      'is_deleted' => 0,
    ]);
    if (empty($row)) {
      return NULL;
    }

    $member = self::newMember($row);

    // Add member options to member object.
    $member->options = MemberOption::loadAllMemberOptions($member->mid);

    return $member;
  }

  /**
   * Save the member to the conreg_members table.
   *
   * @return int
   *   Member ID of saved member.
   */
  public function saveMember(): int {
    // Check if language set, and get current active language if not.
    if (empty($this->language)) {
      $this->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }
    // Transfer object members into array.
    $entry = [];
    foreach ($this as $field => $value) {
      if (!is_array($value) && !is_object($value) && $field != 'stringTranslation') {
        $entry[$field] = $value;
      }
    }
    $entry['update_date'] = time();
    // If no mid set, inserting new member.
    if (empty($this->mid)) {
      $new_mid = SimpleConregStorage::insert($entry);
      if (isset($new_mid)) {
        $this->mid = $new_mid;
        $this->updateOptionMids();
      }
      if (empty($this->lead_mid)) {
        // For lead_mid not passed in, must be first member.
        $this->lead_mid = $new_mid;
        // Update first member with own member ID as lead member ID.
        $update = ['mid' => $this->mid, 'lead_mid' => $this->lead_mid];
        SimpleConregStorage::update($update);
      }
      // Update member options.
      $this->saveMemberOptions();
      // Invoke member added hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_added', ['member' => $this]);
    }
    else {
      // Updating an existing member.
      SimpleConregStorage::update($entry);
      // Update member options.
      $this->saveMemberOptions();
      // Invoke member updated hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_updated', ['member' => $this]);
    }

    return $this->mid;
  }

  /**
   * Save member options.
   */
  public function saveMemberOptions(): void {
    // Update member field options.
    if (is_array($this->options)) {
      foreach ($this->options as $option) {
        $option->mid = $this->mid;
        $option->saveMemberOption();
      }
    }
  }

  /**
   * Delete the member by setting is_deleted to 1.
   */
  public function deleteMember() {
    $entry = [
      'is_deleted' => 1,
      'mid' => $this->mid,
    ];
    // Update the member record.
    if (SimpleConregStorage::update($entry)) {
      // Invoke member deleted hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_deleted', ['member' => $this]);
    }
  }

  /**
   * Set the members selected options.
   *
   * @param array $options
   *   Array of member options.
   */
  public function setOptions(array $options): void {
    $this->options = $options;
    $this->updateOptionMids();
  }

  /**
   * If member has member ID, apply to all options.
   */
  public function updateOptionMids(): void {
    if (isset($this->mid)) {
      // First, set the member ID for all options.
      foreach ($this->options as $optid => $option) {
        $this->options[$optid]->mid = $this->mid;
      }
    }
  }

  /**
   * Get the member's options.
   *
   * @return array
   *   Array of member options.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Format a field correctly.
   *
   * @param string $field
   *   The name of the field to be formatted.
   *
   * @return string
   *   The formatted field value.
   */
  public function fieldDisplay(string $field): string {
    $config = SimpleConregConfig::getConfig($this->eid);

    switch ($field) {
      case 'member_no':
        if (empty($this->member_no)) {
          return "";
        }

        $digits = $config->get('member_no_digits');
        return $this->badge_type . sprintf("%0" . $digits . "d", $this->member_no);

      case 'member_type':
        $types = SimpleConregOptions::memberTypes($this->eid, $config);
        return isset($types->types[$this->member_type]) ? $types->types[$this->member_type]->name : $this->member_type;

      case 'days':
        $days = SimpleConregOptions::days($this->eid, $config);
        if (!empty($this->days)) {
          $dayDescs = [];
          foreach (explode('|', $this->days) as $day) {
            $dayDescs[] = $days[$day] ?: $day;
          }
          return implode(', ', $dayDescs);
        }
        return '';

      case 'badge_type':
        $badgeTypes = SimpleConregOptions::badgeTypes($this->eid, $config);
        return $badgeTypes[$this->badge_type] ?: $this->badge_type;

      case 'communication_method':
        $communicationsOptions = SimpleConregOptions::communicationMethod($this->eid, $config);
        return $communicationsOptions[$this->communication_method] ?: $this->communication_method;

      case 'display':
        $displayOptions = SimpleConregOptions::display();
        return $displayOptions[$this->display] ?: $this->display;

      case 'country':
        $countryOptions = SimpleConregOptions::memberCountries($this->eid, $config);
        return $countryOptions[$this->country] ?: $this->country;

      case 'join_date':
        return date('Y-m-d H:i:s', $this->join_date);

      case 'is_approved':
        return empty($this->is_approved) ? $this->t('No') : $this->t('Yes');

      case 'is_paid':
        return empty($this->is_paid) ? $this->t('No') : $this->t('Yes');

      case 'is_deleted':
        return empty($this->is_deleted) ? $this->t('No') : $this->t('Yes');

      default:
        return $this->$field;
    }
  }

}
