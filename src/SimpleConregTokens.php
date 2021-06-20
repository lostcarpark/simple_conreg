<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregTokens
 */

namespace Drupal\simple_conreg;

use Drupal\devel;


class SimpleConregTokens {

  var $eid;
  var $mid;
  var $html;
  var $plain;
  var $event;
  var $config;
  var $symbol;
  var $typevals;
  var $display;
  var $plain_display;


  public function __construct($eid = 1, $mid = null)
  {
    $this->eid = $eid;
    $this->mid = $mid;

    $this->html = [];
    $this->event = SimpleConregEventStorage::load(['eid' => $eid]);
    $this->config = SimpleConregConfig::getConfig($eid);
    $this->symbol = $this->config->get('payments.symbol');

    $types = SimpleConregOptions::memberTypes($this->eid, $this->config);
    $this->typeVals = $types->types;
	
    $this->html['[site_name]'] = \Drupal::config('system.site')->get('name');
    $this->html['[event_name]'] = $this->event['event_name'];
    $this->html['[event_email]'] = $this->config->get('confirmation.from_email');

    // Only fetch member details if mid set.
    if (isset($mid)) {
      if (is_array($mid)) {
        $extra_mids = array_slice($mid, 1); // Store any extra member IDs for later.
        $mid = $mid[0];
      }
      // Get all members registered by subject member.
      $members = SimpleConregStorage::loadAll(['eid' => $eid, 'lead_mid' => $mid, 'is_deleted' => 0]);
      // If no records returned, member is not group leader, so get member details.
      if (count($members) == 0) {
        $members = SimpleConregStorage::loadAll(['eid' => $eid, 'mid' => $mid, 'is_deleted' => 0]);
      }

      // Replace codes with values in member data.
      $this->replaceMemberCodes($members);
      $this->vals = $members[0];

      // Check if random_key set. If not set, generate it.
      if (empty($this->vals['random_key'])) {
        $rand_key = mt_rand();
        SimpleConregStorage::update(['mid'=>$this->vals['mid'], 'random_key'=>$rand_key]);
        $this->vals['random_key'] = $rand_key;
      }
      
      // Get login expiry time.
      $expiryDate = self::updateLoginExpiryDate($mid);
      $this->html['[login_expiry]'] = $expiryDate;
      $this->html['[login_expiry_medium]'] = format_date($expiryDate, 'medium');
      $login_url = \Drupal\Core\Url::fromRoute('simple_conreg_login',
        ['mid' => $this->vals['mid'], 'key' => $this->vals['random_key'], 'expiry' => $expiryDate],
        ['absolute' => TRUE]
      )->toString();
      $this->html['[login_url]'] = $login_url;

      // If member is not group lead, we need to get payment URL and possibly email from leader.
      if ($this->vals['mid'] != $this->vals['lead_mid']) {
        $leader = SimpleConregStorage::load(['eid' => $eid, 'mid' => $member['lead_mid'], 'is_deleted' => 0]);
      } else {
        $leader = $this->vals;
      }
      $this->html['[lead_key]'] = $leader['random_key'];
      $this->html['[lead_email]'] = $leader['email'];

      // Add tokens for all member fields.
      foreach ($this->vals as $field => $value) {
        $this->html["[$field]"] = $value;
      }
      $this->html['[full_name]'] = trim($this->vals['first_name'] . ' ' . $this->vals['last_name']);

      // Copy all tokens into plain version. Later tokens may contain HTML.
      $this->plain = $this->html;

      // Format Login URL as a link for HTML.
      $this->html["[login_url]"] = '<a href="'.$login_url.'">'.$login_url.'</a>';

      // Add payment URL to tokens.
      if (!empty($leader['random_key'])) {
        $payment_url = \Drupal\Core\Url::fromRoute('simple_conreg_payment',
          array('mid' => $leader['lead_mid'], 'key' => $leader['random_key'], 'name' => $this->html['[full_name]'], 'postcode' => $leader['postcode']),
          array('absolute' => TRUE)
        )->toString();
        $this->html["[payment_url]"] = '<a href="'.$payment_url.'">'.$payment_url.'</a>';
        $plain["[payment_url]"] = $payment_url;
      } else {
        $tokens["[payment_url]"] = '';
        $plain["[payment_url]"] = '';
      }

      $this->display = '';
      $this->plain_display = '';
      $member_seq = 0;
      $this->getMemberDetailsToken($members, $member_seq);

      // Loop through any additional members registered and add them to the member details.
      if (isset($extra_mids)) {
        foreach($extra_mids as $mid) {
          $members = SimpleConregStorage::loadAll(['eid' => $eid, 'lead_mid' => $mid, 'is_deleted' => 0]);
          // If no records returned, member is not group leader, so get member details.
          if (count($members) == 0) {
            $members = SimpleConregStorage::loadAll(['eid' => $eid, 'mid' => $mid, 'is_deleted' => 0]);
          }
          
          // Replace codes with values in member data.
          $this->replaceMemberCodes($members);
          // Add member 
          $this->getMemberDetailsToken($members, $member_seq);
        }
      }

      $this->html['[member_details]'] = $this->display;
      $this->plain['[member_details]'] = $this->plain_display;
    }
  }

  /***
   * Update the date/time when the login link will expire.
   * ToDo: Currently set to one week in future. Make duration configurable.
   */
  private function updateLoginExpiryDate($mid)
  {
    // Get current time.
    $timeNow = \Drupal::time()->getRequestTime();

    // First check previous expiry time.
    $result = SimpleConregStorage::load(['mid'=>$mid]);
    $expiryTime = $result['login_exp_date'];
    // Check if previous expiry date is more than 24 hours in the future.
    if ($expiryTime > $timeNow + 86400) { // 24*3600.
      // Plenty of time left on previous key, just return it.
      return $expiryTime;
    }
    
    // Set expiry time to a week in the future.
    // Add a random number of seconds so logins generated in bulk won't have the same expiry.
    $expiryTime = $timeNow + 604800 + rand(0, 3600); //7*24*3600 - seconds in a week.
    SimpleConregStorage::update(['mid'=>$mid, 'login_exp_date'=>$expiryTime]);
    return $expiryTime;
  }

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
       '[payment_amount]',
       '[payment_id]',
       '[payment_url]',
       '[member_details]',
       '[login_url]',
       '[login_expiry]',
       '[login_expiry_medium]',
      ];
    return t("Available tokens: @tokens", ['@tokens' => implode(', ', $tokens)]);
  }



  public function replaceMemberCodes(&$members) {
    // Labels for display option and communications method. Will add to config later.
    $types = SimpleConregOptions::memberTypes($this->eid, $this->config);
    $days = SimpleConregOptions::days($this->eid, $this->config);
    $this->typeVals = $types->types;
    $displayOptions = SimpleConregOptions::display();
    $communicationOptions = SimpleConregOptions::communicationMethod($this->eid, $this->config);
    $countryOptions = SimpleConregOptions::memberCountries($this->eid, $this->config);
    $yesNoOptions = SimpleConregOptions::yesNo();
    $digits = $this->config->get('member_no_digits');

    // Loop once to get the correct payment total.
    $payAmount = 0;
    foreach ($members as $index => $val) {
      // Get add ons and add up price.
      $members[$index]['addons'] = SimpleConregAddons::getMemberAddons($this->config, $val['mid']);
      $members[$index]['add_on_price'] = 0;
      foreach ($members[$index]['addons'] as $addon) {
        $members[$index]['add_on_price'] += $addon->value;
      }
      $members[$index]['member_total'] = $val['member_price'] + $members[$index]['add_on_price'];
      $payAmount += $members[$index]['member_total'];
    }

    // Loop through members and set payment total.
    foreach ($members as $index => $val) {
      // If member number is zero, replace with blank.
      if ($members[$index]['member_no'] == 0)
        $members[$index]['member_no'] = '';
      else
        $members[$index]['member_no'] = $members[$index]['badge_type'] . sprintf("%0".$digits."d", $members[$index]['member_no']);
      // Expand list values and add currency symbol.
      if (!empty($members[$index]['days'])) {
        $dayDescs = [];
        foreach(explode('|', $members[$index]['days']) as $day) {
          $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
        }
        $members[$index]['days'] = implode(', ', $dayDescs);
      }
      $members[$index]['is_approved'] = $yesNoOptions[$val['is_approved']];
      $members[$index]['is_paid'] = $yesNoOptions[$val['is_paid']];
      $members[$index]['display'] = $displayOptions[$val['display']];
      $members[$index]['member_price'] = $this->symbol . $val['member_price'];
      $members[$index]['member_total'] = $this->symbol . $val['member_total'];
      $members[$index]['payment_amount'] = $this->symbol . $payAmount;
      if (!empty($val['add_on_price']))
        $members[$index]['add_on_price'] = $this->symbol . $val['add_on_price'];
      $members[$index]['raw_member_type'] = $members[$index]['member_type'];
      $members[$index]['member_type'] = (isset($this->typeVals[$val['member_type']]) ? $this->typeVals[$val['member_type']]->name : $val['member_type']);
      if (!empty($val['communication_method']))
        $members[$index]['communication_method'] = $communicationOptions[$val['communication_method']];
      $members[$index]['country'] = $countryOptions[$val['country']];
      $members[$index]['extra_flag1'] = $yesNoOptions[$val['extra_flag1']];
      $members[$index]['extra_flag2'] = $yesNoOptions[$val['extra_flag2']];
    }
  }

  public function getMemberDetailsToken($members, &$member_seq) {
    // If types not set, fetch them.
    if (!isset($this->typeVals)) {
      $types = SimpleConregOptions::memberTypes($this->eid, $this->config);
      $this->typeVals = $types->types;
    }
    // List of fields to add to mail for each member.
    $confirm_labels = array(
      'member_type' => 'fields.membership_type_label',
      'days' => 'fields.membership_days_label',
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
      'age' => 'fields.age_label',
      'extra_flag1' => 'extras.flag1',
      'extra_flag2' => 'extras.flag2',
    );

    $reg_date = t('Registered on @date', ['@date' => format_date($members[0]['join_date'])]);
    $this->display .= '<h3>' . $reg_date . '</h3>';
    $this->plain_display .= "\n$reg_date\n";
    $this->display .= '<table>';

    $fieldOptions = FieldOptions::getFieldOptions($this->eid);

    foreach ($members as $index => $cur_member) {
      // Get fieldset config for member type.
      $memberType = $cur_member['raw_member_type'];
      $fieldsetConfig = $this->typeVals[$memberType]->config;
      // Get member options from database.
      $memberOptions = $fieldOptions->getMemberOptions($cur_member['mid']);
      // Look up labels for fields to email.
      $member_seq ++;
      $member_heading = t('Member @seq', ['@seq' => $member_seq]);
      $this->display .= '<tr><th colspan="2">'.$member_heading.'</th></tr>';
      $this->plain_display .= "\n$member_heading\n";
      if (!empty($cur_member['member_no'])) {
        $label = t('Member Number');
        $this->display .= '<tr><td>'.$label.'</td><td>'.$cur_member['member_no'].'</td></tr>';
        $this->plain_display .= $label.":\t".$member_no."\n";
      }
      foreach ($confirm_labels as $key=>$val) {
        if (!empty($fieldsetConfig) && !empty($fieldsetConfig->get($val))) {
          // Override name for badge name field, as we don't want it to say "Custom badge name".
          if ($key == 'badge_name')
            $label = t('Name on badge');
          else
            $label = $fieldsetConfig->get($val);
          $this->display .= '<tr><td>'.$label.'</td><td>'.$cur_member[$key].'</td></tr>';
          $this->plain_display .= $label.":\t".$cur_member[$key]."\n";

          if (isset($memberOptions[$key])) {
            $this->display .= '<tr><td colspan="2">'.$memberOptions[$key]['title'].'</td></tr>';
            $this->plain_display .= $memberOptions[$key]['title']."\n";
            foreach ($memberOptions[$key]['options'] as $option) {
              $this->display .= '<tr><td>'.$option['option_title'].'</td><td>'.t('Yes').'</td></tr>';
              $this->plain_display .= $option['option_title'].":\t".t('Yes')."\n";
              if (isset($option['option_detail'])) {
                $this->display .= '<tr><td>'.$option['detail_title'].'</td><td>'.$option['option_detail'].'</td></tr>';
                $this->plain_display .= $option['detail_title'].":\t".$option['option_detail']."\n";
              }            
            }
            unset($memberOptions[$key]);
          }
        }
      }

      // If any extra member options, add them to end of display...
      foreach ($memberOptions as $memberOption) {
        $this->display .= '<tr><td colspan="2">'.$memberOption['title'].'</td></tr>';
        $this->plain_display .= $memberOption['title']."\n";
        foreach ($memberOption['options'] as $option) {
          $this->display .= '<tr><td>'.$option['option_title'].'</td><td>'.t('Yes').'</td></tr>';
          $this->plain_display .= $option['option_title'].":\t".t('Yes')."\n";
          if (isset($option['option_detail'])) {
            $this->display .= '<tr><td>'.$option['detail_title'].'</td><td>'.$option['option_detail'].'</td></tr>';
            $this->plain_display .= $option['detail_title'].":\t".$option['option_detail']."\n";
          }            
        }
      }      

      // Add price with static label.
      $label = t('Price for member');
      $this->display .= '<tr><td>'.$label.'</td><td>'.$cur_member['member_price'].'</td></tr>';
      $this->plain_display .= $label.":\t".$cur_member['member_price']."\n";

      // If any member add-ons, add them.
      foreach ($cur_member['addons'] as $addon) {
        if (!empty($addon->label) && !empty($addon->option)) {
          $addOnName = $addon->label;
          $this->display .= '<tr><td>'.t("Add-on: @addon", ['@addon' => $addOnName]).'</td><td>'.$addon->option.'</td></tr>';
          $this->plain_display .= t("Add-on: @addon", ['@addon' => $addOnName]).":\t".$addon->option."\n";
        }
        if (!empty($addon->info_label) && !empty($addon->info)) {
          $this->display .= '<tr><td>'.$addon->info_label.'</td><td>'.$addon->info.'</td></tr>';
          $this->plain_display .= $addon->info_label.":\t".$addon->info."\n";
        }
        if (!empty($addon->free_label) && !empty($addon->amount)) {
          $addOnName = $addon->free_label;
          $this->display .= '<tr><td>'.t("Add-on: @addon", ['@addon' => $addOnName]).'</td><td>'.$addon->amount.'</td></tr>';
          $this->plain_display .= t("Add-on: @addon", ['@addon' => $addOnName]).":\t".$addon->amount."\n";
        }
        if (!empty($addon->amount)) {
          $this->display .= '<tr><td>'.t("@addon price", ['@addon' => $addOnName]).'</td><td>'.$addon->amount.'</td></tr>';
          $this->plain_display .= t("@addon price", ['@addon' => $addOnName]).":\t".$addon->amount."\n";
        }
      }
      if (!empty($cur_member['add_on_price'])) {
        $label = t('Add-on Total for member');
        $this->display .= '<tr><td>'.$label.'</td><td>'.$cur_member['add_on_price'].'</td></tr>';
        $this->plain_display .= $label.":\t".$cur_member['add_on_price']."\n";
      }
      $label = t('Member Total');
      $this->display .= '<tr><td>'.$label.'</td><td>'.$cur_member['member_total'].'</td></tr>';
      $this->plain_display .= $label.":\t".$cur_member['member_total']."\n";
      $payment_amount = $cur_member['payment_amount'];
    }
    $label = t('Total');
    $this->display .= '<tr><th colspan="2">'.$label.'</th></tr>';
    $this->plain_display .= "\n$label\n";
    $label = t('Total amount paid');
    $this->display .= '<tr><td>'.$label.'</td><td>'.$payment_amount.'</td></tr>';
    $this->plain_display .= $label.":\t".$payment_amount."\n";
    $this->display .= '</table>';
  }

  public function applyTokens($message, $use_plain = FALSE) {
    // Select which set of tokens to use.
    if ($use_plain)
      $apply = $this->plain;
    else
      $apply = $this->html;
    if (is_array($apply)) {
      // Split tokens into two arrays for find and replace.
      $find = [];
      $replace = [];
      foreach($apply as $key => $val) {
        $find[] = $key;
        $replace[] = $val;
      }
      return str_replace($find, $replace, $message);
      }
    else
      return $message;
  }

  public function previewTokens($message, $use_plain = FALSE) {
    return $this->applyTokens($message, $use_plain);
  }

}
