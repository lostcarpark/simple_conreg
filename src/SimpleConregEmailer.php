<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;
use Drupal\Core\StreamWrapper\PublicStream;

// Define message formats.
define('CONREG_FORMAT_PLAIN', 'text/plain');
define('CONREG_FORMAT_HTML', 'text/html');

/**
 * Class for sending emails.
 */
class SimpleConregEmailer {

  /**
   * Function to create an email for specified member.
   *
   * @param string $message
   *   The message to send.
   * @param array $params
   *   Array of message parameters.
   */
  public static function createEmail(&$message, array $params) {
    // Only proceed if Event provided.
    if (isset($params['eid'])) {
      if (isset($params['tokens'])) {
        $tokens = $params['tokens'];
      }
      else {
        $tokens = new SimpleConregTokens($params['eid'], $params['mid'] ?? NULL);
      }
      // If no address, use lead member address, and add a note.
      if (empty($params['to'])) {
        if (isset($tokens->html['[email]'])) {
          $params['to'] = $tokens->html['[email]'];
        }
        else {
          $params['to'] = $tokens->html['[lead_email]'];
          $params['body'] = t('<p>Note: we are you writing to you as contact for [full_name].</p>') . $params['body'];
        }
      }
      // Set the message type.
      $message['headers']['Content-Type'] = CONREG_FORMAT_HTML;
      // Set member values in params.
      if (is_array($tokens->vals)) {
        foreach ($tokens->vals as $key => $val) {
          $params[$key] = $val;
        }
      }
      // Add tokens to params for later reuse.
      $params['tokens'] = $tokens;
      // Store params in message to return.
      $message['params'] = $params;
      $message['subject'] = $tokens->applyTokens($params['subject']);
      $body = [$tokens->applyTokens(preg_replace("/[\n\r]+/", '', $params['body']), FALSE)];
      $message['preview'] = implode("\n", $body);

      // Only attach badge image if referenced in body.
      if (strpos($body[0], '[badge]') !== FALSE) {
        // Set ID for attachment.
        $badge_id = "conreg-badge" . $params['mid'];
        $badge = '<img src="cid:' . $badge_id . '" />';

        // Prepare image attachment.
        $badgepath = PublicStream::basePath() . '/badges/' . $params['eid'];
        $badgefile = 'mid' . $params['mid'] . '.png';
        // Create attachment object.
        $file = new \stdClass();
        $file->cid = $badge_id;
        // File path.
        $file->uri = $badgepath . '/' . $badgefile;
        // File name.
        $file->filename = $badgefile;
        // File mime type.
        $file->filemime = 'image/png';
        // Add object to images array.
        $message['params']['images'][] = $file;
      }

      $config = \Drupal::config('simple_conreg.settings.' . $params['eid']);
      if ($config->get('confirmation.format_html')) {
        // Split body into an array.
        if (!empty($badge)) {
          $body[0] = str_replace('[badge]', $badge, $body[0]);
        }
        $message['body'] = array_map(function ($body) {
          return Markup::create($body);
        }, $body);
      }
      else {
        // Plain text version of body.
        $message['body'][] = Html::escape($tokens->applyTokens($params['body'], TRUE));
      }
      if (empty($params['from'])) {
        $from = $config->get('confirmation.from_name') . " <" . $config->get('confirmation.from_email') . ">";
        $message['from'] = $from;
        $message['headers']['From'] = $from;
      }
      else {
        $message['from'] = $params['from'];
        $message['headers']['From'] = $params['from'];
      }
    }
  }

}
