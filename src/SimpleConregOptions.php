<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregOptions.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregOptions {

  /**
   * Return list of membership types from config.
   *
   * Parameters: Optional config.
   */
  public static function memberTypes($eid, &$config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    $types = explode("\n", $config->get('member_types')); // One type per line.
    $typeVals = [];
    $publicOptions = [];
    $privateOptions = [];
    foreach ($types as $type) {
      if (!empty($type)) {
        $typeFields = array_pad(explode('|', $type), 8, '');
        list($code, $desc, $name, $price, $badgeType, $fieldset, $active, $defaultDays) = $typeFields;
        // Remove any extra spacing.
        $code = trim($code);
        $fieldset = trim($fieldset);
        // If fieldset not specified, use 0.
        if (empty($fieldset)) {
          $fieldset = 0;
        }
        $days = null;
        // If extra fields, they will contain day details.
        $days = [];
        $dayOptions = [];
        $fieldCount = count($typeFields);
        if ($fieldCount > 8) {
          for ($fieldNo = 8; $fieldNo < $fieldCount; $fieldNo++) {
            list($dayCode, $dayDesc, $dayName, $dayPrice) = array_pad(explode('~', $typeFields[$fieldNo]), 4, '');
              $dayOptions[$dayCode] = $dayDesc;
              $days[$dayCode] = (object)[
                'name' => $dayName,
                'description' => $dayDesc,
                'price' => $dayPrice,
              ];
          }
        }
        // Put description in specific array for populating drop-down. Put all options in private array, but only active options in public array.
        $privateOptions[$code] = trim($desc);
        if ($active)
          $publicOptions[$code] = trim($desc);
        // Put all other values in an associative array.
        $typeVals[$code] = (object)[
          'name' => trim($name),
          'description' => trim($desc),
          'price' => trim($price),
          'badgeType' => trim($badgeType),
          'fieldset' => trim($fieldset),
          'active' => $active,
          'defauleDays' => $defaultDays,
          'config' => SimpleConregConfig::getFieldsetConfig($eid, $fieldset),
          'days' => $days,
          'dayOptions' => $dayOptions,
        ];
      }
    }
    return (object)['publicOptions' => $publicOptions, 'privateOptions' => $privateOptions, 'types' => $typeVals];
  }

  /**
   * Return list of badge types from config.
   *
   * Parameters: Optional config.
   */
  public static function badgeTypes($eid, &$config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $types = explode("\n", $config->get('badge_types')); // One type per line.
    $badgeTypes = [];
    foreach ($types as $type) {
      list($code, $badgeType) = explode('|', $type);
      $badgeTypes[trim($code)] = trim($badgeType);
    }
    return $badgeTypes;
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberAddons($eid, &$config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $addOns = explode("\n", $config->get('add_ons.options')); // One type per line.
    $addOnOptions = array();
    $addOnPrices = array();
    foreach ($addOns as $addOn) {
      list($desc, $price) = explode('|', $addOn);
      $addOnOptions[$desc] = $desc;
      $addOnPrices[$desc] = $price;
    }
    return array($addOnOptions, $addOnPrices);
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberCountries($eid, &$config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $countries = explode("\n", $config->get('reference.countries')); // One country per line.
    $countryOptions = array();
    foreach ($countries as $country) {
      if (!empty($country)) {
        list($code, $name) = explode('|', $country);
        $countryOptions[$code] = $name;
      }
    }
    return $countryOptions;
  }

  /**
   * Return list of display options for membership list.
   */
  public static function display() {
    return ['F' => t('Full name and badge name'),
            'B' => t('Badge name only'),
            'N' => t('Not at all')];
  }

  /**
   * Return list of communications methods (currently hard coded, will be editable later).
   */
  public static function communicationMethod($eid, $config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $methods = explode("\n", $config->get('communications_method.options')); // One communications method per line.
    $methodOptions = array();
    foreach ($methods as $method) {
      list($code, $description) = explode('|', $method);
      $methodOptions[$code] = $description;
    }
    return $methodOptions;
    /* return ['E' => t('Electronic only'),
            'P' => t('Paper only'),
            'B' => t('Both electronic and paper')]; */
  }

  /**
   * Return list of payment methods.
   */
  public static function paymentMethod() {
    return ['Stripe' => t('Stripe'),
            'Bank Transfer' => t('Bank Transfer'),
            'Cash' => t('Cash'),
            'Cheque' => t('Cheque'),
            'Credit Card' => t('Credit Card'),
            'Free' => t('Free'),
            'PayPal' => t('PayPal'),
           ];
  }

  /**
   * Return yes and no.
   */
  public static function yesNo() {
    return [
      0 => t('No'),
      1 => t('Yes'),
    ];
  }

}
