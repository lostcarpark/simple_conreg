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
   * Constructs a new ModuleHandler object.
   *
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance
   */
  public function __construct()
  {
  }

  public static function newMember($details)
  {
    $member = new Member();
    foreach ($details as $key=>$value) {
      $member->$key = $value;
    }
    return $member;
  }
  
  public static function loadMember($mid)
  {
    $member = self::newMember(SimpleConregStorage::load(['mid' => $mid]));
    
    // Add member options to member object.
    $member->options = SimpleConregFieldOptions::getMemberOptionValues($mid);

    return $member;
  }
  
  public function saveMember()
  {
    // Transfer object members into array.
    $entry = [];
    foreach ($this as $field=>$value) {
      if (!is_array($value) && !is_object($value))
        $entry[$field] = $value;
    }
    $entry['update_date'] = time();
    // If no mid set, inserting new member.
    if (empty($this->mid)) {
      $return = SimpleConregStorage::insert($entry);
      if (isset($return))
        $this->mid = $return;
      if (empty($this->lead_mid)) {
        // For lead_mid not passed in, must be first member, so update lead_mid to mid.
        $this->lead_mid = $return;
        // Update first member with own member ID as lead member ID.
        $update = array('mid' => $this->mid, 'lead_mid' => $this->lead_mid);
        $result = SimpleConregStorage::update($update);
      }
      // Invoke member added hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_added', ['member' => $this]);
    }
    else {
      // Updating an existing member.
      $return = SimpleConregStorage::update($entry);
      // Invoke member updated hook.
      \Drupal::moduleHandler()->invokeAll('convention_member_updated', ['member' => $this]);
    }
    
    // Update member field options.
    if (is_array($this->options)) {
      SimpleConregFieldOptions::updateOptionFields($this->mid, $this->options);
    }
    
    return $return;
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
        return $this->badge_type . sprintf("%0".$digits."d", $this->member_no);
        break;

      case 'member_type':
        $types = SimpleConregOptions::memberTypes($this->eid, $config);
        return isset($types->types[$this->member_type]) ? $types->types[$this->member_type]->name : $this->member_type;
        break;
      
      case 'days':
        $days = SimpleConregOptions::days($this->eid, $config);
        if (!empty($this->days)) {
          $dayDescs = [];
          foreach(explode('|', $this->days) as $day) {
            $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
          }
          return implode(', ', $dayDescs);
        }
        return '';
        break;
      
      case 'badge_type':
        $badgeTypes = SimpleConregOptions::badgeTypes($this->eid, $config);
        return isset($badgeTypes[$this->badge_type]) ? $badgeTypes[$this->badge_type] : $this->badge_type;
        break;
        
      case 'communication_method':
        $communicationsOptions = SimpleConregOptions::communicationMethod($this->eid, $config);
        return isset($communicationsOptions[$this->communication_method]) ? $communicationsOptions[$this->communication_method] : $this->communication_method;
        break;
        
      case 'display':
        $displayOptions = SimpleConregOptions::display();
        return isset($displayOptions[$this->display]) ? $displayOptions[$this->display] : $this->display;
        break;
        
      case 'country':
        $countryOptions = SimpleConregOptions::memberCountries($this->eid, $config);
        return isset($countryOptions[$this->country]) ? $countryOptions[$this->country] : $this->country;
        break;
      
      case 'join_date':
        return date('Y-m-d H:i:s', $this->join_date);
        break;
      
      case 'is_approved':
        return empty($this->is_approved) ? t('No') : t('Yes');
        break;
      
      case 'is_paid':
        return empty($this->is_paid) ? t('No') : t('Yes');
        break;
      
      case 'is_deleted':
        return empty($this->is_deleted) ? t('No') : t('Yes');
        break;
      
      default:
        return $this->$field;
    }
  }

}
