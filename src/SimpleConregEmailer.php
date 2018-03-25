<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregEmailer
 */

use Drupal\devel;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Render\PlainTextOutput;

namespace Drupal\simple_conreg;

class SimpleConregEmailer {

  // Function to create an email for specified member.
  public static function createEmail(&$message, $params) {
    // Only proceed if Event and Member provided.
    $eid = $params['eid'];
    $mid = $params['mid'];
    //dpm($params, "createEmail");
    if (isset($eid)) {
      if (!isset($tokens))
        $tokens = SimpleConregTokens::getTokens($eid, $mid);
      // If address is empty, use the lead member address, and add a note to the start of the body.
      if (empty($params['to'])) {
        if (isset($tokens['html']['[email]']))
          $params['to'] = $tokens['html']['[email]'];
        else {
          $params['to'] = $tokens['html']['[lead_email]'];
          $params['body'] = t('Note: we are you writing to you as contact for [full_name].') . "\n\n" . $params['body'];
        }
      }
      // Set member values in params.
      foreach($tokens['vals'] as $key=>$val) {
        $params[$key] = $val;
      }
      //$params = array_merge($params, $tokens['vals']);
      // Add tokens to params for later reuse.
      $params['tokens'] = $tokens;
      //dpm($params, "Token Params");
      // Store params in message to return.
      $message['params'] = $params;
      $message['subject'] = SimpleConregTokens::applyTokens($params['subject'], $tokens);
      $body = SimpleConregTokens::applyTokens($params['body'], $tokens, FALSE);
      $message['preview'] = $body;

      $config = \Drupal::config('simple_conreg.settings.'.$eid);
      if ($config->get('confirmation.format_html')) {
        // Set up HTML email (todo: add plain text option).
        $message['headers']['Content-Type'] = 'text/html; charset=UTF-8';
        $message['body'][] = \Drupal\Core\Render\Markup::create($body);
        //$message['body'][] = check_markup($body, $format);  // HTML version of message body.
        $message['plain'] = \Drupal\Component\Utility\SafeMarkup::checkPlain(SimpleConregTokens::applyTokens($params['body'], $tokens, TRUE));  // Plain text version of body.
      } else {
        $message['body'][] = \Drupal\Component\Render\PlainTextOutput::renderFromHtml("There's no telling what will happen with \"this\" or this & this>.");
        $message['body'][] = htmlspecialchars_decode(check_markup(SimpleConregTokens::applyTokens($params['body'], $tokens, TRUE), ENT_QUOTES, $format));  // Plain text version of body.
      }
      if (empty($params['from'])) {
        $from = $config->get('confirmation.from_name')." <".$config->get('confirmation.from_email').">";
        $message['from'] = $from;
        $message['headers']['From'] = $from;
      } else {
        $message['from'] = $params['from'];
        $message['headers']['From'] = $params['from'];
      }
      //dpm($message, "Message");
    }
  }

}
