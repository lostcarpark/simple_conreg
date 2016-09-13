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
  public static function memberTypes(&$config = NULL) {
    if (is_null($config)) {
      $config = \Drupal::config('simple_conreg.settings');
    }
    $types = explode("\n", $config->get('member_types')); // One type per line.
    $typeOptions = [];
    $typeNames = [];
    $typePrices = [];
    $defaultBadgeTypes = [];
    foreach ($types as $type) {
      list($code, $desc, $name, $price, $badgetype) = explode('|', $type);
      $typeOptions[$code] = $desc;
      $typeNames[$code] = $name;
      $typePrices[$code] = $price;
      $defaultBadgeTypes[$code] = $badgetype;
    }
    return array($typeOptions, $typeNames, $typePrices, $defaultBadgeTypes);
  }

  /**
   * Return list of badge types from config.
   *
   * Parameters: Optional config.
   */
  public static function badgeTypes(&$config = NULL) {
    if (is_null($config)) {
      $config = \Drupal::config('simple_conreg.settings');
    }
    $types = explode("\n", $config->get('badge_types')); // One type per line.
    $badgeTypes = [];
    foreach ($types as $type) {
      list($code, $badgetype) = explode('|', $type);
      $badgeTypes[$code] = $badgetype;
    }
    return $badgeTypes;
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberAddons(&$config = NULL) {
    if (is_null($config)) {
      $config = \Drupal::config('simple_conreg.settings');
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
  public static function memberCountries(&$config = NULL) {
    if (is_null($config)) {
      $config = \Drupal::config('simple_conreg.settings');
    }
    $countries = explode("\n", $config->get('reference.countries')); // One country per line.
    $countryOptions = array();
    foreach ($countries as $country) {
      list($code, $name) = explode('|', $country);
      $countryOptions[$code] = $name;
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
  public static function communicationMethod($config = NULL) {
    if (is_null($config)) {
      $config = \Drupal::config('simple_conreg.settings');
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

}
