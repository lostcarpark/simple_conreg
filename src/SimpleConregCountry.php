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

    // URL for geo-plugin service.
    $url = 'http://www.geoplugin.net/json.gp?ip=';

    try {
      // Get IP address from request.
      $ip = \Drupal::request()->getClientIp();
      $client = \Drupal::httpClient();
      $response = $client->get($url.$ip)->getBody()->getContents();
      $decoded = Json::decode($response);
      if (!isset($decoded['geoplugin_status']) || $decoded['geoplugin_status'] != 200) {
        return '';
      }
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
      $response_info = Json::decode($response->getBody()->getContents());
      $logger = \Drupal::logger('modulename');
      Error::logException($logger, $e, 'Failed to create ClickUp task with error: @error (@code).', ['@error' => $response_info['err'], '@code' => $response_info['ECODE']]);
      return '';
    }

    return $decoded['geoplugin_countryCode'];
  }

}
