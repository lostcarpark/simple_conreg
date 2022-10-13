<?php

/**
 * @file
 * Contains \Drupal\conreg_discord\Discord.
 */

namespace Drupal\conreg_discord;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregStorage;
use Drupal\simple_conreg\FieldOptions;
use Drupal\devel;
use GuzzleHttp\Exception\RequestException;


class Discord
{
  const BASE_URL = 'https://discordapp.com/api/';
  const INVITE_URL = 'https://discordapp.com/invite/';
  public $token;
  public $channelId;
  public $channel;
  public $inviteCode;
  public $message;
  
  /**
   * Constructs a new Member object.
   */
  public function __construct($token, $channelId)
  {
    $this->token = $token;
    $this->channelId = $channelId;
  }
  
  /**
   * Gets the channel object.
   */
  public function getChannel()
  {
    $client = \Drupal::httpClient();
    try {
      $response = $client->get(self::BASE_URL . '/channels/' . $this->channelId, [
        'headers' => [
          'Content-type' => 'application/json',
          'Authorization' => 'Bot ' . $this->token,
        ],
      ])->getBody()->getContents();
      $this->channel = (object)Json::decode($response);
      $this->message = '';
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
      $response_info = Json::decode($response->getBody()->getContents());
      $this->message = t('Failed to get channel information with error: @error (@code).', ['@error' => $response_info['channel_id'][0], '@code' => $response->getStatusCode()]);
      watchdog_exception('Remote API Connection', $e, $this->message);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets an invite to the channel.
   */
  public function getChannelInvite()
  {
    $client = \Drupal::httpClient();
    $json = [
        'max_age' => 0,
        'max_uses' => 1,
        'unique' => true,
    ];
    try {
      $response = $client->post(self::BASE_URL . '/channels/' . $this->channelId . '/invites', [
        'headers' => [
          'Content-type' => 'application/json',
          'Authorization' => 'Bot ' . $this->token,
        ],
        'body' => json_encode($json, JSON_FORCE_OBJECT),
      ])->getBody()->getContents();
      $decoded = (object)Json::decode($response);
      $this->inviteCode = $decoded->code;
      $this->message = '';
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
      $response_info = Json::decode($response->getBody()->getContents());
      $this->message = t('Failed to create invite code with error: @error (@code).', ['@error' => $response_info['channel_id'][0], '@code' => $response->getStatusCode()]);
      watchdog_exception('Remote API Connection', $e, $this->message);
      return FALSE;
    }

    return TRUE;
  }

}
