<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAddons
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregAddons {

  /**
   * Cet current event config.
   *
   * Parameters: Event ID.
   */
  public static function getAddon($config, $addonVals, $addOnOptions, $member, $callback) {

    $global = $config->get('add_ons.global');

    $addon = ['#tree' => TRUE];

    // If global is set, only display if there's a member number.
    if ((!empty($member) && !$global) || (empty($member) && $global)) {
      if (!empty($member))
        $id = "member_addon_info_".$member;
      else
        $id = "global_addon_info";
  dd($id);
  
      if (!empty($config->get('add_ons.label'))) {
        $addon['option'] = array(
          '#type' => 'select',
          '#title' => $config->get('add_ons.label'),
          '#description' => $config->get('add_ons.description'),
          '#options' => $addOnOptions,
          '#required' => TRUE,
          '#ajax' => array(
            'callback' => $callback,
            'event' => 'change',
            'target' => $id,
          ),
        );
  
        $addon['extra'] = array(
          '#prefix' => '<div id="'.$id.'">',
          '#suffix' => '</div>',
        );
  
        // Check if something other than the first value in add-on list selected. Display add-on info field if so. Use current(array_keys()) to get first add-on option.
        if (!empty($addonVals['option']) &&
            $addonVals['option']!=current(array_keys($addOnOptions)) &&
            !empty($config->get('add_on_info.label'))) {
          $addon['extra']['info'] = array(
            '#type' => 'textfield',
            '#title' => $config->get('add_on_info.label'),
            '#description' => $config->get('add_on_info.description'),
          );
        }
      }

      if (!empty($config->get('add_on_free.label'))) {
        $addon['free_amount'] = array(
          '#type' => 'number',
          '#title' => $config->get('add_on_free.label'),
          '#description' => $config->get('add_on_free.description'),
          '#default_value' => '0.00',
          '#min' => 0,
          '#ajax' => array(
            'callback' => $callback,
            'event' => 'change',
            'target' => $id,
          ),
        );
      }
    }
    return $addon;
  }

}