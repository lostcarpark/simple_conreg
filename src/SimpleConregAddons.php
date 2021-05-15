<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAddons
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provide array_key_first function for versions of PHP before 7.3.
 */
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }
}

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
   * get addons from event config.
   *
   * Parameters: Event ID.
   */
  public static function getAddon($config, $addonVals, $addOnOptions, $member, $callback, FormStateInterface $form_state, $mid = NULL)
  {

    $addons = ['#tree' => TRUE];
    $fs_addons = [];
    
    // If member ID passed in, read values from database.
    $saved = [];
    if (!is_null($mid)) {
      $result = SimpleConregAddonStorage::loadAll(['mid' => $mid]);
      foreach ($result as $entry) {
        $saved[$entry['addon_name']] = $entry;
      }
    }

    foreach ($config->get('add-ons') as $addOnId => $addOnVals) {
      // Add add-on to form_state.
      $fs_addons[$addOnId] = $addOnId;
      // If add-on set, get values.
      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);          
      // Check add-on is enabled.
      if ($addon['active'] == 1) {
        // Get add options...
        list($addOnOptions, $addOnPrices) = self::memberAddons($addon['options']);
        // If global is set, only display if there's a member number.
        if ((!empty($member) && !$addon['global']) || (empty($member) && $addon['global']) || $member == -1) {
          if ($member == -1) // Single member on edit form.
            $id = 'member_addon_'.$addOnId.'_info';
          elseif (!empty($member)) // Numbered member of Reg form.
            $id = 'member_addon_'.$addOnId.'_info_'.$member;
          else // Global add-ons on Reg form.
            $id = 'global_addon_'.$addOnId.'_info';
      
          $addons[$addOnId] = [];

          if (!empty($addon['label'])) {
            // Set the add-on options drop-down.
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
            // Now check if editing existing member.
            if (!is_null($mid)) {
              if (isset($saved[$addOnId])) {
                if ($saved[$addOnId]['is_paid'] == 1) {
                  // Add-on saved and paid, so replace drop-down with non-editable label.
                  $addons[$addOnId]['option'] = [
                    '#markup' => '<strong>' . $addon['label'] . '</strong><br />' . $addOnOptions[$saved[$addOnId]['addon_option']],
                    '#prefix' => '<div>',
                    '#suffix' => '</div>',
                  ];
                }
                else {
                  // Add-on saved, but not paid, so allow user to change their choice.
                  $addons[$addOnId]['option']['#default_value'] = $saved[$addOnId]['addon_option'];
                }
              }
              else {
                // Add-on not saved, so default to first option (otherwise member will be forced to pick an option every time they edit).
                $addons[$addOnId]['option']['#default_value'] = array_key_first($addOnOptions);
              }
            }
      
            $addons[$addOnId]['extra'] = array(
              '#prefix' => '<div id="'.$id.'">',
              '#suffix' => '</div>',
            );

            // Check if something other than the first value in add-on list selected. Display add-on info field if so. Use current(array_keys()) to get first add-on option.
            $info = (isset($addOnVals['info']) ? $addOnVals['info'] : []);

            if ((!empty($addonVals[$addOnId]['option']) && $addonVals[$addOnId]['option']!=current(array_keys($addOnOptions)) ||
                (isset($saved[$addOnId]))) &&
                !empty($info['label'])) {
              $addons[$addOnId]['extra']['info'] = array(
                '#type' => 'textfield',
                '#title' => $info['label'],
                '#description' => $info['description'],
              );
              if (isset($saved[$addOnId])) {
                 $addons[$addOnId]['extra']['info']['#default_value'] = $saved[$addOnId]['addon_info'];
              }
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
              '#attributes' => array(
                'class' => ["edit-free-amt"],
              ),
            );
            if (isset($saved[$addOnId])) {
              if (isset($saved[$addOnId]) && $saved[$addOnId]['is_paid'] ) {
                $addons[$addOnId]['free_amount'] = [
                  '#markup' => '<strong>' . $free['label'] . '</strong><br />' . $saved[$addOnId]['addon_amount'],
                  '#prefix' => '<div>',
                  '#suffix' => '</div>',
                ];
              }
              else {
                $addons[$addOnId]['free_amount']['#default_value'] = $saved[$addOnId]['addon_amount'];
              }
            }
          }
        }
      }
    }
    $form_state->set('addons', $fs_addons);
    return $addons;
  }
  
  public static function getAllAddonPrices($config, $form_values)
  {
    $addOnTotal = 0;
    $addOnGlobal = 0;
    $addOnGlobalMinusFree = 0;
    $addOnMembers = [];
    $addOnMembersMinusFree = [];
    
    $memberQty = (isset($form_values['global']['member_quantity']) ? $form_values['global']['member_quantity'] : 1);
    for ($cnt = 1; $cnt<=$memberQty+1; $cnt++) {
      // No form values, so return a dummy entry.
      $addOnMembers[$cnt] = 0;
      $addOnMembersMinusFree[$cnt] = 0;
    }
    
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
          $option = (isset($form_values['payment']['global_add_on'][$addOnId]['option']) ? $form_values['payment']['global_add_on'][$addOnId]['option'] : '');
          if (!empty($option)) {
            $addOnGlobal += $addOnPrices[$option];
            $addOnTotal += $addOnPrices[$option];
            $addOnGlobalMinusFree += $addOnPrices[$option];
          }
          $free_amount = (isset($form_values['payment']['global_add_on'][$addOnId]['free_amount']) ? $form_values['payment']['global_add_on'][$addOnId]['free_amount'] : '');
          if (!empty($free_amount)) {
            $addOnGlobal += $free_amount;
            $addOnTotal += $free_amount;
            // Don't add to $addOnGlobalMinusFree.
          }
        }
        else {
          if (isset($form_values) && array_key_exists('members', $form_values)) {
            foreach ($form_values['members'] as $memberKey => $memberVals) {
              $member = substr($memberKey, 6); // memberKey should be in the form "member1". We want the 1.

              if (!array_key_exists($member, $addOnMembers))
                $addOnMembers[$member] = 0;  // Check if member total has been initialised.
              //$id = 'member_addon_'.$addOnId.'_info_'.$member;
              $option = (isset($memberVals['add_on'][$addOnId]['option']) ? $memberVals['add_on'][$addOnId]['option'] : '');
              if (!empty($option)) {
                $addOnPrice = floatval($addOnPrices[$option]);
                $addOnMembers[$member] += $addOnPrice;
                $addOnTotal += $addOnPrice;
                $addOnMembersMinusFree[$member] += $addOnPrice;
              }
              $free_amount = (isset($memberVals['add_on'][$addOnId]['free_amount']) ? $memberVals['add_on'][$addOnId]['free_amount'] : 0);
              if (!empty($free_amount)) {
                $addOnMembers[$member] += $free_amount;
                $addOnTotal += $free_amount;
                // Dpn't add to $addOnMembersMinusFree.
              }
            }
          }
        }
      }
    }

    return [$addOnTotal, $addOnGlobal, $addOnGlobalMinusFree, $addOnMembers, $addOnMembersMinusFree];
  }
  
  //
  // Save add-ons for single member on Edit form.
  //
  
  function saveMemberAddons($config, $form_values, $mid)
  {
    foreach ($config->get('add-ons') as $addOnName => $addOnVals) {
      // If add-on set, get values.
      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);          
      // Check add-on is enabled.
      if ($addon['active'] == 1) {
        // Get add options...
        list($addOnOptions, $addOnPrices) = self::memberAddons($addon['options']);

        $saved = SimpleConregAddonStorage::load(['mid' => $mid, 'addon_name' => $addOnName]);

        $id = 'member_addon_'.$addOnName.'_info';
        $price = 0;
        if (isset($saved) && isset($saved['addonid']))
          $insert = ['addonid' => $saved['addonid'],
                     'addon_amount' => $price];
        else
          $insert = ['mid' => $mid,
                    'addon_name' => $addOnName,
                    'addon_amount' => $price,
                    'is_paid' => 0];
        if (!empty($form_values['member']['add_on'][$addOnName]['option'])) {
          $option = $form_values['member']['add_on'][$addOnName]['option'];
          $insert['addon_option'] = $option;
          $price += $addOnPrices[$option];
          if (isset($form_values['member']['add_on'][$addOnName]['extra']['info'])) {
            $insert['addon_info'] = $form_values['member']['add_on'][$addOnName]['extra']['info'];
          }
        }
        if (!empty($form_values['member']['add_on'][$addOnName]['free_amount']) && $form_values['member']['add_on'][$addOnName]['free_amount'] > 0) {
          $price += $form_values['member']['add_on'][$addOnName]['free_amount'];
        }
        if ($price > 0) {
          $insert['addon_amount'] = $price;
          if (isset($saved) && isset($saved['addonid']))
            SimpleConregAddonStorage::update($insert);
          else
            SimpleConregAddonStorage::insert($insert);
        }
        else {
          // If there's a previously saved addon, delete it.
          if (isset($saved) && isset($saved['addonid']))
            SimpleConregAddonStorage::delete(['addonid' => $saved['addonid']]);
        }
      }
    }
  }

  //
  // Save the add-ons from for each member.
  //
  
  public static function saveAddons($config, $form_values, $memberIDs, SimpleConregPayment $payment = NULL)
  {
    $payId = $payment->getId();
    foreach ($config->get('add-ons') as $addOnName => $addOnVals) {
      // If add-on set, get values.
      $addon = (isset($addOnVals['addon']) ? $addOnVals['addon'] : []);          
      // Check add-on is enabled.
      if ($addon['active'] == 1) {
        // Get add options...
        list($addOnOptions, $addOnPrices) = self::memberAddons($addon['options']);
        // If global is set, only display if there's a member number.
        if ($addon['global']) {
          $id = "global_addon_'.$addOnName.'_info";
          $mid = $memberIDs[1]; // Global options get saved to first member.
          $price = 0;
          $insert = ['mid' => $mid,
                    'addon_name' => $addOnName,
                    'addon_amount' => $price,
                    'payid' => $payId,
                    'is_paid' => 0];
          if (!empty($form_values['payment']['global_add_on'][$addOnName]['option'])) {
            $option = $form_values['payment']['global_add_on'][$addOnName]['option'];
            $insert['addon_option'] = $option;
            $price += $addOnPrices[$option];
            if (isset($form_values['payment']['global_add_on'][$addOnName]['extra']['info'])) {
              $insert['addon_info'] = $form_values['payment']['global_add_on'][$addOnName]['extra']['info'];
            }
          }
          if (!empty($form_values['payment']['global_add_on'][$addOnName]['free_amount']) && $form_values['payment']['global_add_on'][$addOnName]['free_amount'] > 0) {
            $price += $form_values['payment']['global_add_on'][$addOnName]['free_amount'];
          }
          // Only insert if add-on has a price.
          if ($price > 0) {
            $insert['addon_amount'] = $price;
            SimpleConregAddonStorage::insert($insert);
            // Add a payment line for the global add-on.
            $payment->add(new SimpleConregPaymentLine($mid,
                                                 'addon',
                                                 t("Add-on @add_on",
                                                    array('@add_on' => $addOnName)),
                                                 $price));
          }
        }
        else {
          foreach ($form_values['members'] as $memberKey => $memberVals) {
            $member = substr($memberKey, 6); // memberKey should be in the form "member1". We want the 1.

            $id = 'member_addon_'.$addOnName.'_info_'.$member;
            $mid = $memberIDs[$member];
            $first_name = $memberVals['first_name'];
            $last_name = $memberVals['last_name'];
            $price = 0;
            $insert = ['mid' => $mid,
                      'addon_name' => $addOnName,
                      'addon_amount' => $price,
                      'payid' => $payId,
                      'is_paid' => 0];
            if (!empty($memberVals['add_on'][$addOnName]['option'])) {
              $option = $memberVals['add_on'][$addOnName]['option'];
              $insert['addon_option'] = $option;
              $price += $addOnPrices[$option];
              if (isset($memberVals['add_on'][$addOnName]['extra']['info'])) {
                $insert['addon_info'] = $memberVals['add_on'][$addOnName]['extra']['info'];
              }
            }
            if (!empty($memberVals['add_on'][$addOnName]['free_amount']) && $memberVals['add_on'][$addOnName]['free_amount'] > 0) {
              $price += $memberVals['add_on'][$addOnName]['free_amount'];
            }
            if ($price > 0) {
              $insert['addon_amount'] = $price;
              SimpleConregAddonStorage::insert($insert);
            }
            // Add a payment line for the add-on.
            $payment->add(new SimpleConregPaymentLine($mid,
                                                 'addon',
                                                 t("Add-on @add_on for @first_name @last_name",
                                                    array('@add_on' => $addOnName,
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
  public static function markPaid($payId, $paymentRef)
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
  public static function getMemberAddons($config, $mid)
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
