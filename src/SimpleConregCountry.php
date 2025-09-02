<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregCountry.
 */

namespace Drupal\simple_conreg;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Utility\Error;
use GuzzleHttp\Exception\RequestException;

/**
 * Country functions for Simple Convention Registration.
 */
class SimpleConregCountry {

  /**
   * Cet country of user client.
   */
  public static function getUserCountry() {

    // URL for IP country service.
    $url = 'http://ip-api.com/json/';

    try {
      // Get IP address from request.
      $ip = \Drupal::request()->getClientIp();
      $client = \Drupal::httpClient();
      $response = $client->get($url.$ip)->getBody()->getContents();
      $decoded = Json::decode($response);
      if (!isset($decoded['status']) || $decoded['status'] != 'success') {
        return '';
      }
    }
    catch (RequestException $e) {
      return '';
    }

    return $decoded['countryCode'];
  }

}
