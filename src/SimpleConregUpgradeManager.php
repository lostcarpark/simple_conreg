<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregUpgradeManager
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregUpgradeManager
{
  var $eid;
  var $lead_mid;
  var $upgrades;

  // Construct upgrade manager. Store event ID and initialise array.
  public function __construct($eid = 1)
  {
    $this->eid = $eid;
    $this->upgrades = [];
  }

  // Add a new upgrade to upgrade manager.
  public function add(SimpleConregUpgrade $upgrade) 
  {
    if (empty($this->lead_mid))
      $this->lead_mid = $upgrade->lead_mid;
    else
      $upgrade->lead_mid = $this->lead_mid; 
    $this->upgrades[] = $upgrade;
  }
  
  public function count()
  {
    return count($this->upgrades);
  }
  
  // Loop through all members, add up total price, and return to caller.
  public function getTotalPrice() 
  {
    $total = 0;
    foreach($this->upgrades as $upgrade) {
      $total =+ $upgrade->upgradePrice;
    }
    return $total;
  }
  
  // Save all upgrades to conreg_upgrades table.
  public function saveUpgrades()
  {
    SimpleConregUpgradeStorage::deleteUnpaidByLeadMid($this->lead_mid);

    $total = $this->getTotalPrice();
    // Create array containing member IDs of members to upgrade.
    foreach($this->upgrades as $upgrade) {
      $upgrade->saveUpgrade($total);
    }
    return $this->lead_mid;
  }
  
  public function loadUpgrades($leadMid, $isPaid)
  {
    $this->upgrades = [];
    $this->lead_mid = $leadMid;
    $upgrades = SimpleConregUpgradeStorage::loadAll(['lead_mid' => $this->lead_mid, 'is_paid' => $isPaid]);
    if (!empty($upgrades)) {
      foreach ($upgrades as $upgrade) {
        $this->add(new SimpleConregUpgrade($this->eid, $upgrade['mid'], NULL, $this->lead_mid, $upgrade['from_type'], $upgrade['from_days'], 
                                           $upgrade['to_type'], $upgrade['to_days'], $upgrade['to_badge_type'], $upgrade['upgrade_price']));
      }
      return TRUE;
    }
    else
      return FALSE;
  }
  
  public function completeUpgrades($payment_amount, $payment_method, $payment_id)
  {
    foreach ($this->upgrades as $upgrade)
      $upgrade->complete($this->lead_mid, $payment_amount, $payment_method, $payment_id);
  }
  
}