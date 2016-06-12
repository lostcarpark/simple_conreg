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
    $typeOptions = array();
    $typePrices = array();
    foreach ($types as $type) {
      list($code, $desc, $price) = explode('|', $type);
      $typeOptions[$code] = $desc;
      $typePrices[$code] = $price;
    }
    return array($typeOptions, $typePrices);
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public function memberAddons(&$config = NULL) {
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
  public function memberCountries(&$config = MULL) {
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
  public function display() {
    return ['F' => t('Full name and badge name'),
            'B' => t('Badge name only'),
            'N' => t('Not at all')];
  }

  /**
   * Return list of communications methods (currently hard coded, will be editable later).
   */
  public function communicationMethod() {
    return ['E' => t('Electronic only'),
            'P' => t('Paper only'),
            'B' => t('Both electronic and paper')];
  }

  /**
   * Return list of payment methods.
   */
  public function paymentMethod() {
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
