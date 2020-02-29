<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAddons
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormStateInterface;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregAddons
{

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberAddons($options)
  {
    $addOns = explode("\n", $options); // One type per line.
    $addOnOptions = array();
    $addOnPrices = array();
    foreach ($addOns as $addOn) {
      if (!empty($addOn)) {
        list($name, $desc, $price) = explode('|', $addOn);
        $addOnOptions[$name] = $desc;
        $addOnPrices[$name] = $price;
      }
    }
    return array($addOnOptions, $addOnPrices);
  }

  /**
   * Cet current event config.
   *
   * Parameters: Event ID.
   */
  public static function getAddon($config, $addonVals, $addOnOptions, $member, $callback, FormStateInterface $form_state)
  {

    $addons = ['#tree' => TRUE];
    $fs_addons = [];

    foreach ($config->get('add-ons') as $addOnId => $addOnVals) {
      // Add add-on to form_state.
      $fs_addons[$addOnId] = $addOnId;
      // If add-on set, get values.
      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);          
      // Check add-on is enabled.
      if ($addon['active'] == 1) {
//dpm($addOnVals, $addOnId);
        // Get add options...
        list($addOnOptions, $addOnPrices) = self::memberAddons($addon['options']);
        // If global is set, only display if there's a member number.
        if ((!empty($member) && !$addon['global']) || (empty($member) && $addon['global'])) {
          if (!empty($member))
            $id = 'member_addon_'.$addOnId.'_info_'.$member;
          else
            $id = 'global_addon_'.$addOnId.'_info';
      
          $addons[$addOnId] = [];

          if (!empty($addon['label'])) {
            $addons[$addOnId]['option'] = array(
              '#type' => 'select',
              '#title' => $addon['label'],
              '#description' => $addon['description'],
              '#options' => $addOnOptions,
              '#required' => TRUE,
              '#ajax' => array(
                'callback' => $callback,
                'event' => 'change',
                'target' => $id,
              ),
            );
      
            $addons[$addOnId]['extra'] = array(
              '#prefix' => '<div id="'.$id.'">',
              '#suffix' => '</div>',
            );

            // Check if something other than the first value in add-on list selected. Display add-on info field if so. Use current(array_keys()) to get first add-on option.
            $info = (isset($addOnVals['info']) ? $addOnVals['info'] : []);

            if (!empty($addonVals[$addOnId]['option']) &&
                $addonVals[$addOnId]['option']!=current(array_keys($addOnOptions)) &&
                !empty($info['label'])) {
              $addons[$addOnId]['extra']['info'] = array(
                '#type' => 'textfield',
                '#title' => $info['label'],
                '#description' => $info['description'],
              );
            }
          }

          $free = (isset($addOnVals['free']) ? $addOnVals['free'] : []);

          if (isset($free['label']) && strlen($free['label'])) {
            $addons[$addOnId]['free_amount'] = array(
              '#type' => 'number',
              '#title' => $free['label'],
              '#description' => $free['description'],
              '#default_value' => '0.00',
              '#step' => '0.01',
              '#min' => 0,
            );
          }
        }
      }
    }
    $form_state->set('addons', $fs_addons);
    return $addons;
  }
  
  function getAllAddonPrices($config, $form_values)
  {
    $addOnTotal = 0;
    $addOnGlobal = 0;
    $addOnMembers = [];
    
    foreach ($config->get('add-ons') as $addOnId => $addOnVals) {
      // If add-on set, get values.
      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);          
      // Check add-on is enabled.
      if ($addon['active'] == 1) {
        // Get add options...
        list($addOnOptions, $addOnPrices) = self::memberAddons($addon['options']);
        // If global is set, only display if there's a member number.
        if ($addon['global']) {
          //$id = "global_addon_'.$addOnId.'_info";
          $option = $form_values['payment']['global_add_on'][$addOnId]['option'];
          if (!empty($option)) {
            $addOnGlobal += $addOnPrices[$option];
            $addOnTotal += $addOnPrices[$option];
          }
          $free_amount = $form_values['payment']['global_add_on'][$addOnId]['free_amount'];
          if (!empty($free_amount)) {
            $addOnGlobal += $free_amount;
            $addOnTotal += $free_amount;
          }
        }
        else {
          foreach ($form_values['members'] as $memberKey => $memberVals) {
            $member = substr($memberKey, 6); // memberKey should be in the form "member1". We want the 1.

            if (!array_key_exists($member, $addOnMembers))
              $addOnMembers[$member] = 0;  // Check if member total has been initialised.
            //$id = 'member_addon_'.$addOnId.'_info_'.$member;
            $option = $memberVals['add_on'][$addOnId]['option'];
            if (!empty($option)) {
              $addOnMembers[$member] += $addOnPrices[$option];
              $addOnTotal += $addOnPrices[$option];
            }
            $free_amount = $memberVals['add_on'][$addOnId]['free_amount'];
            if (!empty($free_amount)) {
              $addOnMembers[$member] += $free_amount;
              $addOnTotal += $free_amount;
            }
          }
        }
      }
    }

    return [$addOnTotal, $addOnGlobal, $addOnMembers];
  }
  
  //
  // Save the add-ons from for each member.
  //
  
  function saveAddons($config, $form_values, $memberIDs, SimpleConregPayment $payment)
  {
    $payId = $payment->getId();
    foreach ($config->get('add-ons') as $addOnId => $addOnVals) {
      // If add-on set, get values.
      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);          
      // Check add-on is enabled.
      if ($addon['active'] == 1) {
        // Get add options...
        list($addOnOptions, $addOnPrices) = self::memberAddons($addon['options']);
        // If global is set, only display if there's a member number.
        if ($addon['global']) {
          $id = "global_addon_'.$addOnId.'_info";
          $mid = $memberIDs[1]; // Global options get saved to first member.
          $price = 0;
          $insert = ['mid' => $mid,
                    'addon_name' => $addOnId,
                    'addon_amount' => $price,
                    'payid' => $payId];
          if (!empty($form_values['payment']['global_add_on'][$addOnId]['option'])) {
            $option = $form_values['payment']['global_add_on'][$addOnId]['option'];
            $insert['addon_option'] = $option;
            $price += $addOnPrices[$option];
            if (isset($form_values['payment']['global_add_on'][$addOnId]['extra']['info'])) {
              $insert['addon_info'] = $form_values['payment']['global_add_on'][$addOnId]['extra']['info'];
            }
          }
          if (!empty($form_values['payment']['global_add_on'][$addOnId]['free_amount']) && $form_values['payment']['global_add_on'][$addOnId]['free_amount'] > 0) {
            $price += $form_values['payment']['global_add_on'][$addOnId]['free_amount'];
          }
          // Only insert if add-on has a price.
          if ($price > 0) {
            $insert['addon_amount'] = $price;
            SimpleConregAddonStorage::insert($insert);
            // Add a payment line for the global add-on.
            $payment->add(new SimpleConregPaymentLine($mid,
                                                 'addon',
                                                 t("Add-on @add_on",
                                                    array('@add_on' => $addOnId)),
                                                 $price));
          }
        }
        else {
          foreach ($form_values['members'] as $memberKey => $memberVals) {
            $member = substr($memberKey, 6); // memberKey should be in the form "member1". We want the 1.

            $id = 'member_addon_'.$addOnId.'_info_'.$member;
            $mid = $memberIDs[$member];
            $first_name = $memberVals['first_name'];
            $last_name = $memberVals['last_name'];
            $price = 0;
            $insert = ['mid' => $mid,
                      'addon_name' => $addOnId,
                      'addon_amount' => $price,
                      'payid' => $payId];
            if (!empty($memberVals['add_on'][$addOnId]['option'])) {
              $option = $memberVals['add_on'][$addOnId]['option'];
              $insert['addon_option'] = $option;
              $price += $addOnPrices[$option];
              if (isset($memberVals['add_on'][$addOnId]['extra']['info'])) {
                $insert['addon_info'] = $memberVals['add_on'][$addOnId]['extra']['info'];
              }
            }
            if (!empty($memberVals['add_on'][$addOnId]['free_amount']) && $memberVals['add_on'][$addOnId]['free_amount'] > 0) {
              $price += $memberVals['add_on'][$addOnId]['free_amount'];
            }
            if ($price > 0) {
              $insert['addon_amount'] = $price;
              SimpleConregAddonStorage::insert($insert);
            }
            // Add a payment line for the member.
            $payment->add(new SimpleConregPaymentLine($mid,
                                                 'addon',
                                                 t("Add-on @add_on for @first_name @last_name",
                                                    array('@add_on' => $addOnId,
                                                      '@first_name' => $first_name,
                                                      '@last_name' => $last_name)),
                                                 $price));
          }
        }
      }
    }
  }
  
  /*
   * Update all addons for a payment and set is_paid to true.
   */
  function markPaid($payId, $paymentRef)
  {
    $update = [
      'payid' => $payId,
      'is_paid' => 1,
      'payment_ref' => $paymentRef,
    ];
    SimpleConregAddonStorage::updateByPayId($update);
  }
  
  /*
   * Return an array of all add-ons for a member.
   */
  function getMemberAddons($config, $mid)
  {
    $symbol = $config->get('payments.symbol');
    $addons = $config->get('add-ons');
    $memberAddons = [];
    // Fetch all paid add-ons for member, and loop through them.
    foreach (SimpleConregAddonStorage::loadAll(['mid' => $mid, 'is_paid' => 1]) as $addOpts) {
      $name = $addOpts['addon_name'];
      $memberAddon = ['name' => $name,
                      'label' => $addons[$name]['addon']['label'],
                      'option' => $addOpts['addon_option'],
                      'info_label' => $addons[$name]['info']['label'],
                      'info' => $addOpts['addon_info'],
                      'free_label' => $addons[$name]['free']['label'],
                      'amount' => $symbol.$addOpts['addon_amount'],
                      'value' => $addOpts['addon_amount'],
                      ];
      $memberAddons[$name] = (object)$memberAddon;
    }
    return $memberAddons;
  }
}
