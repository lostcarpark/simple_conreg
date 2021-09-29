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
    $response = $client->get(self::BASE_URL . '/channels/' . $this->channelId, [
      'headers' => [
        'Content-type' => 'application/json',
        'Authorization' => 'Bot ' . $this->token,
      ],
    ])->getBody()->getContents();
    $decoded = (object)Json::decode($response);

    return $decoded;
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
    $response = $client->post(self::BASE_URL . '/channels/' . $this->channelId . '/invites', [
      'headers' => [
        'Content-type' => 'application/json',
        'Authorization' => 'Bot ' . $this->token,
      ],
      'body' => json_encode($json, JSON_FORCE_OBJECT),
    ])->getBody()->getContents();
    $decoded = (object)Json::decode($response);
    
    return $decoded->code;
  }

}
