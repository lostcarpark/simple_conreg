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
    static $member_types = [];

    // If member types previously loaded, just return them.
    if (!empty($member_types[$eid])) {
      return $member_types[$eid];
    }
  
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    $types = explode("\n", $config->get('member_types')); // One type per line.
    $typeVals = [];
    $firstOptions = [];
    $publicOptions = [];
    $privateOptions = [];
    $publicNames = [];
    foreach ($types as $type) {
      if (!empty($type)) {
        $typeFields = array_pad(explode('|', $type), 8, '');
        list($code, $desc, $name, $price, $badgeType, $fieldset, $allowFirst, $active, $defaultDays) = $typeFields;
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
        if (strpos($defaultDays, '~') !== FALSE) {
          list($dayDesc, $dayName) = array_pad(explode('~', $defaultDays), 2, '');
          $dayOptions[$code] = $dayDesc;
          $defaultDays = $dayName;
        }
        $fieldCount = count($typeFields);
        if ($fieldCount > 8) {
          for ($fieldNo = 8; $fieldNo < $fieldCount; $fieldNo++) {
            list($dayCode, $dayDesc, $dayName, $dayPrice) = array_pad(explode('~', $typeFields[$fieldNo]), 4, '');
              $dayOptions[$dayCode] = $dayDesc;
              $days[$dayCode] = (object)[
                'name' => $dayName,
                'description' => $dayDesc,
                'price' => $dayPrice
              ];
          }
        }
        // Put description in specific array for populating drop-down. Put all options in private array, but only active options in public array.
        $privateOptions[$code] = trim($desc);
        if ($active) {
          if ($allowFirst) {
            $firstOptions[$code] = trim($desc);
          }
          $publicOptions[$code] = trim($desc);
          $publicNames[$code] = trim($name);
        }
        // Put all other values in an associative array.
        $typeVals[$code] = (object)[
          'name' => trim($name),
          'description' => trim($desc),
          'price' => trim($price),
          'badgeType' => trim($badgeType),
          'fieldset' => trim($fieldset),
          'allowFirst' => trim($allowFirst),
          'active' => $active,
          'defaultDays' => $defaultDays,
          'config' => SimpleConregConfig::getFieldsetConfig($eid, $fieldset),
          'days' => $days,
          'dayOptions' => $dayOptions,
        ];
      }
    }

    // Stash member types in static variable in case needed again.
    $member_types[$eid] = (object)[
      'firstOptions' => $firstOptions,
      'publicOptions' => $publicOptions,
      'privateOptions' => $privateOptions,
      'publicNames' => $publicNames,
      'types' => $typeVals];

    return $member_types[$eid];
  }

  /**
   * Return list of membership types from config.
   *
   * Parameters: Optional config.
   */
  public static function memberUpgrades($eid, &$config = NULL)
  {
    static $member_upgrades = []; // Store upgrade options in a static array.

    // If member upgrades previously stored, just return them.
    if (!empty($member_upgrades[$eid])) {
      return $member_upgrades[$eid];
    }

    // If config not passed in, we need to load it.
    if (is_null($config))
      $config = SimpleConregConfig::getConfig($eid);
    
    $types = self::memberTypes($eid, $config);
    $upgrades = explode("\n", $config->get('member_upgrades')); // One upgrades per line.
    $upgradeOptions = [];
    $upgradeVals = [];
    foreach ($upgrades as $upgrade) {
      if (!empty($upgrade)) {
        list($upid, $fromtype, $fromdays, $totype, $todays, $tobadge, $desc, $price) = array_pad(explode('|', $upgrade), 8, '');
        // If list not present, add from type as first option.
        if (!isset($upgradeOptions[$fromtype][$fromdays])) {
          $upgradeOptions[$fromtype][$fromdays][0] = $types->types[$fromtype]->name;
        }
        // Store 
        $upgradeOptions[$fromtype][$fromdays][$upid] = $desc;
        $upgradeVals[$upid] = (object)[
          'fromType' => $fromtype,
          'fromDays' => $fromdays,
          'toType' => $totype,
          'toDays' => $todays,
          'toBadgeType' => $tobadge,
          'desc' => $desc,
          'price' => $price];
      }
    }
    
    // Stash member upgrades in static variable in case needed again.
    $member_upgrades[$eid] = (object)['options' => $upgradeOptions, 'upgrades' => $upgradeVals];
    return $member_upgrades[$eid];
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
   * Return list of badge name options from config.
   *
   * Parameters: Optional config.
   */
  public static function badgeNameOptions($eid, &$config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $options = explode("\n", $config->get('badge_name_options')); // One type per line.
    $badgeNameOptions = [];
    foreach ($options as $option) {
      list($code, $badgeOption) = explode('|', $option);
      $badgeNameOptions[trim($code)] = trim($badgeOption);
    }
    return $badgeNameOptions;
  }

  /**
   * If member name has been entered, customise the badge name options.
   */
  public static function badgeNameOptionsForName($eid, $firstName, $lastName, $maxLength, &$config = NULL) {
    $badgeNameOptions = self::badgeNameOptions($eid, $config);
    if (!(empty($firstName) && empty($lastName))) {
      if (array_key_exists('F', $badgeNameOptions))
        $badgeNameOptions['F'] = substr($firstName, 0, $maxLength);
      if (array_key_exists('N', $badgeNameOptions))
        $badgeNameOptions['N'] = substr($firstName . ' ' . $lastName, 0, $maxLength);
      if (array_key_exists('L', $badgeNameOptions))
        $badgeNameOptions['L'] = substr($lastName . ', ' . $firstName, 0, $maxLength);
    }
    return $badgeNameOptions;
  }

  /**
   * Return list of days from config.
   *
   * Parameters: Optional config.
   */
  public static function days($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $dayLines = explode("\n", $config->get('days')); // One type per line.
    $days = [];
    foreach ($dayLines as $dayLine) {
      list($dayCode, $dayName) = explode('|', $dayLine);
      $days[trim($dayCode)] = trim($dayName);
    }
    return $days;
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberAddons($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $addOns = explode("\n", $config->get('add_ons.options')); // One type per line.
    $addOnOptions = array();
    $addOnPrices = array();
    foreach ($addOns as $addOn) {
      if (!empty($addOn)) {
        list($desc, $price) = explode('|', $addOn);
        $addOnOptions[$desc] = $desc;
        $addOnPrices[$desc] = $price;
      }
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
  public static function display($eid = 1, $config = NULL) {
    // Get the config display options.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $options = explode("\n", $config->get('display_options.options'));
    $display_options = [];
    foreach ($options as $option) {
      list($code, $description) = array_pad(explode('|', trim($option)), 2, '');
      $display_options[$code] = $description;
    }
    return $display_options;
    /* return ['F' => t('Full name and badge name'),
            'B' => t('Badge name only'),
            'N' => t('Not at all')]; */
  }

  /**
   * Return list of communications methods (from ).
   */
  public static function communicationMethod($eid, $config = NULL, $publicOnly = TRUE) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $methods = explode("\n", $config->get('communications_method.options')); // One communications method per line.
    $methodOptions = array();
    foreach ($methods as $method) {
      list($code, $description, $public) = array_pad(explode('|', trim($method)), 3, '');
      if (!$publicOnly || $public == '1' || $public == '')
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
            'Groats' => t('Groats'),
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
