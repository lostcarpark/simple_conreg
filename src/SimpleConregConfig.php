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
   * @param int $eid
   *   Event ID.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config object.
   */
  public static function getConfig(int $eid): ImmutableConfig {

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
   * @param int $eid
   *   The Event ID.
   * @param int $fieldset
   *   The Fieldset number.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config object.
   */
  public static function getFieldsetConfig(int $eid, int|null $fieldset = 0): ImmutableConfig {

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
