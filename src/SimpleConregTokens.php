<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregTokens
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregTokens {

  // Function to return help text containing allowed tokens.
  public static function tokenHelp() {
    $tokens = 
      ['[site_name]',
       '[event_name]',
       '[event_email]',
       '[first_name]',
       '[last_name]',
       '[full_name]',
       '[badge_name]',
       '[email]',
       '[street]',
       '[street2]',
       '[city]',
       '[county]',
       '[postcode]',
       '[country]',
       '[member_type]',
       '[communications_method]',
       '[display]',
       '[member_price]',
       '[payment_id]',
       '[payment_url]',
       '[member_details]',
      ];
    return t("Available tokens: @tokens", ['@tokens' => implode(', ', $tokens)]);
  }

  public static function getTokens($eid, $mid) {
    $tokens = [];
    $event = SimpleConregEventStorage::load(['eid' => $eid]);
    $config = \Drupal::config('simple_conreg.settings.'.$eid);
    $symbol = $config->get('payments.symbol');
    // Get all members registered by subject member.
    $members = SimpleConregStorage::loadAll(['eid' => $eid, 'lead_mid' => $mid, 'is_deleted' => 0]);
    // If no records returned, member is not group leader, so get member details.
    if (count($members) == 0) {
      $members = SimpleConregStorage::loadAll(['eid' => $eid, 'mid' => $mid, 'is_deleted' => 0]);
    }

    // Labels for display option and communications method. Will add to config later.
    list($typeOptions, $typeNames) = SimpleConregOptions::memberTypes($eid, $config);
    $displayOptions = SimpleConregOptions::display();
    $communicationOptions = SimpleConregOptions::communicationMethod($eid, $config);
    $countryOptions = SimpleConregOptions::memberCountries($eid, $config);
    $yesNoOptions = SimpleConregOptions::yesNo();
    
    // Replace codes with values in member data.
    foreach ($members as $index => $val) {
      // If member number is zero, replace with blank.
      if ($members[$index]['member_no'] == 0)
        $members[$index]['member_no'] = '';
      // Expand list values and add currency symbol.
      $members[$index]['is_approved'] = $yesNoOptions[$val['is_approved']];
      $members[$index]['is_paid'] = $yesNoOptions[$val['is_paid']];
      $members[$index]['display'] = $displayOptions[$val['display']];
      $members[$index]['member_price'] = $symbol . $val['member_price'];
      $members[$index]['member_type'] = $typeNames[$val['member_type']];
      if (!empty($val['communication_method']))
        $members[$index]['communication_method'] = $communicationOptions[$val['communication_method']];
      $members[$index]['country'] = $countryOptions[$val['country']];
      $members[$index]['extra_flag1'] = $yesNoOptions[$val['extra_flag1']];
      $members[$index]['extra_flag2'] = $yesNoOptions[$val['extra_flag2']];
    }
    $member = $members[0];
    
    $tokens['[site_name]'] = \Drupal::config('system.site')->get('name');
    $tokens['[event_name]'] = $event['event_name'];
    $tokens['[event_email]'] = $config->get('confirmation.from_email');

    // If member is not group lead, we need to get payment URL and possibly email from leader.
    if ($member['mid'] != $member['lead_mid']) {
      $leader = SimpleConregStorage::load(['eid' => $eid, 'mid' => $member['lead_mid'], 'is_deleted' => 0]);
    } else {
      $leader = $member;
    }
    $tokens['[lead_key]'] = $leader['random_key'];
    $tokens['[lead_email]'] = $leader['email'];

    // Add tokens for all member fields.
    foreach ($member as $field => $value) {
      $tokens["[$field]"] = $value;
    }
    $tokens['[full_name]'] = trim($member['first_name'] . ' ' . $member['last_name']);

    $plain = $tokens;

    // Add payment URL to tokens.
    $payment_url = \Drupal\Core\Url::fromRoute('simple_conreg_payment',
      array('mid' => $member['lead_mid'], 'key' => $leader['random_key']),
      array('absolute' => TRUE)
    )->toString();
    $tokens["[payment_url]"] = '<a href="'.$payment_url.'">'.$payment_url.'</a>';
    $plain["[payment_url]"] = $payment_url;

    // List of fields to add to mail for each member.
    $confirm_labels = array(
      'first_name' => 'fields.first_name_label',
      'last_name' => 'fields.last_name_label',
      'badge_name' => 'fields.badge_name_label',
      'email' => 'fields.email_label',
      'display' => 'fields.display_label',
      'communications_method' => 'fields.communications_method_label',
      'street' => 'fields.street_label',
      'street2' => 'fields.street2_label',
      'city' => 'fields.city_label',
      'county' => 'fields.county_label',
      'postcode' => 'fields.postcode_label',
      'country' => 'fields.country_label',
      'phone' => 'fields.phone_label',
      'birth_date' => 'fields.birth_date_label',
      'member_type' => 'fields.member_type_label',
      'add_on' => 'add_ons.label',
      'add_on_info' => 'add_on_info.label',
      'extra_flag1' => 'extras.flag1',
      'extra_flag2' => 'extras.flag2',
    );
    $dispay = "";
    $plain_display = "";
    $member_no = 0;
    foreach ($members as $index => $cur_member) {
      // Look up labels for fields to email.
      $display .= '<table>';
      $member_no ++;
      $member_heading = t('Member @number', ['@number' => $member_no]);
      $display .= '<th colspan="2">'.$member_heading.'</th>';
      $plain_display .= "\n$member_heading\n";
      foreach ($confirm_labels as $key=>$val) {
        if (!empty($config->get($val))) {
          $label = $config->get($val);
          $display .= '<tr><td>'.$label.'</td><td>'.$cur_member[$key].'</td></tr>';
          $plain_display .= $label.":\t".$cur_member[$key]."\n";
        }
      }
      // Add price with static label.
      $label = t('Price for member');
      $display .= '<tr><td>'.$label.'</td><td>'.$cur_member['member_price'].'</td></tr>';
      $plain_display .= $label.":\t".$cur_member['member_price']."\n";
      $display .= "</table>";
    }
    $tokens['[member_details]'] = $display;
    $plain['[member_details]'] = $plain_display;
    return ['html' => $tokens, 'plain' => $plain, 'vals' => $member];
  }

  public static function applyTokens($message, $tokens, $use_plain = FALSE) {
    // Select which set of tokens to use.
    if ($use_plain)
      $apply = $tokens['plain'];
    else
      $apply = $tokens['html'];
    // Split tokens into two arrays for find and replace.
    $find = [];
    $replace = [];
    foreach($apply as $key => $val) {
      $find[] = $key;
      $replace[] = $val;
    }
    return str_replace($find, $replace, $message);
  }

  public static function previewTokens($message, $tokens, $use_plain = FALSE) {
    return SimpleConregTokens::applyTokens($message, $tokens, $use_false);
  }

}