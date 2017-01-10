<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregEmailer
 */

use Drupal\devel;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Render\PlainTextOutput;

namespace Drupal\simple_conreg;

class SimpleConregEmailer {

  // Function to create an email for specified member.
  public static function createEmail(&$message, $params) {
    // Only proceed if Event and Member provided.
    if (NULL != $eid = $params['eid'] && NULL != $mid = $params['mid']) {
dpm($mid);
      if (!isset($tokens))
        $tokens = SimpleConregTokens::getTokens($eid, $mid);
      // If address is empty, use the lead member address, and add a note to the start of the body.
      if (empty($params['to'] = $tokens['html']['[email]'])) {
        $params['to'] = $tokens['html']['[lead_email]'];
        $params['body'] = t('Note: we are you writing to you as contact for [full_name].') . "\n\n" . $params['body'];
      }
dpm($params['subject']);
      // Set member values in params.
      $params = array_merge($params, $tokens['vals']);
      // Add tokens to params for later reuse.
      $params['tokens'] = $tokens;
      // Store params in message to return.
      $message['params'] = $params;
      $message['subject'] = SimpleConregTokens::applyTokens($params['subject'], $tokens);
      $body = SimpleConregTokens::applyTokens($params['body'], $tokens, FALSE);
      $message['preview'] = str_replace("\n", "<br />", $body);

      $config = \Drupal::config('simple_conreg.settings.'.$eid);
      if ($config->get('confirmation.format_html')) {
        // Set up HTML email (todo: add plain text option).
        $message['headers']['Content-Type'] = 'text/html; charset=UTF-8';
        $message['body'][] = htmlspecialchars_decode($body, ENT_QUOTES);  // HTML version of message body.
        $message['plain'] = \Drupal\Component\Utility\SafeMarkup::checkPlain(SimpleConregTokens::applyTokens($params['body'], $tokens, TRUE));  // Plain text version of body.
      } else {
        $message['body'][] = \Drupal\Component\Render\PlainTextOutput::renderFromHtml("There's no telling what will happen with \"this\" or this & this>.");
        $message['body'][] = htmlspecialchars_decode(SimpleConregTokens::applyTokens($params['body'], $tokens, TRUE), ENT_QUOTES);  // Plain text version of body.
      }
      if (!empty($params['from'])) {
        $message['from'] = $params['from'];
        $message['headers']['From'] = $params['from'];
      }
    }
  }

}
