<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregUpgrade
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregUpgrade
{

  var $eid; // The event ID.
  var $mid; // The member being upgraded.
  var $lead_mid;
  var $fromType, $fromDays;
  var $toType, $toDays, $toBadgeType;
  var $upgradePrice;
  
  public function __construct($eid = 1, $mid = null, $upid = null, &$lead_mid = null,
                              $fromType = null, $fromDays = null, $toType = null, $toDays = null, $toBadgeType = null, $upgradePrice = null)
  {
    $this->eid = $eid;
    $this->mid = $mid;
    $this->lead_mid = $lead_mid;
    
    $lead_mid = $this->getLead(); // Ensure that Lead MID is passed back to caller.
    
    if (!empty($upid))
      $this->setUpgrade($upid);
    else {
      // Only use passed in values for 
      $this->fromType = $fromType;
      $this->fromDays = $fromDays;
      $this->toType = $toType;
      $this->toDays = $toDays;
      $this->toBadgeType = $toBadgeType;
      $this->upgradePrice = $upgradePrice;
    }
  }
  
  public function getLead()
  {
    if (empty($this->lead_mid) && !empty($this->mid)) {
      if ($member = SimpleConregStorage::load(['mid' => $this->mid]))
        $this->lead_mid = $member['lead_mid'];
    }
    return $this->lead_mid;
  }
  
  public function setUpgrade($upid)
  {
    $upgrades = SimpleConregOptions::memberUpgrades($this->eid);
    if (isset($upgrades->upgrades[$upid])) {
      $this->fromType = $upgrades->upgrades[$upid]->fromType;
      $this->fromDays = $upgrades->upgrades[$upid]->fromDays;
      $this->toType = $upgrades->upgrades[$upid]->toType;
      $this->toDays = $upgrades->upgrades[$upid]->toDays;
      $this->toBadgeType = $upgrades->upgrades[$upid]->toBadgeType;
      $this->upgradePrice = $upgrades->upgrades[$upid]->price;
    }
  }
  
  public function saveUpgrade($upgradeTotal)
  {
    SimpleConregUpgradeStorage::deleteUnpaidByMid($this->mid);
  
    SimpleConregUpgradeStorage::insert([
      'mid' => $this->mid,
      'eid' => $this->eid,
      'lead_mid' => $this->lead_mid,
      'from_type' => $this->fromType,
      'from_days' => $this->fromDays,
      'to_type' => $this->toType,
      'to_days' => $this->toDays,
      'to_badge_type' => $this->toBadgeType,
      'upgrade_price' => $this->upgradePrice,
      'is_paid' => 0,
      'payment_amount' => $upgradeTotal,
      'upgrade_date' => time(),
    ]);	
  }
  
  // Function to complete upgrade when payment received.
  public function complete($lead_mid, $payment_amount, $payment_method, $payment_id)
  {
    $upgrade = SimpleConregUpgradeStorage::load(['eid' => $this->eid, 'mid' => $this->mid, 'is_paid' => 0]);
    // Update upgrade record.
    $update = [
      'upgid' => $upgrade['upgid'],
      'lead_mid' => $lead_mid,
      'payment_amount' => $payment_amount,
      'payment_method' => $payment_method,
      'payment_id' => $payment_id,
      'is_paid' => 1,
      'upgrade_date' => \Drupal::time()->getRequestTime(),
    ];
    SimpleConregUpgradeStorage::update($update);
    // Fetch member record.
    if ($member = SimpleConregStorage::load(['mid' => $this->mid])) {
      // Update member type, days and price.
      $member['member_type'] = $this->toType;
      $member['days'] = $this->toDays;
      $member['badge_type'] = $this->toBadgeType;
      $member['member_price'] += $this->upgradePrice;
      $member['member_total'] = $member['member_price'] + $member['add_on_price'] + $this->upgradePrice;
      $member['update_date'] = time();
      // Save updated member.
      SimpleConregStorage::update($member);
    }
  }
}
