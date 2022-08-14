<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregConfig.
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Config\ImmutableConfig;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregConfig {

  /**
   * Cet current event config.
   *
   * @param int $eid Event ID.
   * @return ImmutableConfig
   */
  public static function getConfig($eid): ImmutableConfig {

    // Get event configuration from config.
    $config = \Drupal::config('simple_conreg.settings.'.$eid);
    if (empty($config->get('payments.system'))) {
      $config = \Drupal::config('simple_conreg.settings');
    }
    
    return $config;
  }
    
  /**
   * Get specified fieldset config.
   *
   * Parameters: Event ID, Fieldset.
   */
  public static function getFieldsetConfig($eid, $fieldset = 0) {

    // If fieldset is not null or zero.
    if (!empty($fieldset)) {
      $configName = 'simple_conreg.settings.'.$eid.'.fieldset.'.$fieldset;
      $config = \Drupal::config($configName);
      if (empty($config->get('fields.first_name_label'))) {
        // No field settings have been stored, so use main config.
        $config = SimpleConregConfig::getConfig($eid);
      }
    } else {
      // Using default fields settings from main event config.
      $config = SimpleConregConfig::getConfig($eid);
    }

    return $config;

  }

}

