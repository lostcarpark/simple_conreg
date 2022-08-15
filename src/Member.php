<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregRegistrationForm
 */

namespace Drupal\simple_conreg;

/**
 * Store a member's details.
 */
class Member
{

  public $options;

  /**
   * Constructs a new Member object.
   */
  public function __construct()
  {
  }

  /**
   * Create a new member from an array of values.
   * @param array $details
   * @return Member
   */
  public static function newMember($details)
  {
    $member = new Member();
    foreach ($details as $key => $value) {
      $member->$key = $value;
    }
    return $member;
  }

  /**
   * Load member by member ID and create member object.
   * @param int $mid
   * @return Member
   */
  public static function loadMember($mid)
  {
    $member = self::newMember(SimpleConregStorage::load(['mid' => $mid]));

    // Add member options to member object.
    $member->options = MemberOption::loadAllMemberOptions($mid);

    return $member;
  }

  /**
   * Load a member using event and member number and create member object.
   * @param int $eid
   * @param int $memberNo
   * @return Member
   */
  public static function loadMemberByMemberNo($eid, $memberNo)
  {
    $member = self::newMember(SimpleConregStorage::load(['eid' => $eid, 'member_no' => $memberNo, 'is_deleted' => 0]));

    // Add member options to member object.
    $member->options = MemberOption::loadAllMemberOptions($member->mid);

    return $member;
  }

  /**
   * Save the member to the conreg_members table.
   */
  public function saveMember()
  {
    // Transfer object members into array.
    $entry = [];
    foreach ($this as $field => $value) {
      if (!is_array($value) && !is_object($value))
        $entry[$field] = $value;
    }
    $entry['update_date'] = time();
    // If no mid set, inserting new member.
    if (empty($this->mid)) {
      $return = SimpleConregStorage::insert($entry);
      if (isset($return)) {
        $this->mid = $return;
        $this->updateOptionMids();
      }
      if (empty($this->lead_mid)) {
        // For lead_mid not passed in, must be first member, so update lead_mid to mid.
        $this->lead_mid = $return;
        // Update first member with own member ID as lead member ID.
        $update = array('mid' => $this->mid, 'lead_mid' => $this->lead_mid);
        $result = SimpleConregStorage::update($update);
      }
      // Update member options.
      $this->saveMemberOptions();
      // Invoke member added hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_added', ['member' => $this]);
    }
    else {
      // Updating an existing member.
      $return = SimpleConregStorage::update($entry);
      // Update member options.
      $this->saveMemberOptions();
      // Invoke member updated hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_updated', ['member' => $this]);
    }

    return $return;
  }

  public function saveMemberOptions()
  {
    // Update member field options.
    if (is_array($this->options)) {
      foreach ($this->options as $option) {
        $option->mid = $this->mid;
        $option->saveMemberOption();
      }
    //FieldOptions::updateOptionFields($this->mid, $this->options);
    }
  }

  public function deleteMember()
  {
    $entry = array(
      'is_deleted' => 1,
      'mid' => $this->mid,
    );
    // Update the member record.
    if ($return = SimpleConregStorage::update($entry)) {
      // Invoke member deleted hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_deleted', ['member' => $this]);
    }
  }

  public function setOptions($options)
  {
    $this->options = $options;
    $this->updateOptionMids();
  }

  /**
   * If member has member ID, apply to all options.
   */
  public function updateOptionMids()
  {
    if (isset($this->mid)) {
      // First, set the member ID for all options.
      foreach ($this->options as $optid => $option) {
        $this->options[$optid]->mid = $this->mid;
      }
    }
  }

  public function getOptions()
  {
    return $this->options;
  }

  public function fieldDisplay($field)
  {
    $config = SimpleConregConfig::getConfig($this->eid);

    switch ($field) {
      case 'member_no':
        if (empty($this->member_no))
          return "";

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
            $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
          }
          return implode(', ', $dayDescs);
        }
        return '';

      case 'badge_type':
        $badgeTypes = SimpleConregOptions::badgeTypes($this->eid, $config);
        return isset($badgeTypes[$this->badge_type]) ? $badgeTypes[$this->badge_type] : $this->badge_type;

      case 'communication_method':
        $communicationsOptions = SimpleConregOptions::communicationMethod($this->eid, $config);
        return isset($communicationsOptions[$this->communication_method]) ? $communicationsOptions[$this->communication_method] : $this->communication_method;

      case 'display':
        $displayOptions = SimpleConregOptions::display();
        return isset($displayOptions[$this->display]) ? $displayOptions[$this->display] : $this->display;

      case 'country':
        $countryOptions = SimpleConregOptions::memberCountries($this->eid, $config);
        return $countryOptions[$this->country] ?: $this->country;

      case 'join_date':
        return date('Y-m-d H:i:s', $this->join_date);

      case 'is_approved':
        return empty($this->is_approved) ? t('No') : t('Yes');

      case 'is_paid':
        return empty($this->is_paid) ? t('No') : t('Yes');

      case 'is_deleted':
        return empty($this->is_deleted) ? t('No') : t('Yes');

      default:
        return $this->$field;
    }
  }

}
