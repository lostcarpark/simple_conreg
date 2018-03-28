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
    $typeOptions = [];
    foreach ($types as $type) {
      if (!empty($type)) {
        list($code, $desc, $name, $price, $badgetype, $fieldset) = explode('|', $type);
        $code = trim($code);
        $fieldset = trim($fieldset);
        if (empty($fieldset)) {
          $fieldset = 0;
        }
        // Put description in specific array for populating drop-down.
        $typeOptions[$code] = trim($desc);
        // Put all other values in an associative array.
        $typeVals[$code] = [
          'name' => trim($name),
          'description' => trim($desc),
          'price' => trim($price),
          'badgetype' => trim($badgetype),
          'fieldset' => trim($fieldset),
          'config' => SimpleConregConfig::getFieldsetConfig($eid, $fieldset),
        ];
      }
    }
    return [$typeOptions, $typeVals];
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
      list($code, $badgetype) = explode('|', $type);
      $badgeTypes[trim($code)] = trim($badgetype);
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
