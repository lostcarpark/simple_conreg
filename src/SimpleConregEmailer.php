<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregEmailer
 */

namespace Drupal\simple_conreg;

use Drupal\devel;
use Drupal\Core\File\FileSystemInterface;

use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\StreamWrapper\PublicStream;

class SimpleConregEmailer {

  // Function to create an email for specified member.
  public static function createEmail(&$message, $params)
  {
    // Only proceed if Event provided.
    if (isset($params['eid'])) {
      if (isset($params['tokens']))
        $tokens = $params['tokens'];
      else
        $tokens = new SimpleConregTokens($params['eid'], $params['mid']);
      // If address is empty, use the lead member address, and add a note to the start of the body.
      if (empty($params['to'])) {
        if (isset($tokens->html['[email]']))
          $params['to'] = $tokens->html['[email]'];
        else {
          $params['to'] = $tokens->html['[lead_email]'];
          $params['body'] = t('Note: we are you writing to you as contact for [full_name].') . "\n\n" . $params['body'];
        }
      }
      // Set member values in params.
      if (is_array($tokens->vals))
        foreach($tokens->vals as $key=>$val) {
          $params[$key] = $val;
        }
      // Add tokens to params for later reuse.
      $params['tokens'] = $tokens;
      // Store params in message to return.
      $message['params'] = $params;
      $message['subject'] = $tokens->applyTokens($params['subject']);
      $body = $tokens->applyTokens($params['body'], FALSE);
      $message['preview'] = $body;

      // Only attach badge image if referenced in body.
      if (strpos($body, '[badge]') !== FALSE) {
        // Set ID for attachment.
        $badge_id = "conreg-badge".$params['mid'];
        $badge = '<img src="cid:' . $badge_id . '" />';

        // Prepare image attachment.
        $badgepath = PublicStream::basePath().'/badges/'.$params['eid'];
        $badgefile = 'mid'.$params['mid'].'.png';
        // Create attachment object.
        $file = new \stdClass();
        $file->cid = $badge_id;
        $file->uri = $badgepath.'/'.$badgefile; // File path
        $file->filename = $badgefile; //File name
        $file->filemime = 'image/png'; //File mime type
        // Add object to images array.
        $message['params']['images'][] = $file;
      }

      $config = \Drupal::config('simple_conreg.settings.'.$params['eid']);
      if ($config->get('confirmation.format_html')) {
        // Set up HTML email (todo: add plain text option).
        $message['headers']['Content-Type'] = 'text/html; charset=UTF-8';
        // Split body into an array.
        if (!empty($badge)) {
          $body = str_replace('[badge]', $badge, $body);
        }
        $body = explode('\n', $body);
        $message['body'] = array_map(function ($body) {
          return Markup::create($body);
        }, $body);
        
        //$message['body'][] = \Drupal\Core\Render\Markup::create($body);
        //$message['body'][] = check_markup($body, $format);  // HTML version of message body.
        $message['plain'] = \Drupal\Component\Utility\SafeMarkup::checkPlain($tokens->applyTokens($params['body'], TRUE));  // Plain text version of body.
      } else {
        //$message['body'][] = \Drupal\Component\Render\PlainTextOutput::renderFromHtml("There's no telling what will happen with \"this\" or this & this>.");
        $message['body'][] = \Drupal\Component\Utility\SafeMarkup::checkPlain($tokens->applyTokens($params['body'], TRUE));  // Plain text version of body.
      }
      if (empty($params['from'])) {
        $from = $config->get('confirmation.from_name')." <".$config->get('confirmation.from_email').">";
        $message['from'] = $from;
        $message['headers']['From'] = $from;
      } else {
        $message['from'] = $params['from'];
        $message['headers']['From'] = $params['from'];
      }
    }
  }

}
