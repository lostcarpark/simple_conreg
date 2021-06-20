<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\FieldOptions.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class FieldOptionPermissions {

  /**
   * No construction required.
   */
  public function __construct()
  {
  }

  /**
   * Get permissions for ConReg field options.
   *
   * @return array
   *   Permissions array.
   */
  public static function permissions()
  {
    $permissions = [];

    $events = SimpleConregEventStorage::eventOptions();
    foreach ($events as $event) {
      $fieldOptions = FieldOptions::getFieldOptions($event['eid']);
      foreach ($fieldOptions->getFieldOptionList() as $option) {
        $permissions += [
          'view field option ' . $option['optid'] . ' event ' . $event['eid'] => [
            'title' => t('View data for field option %option for event %event', array('%option' => $option['option_title'], '%event' => $event['event_name'])),
          ]
        ];
      }
    }

    return $permissions;
  }
}
