<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Class for replacing tokens in email messages.
 */
class SimpleConregTokens {

  use StringTranslationTrait;

  /**
   * The event ID.
   *
   * @var int
   */
  protected int $eid;

  /**
   * The member ID.
   *
   * @var int
   */
  protected array|int|null $mid;

  /**
   * Array of HTML values.
   *
   * @var array
   */
  public array $html;

  /**
   * Array of plain text values.
   *
   * @var array
   */
  public array $plain;

  /**
   * Array of event values.
   *
   * @var array
   */
  protected array $event;

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The currency symbol.
   *
   * @var string
   */
  protected string $symbol;

  /**
   * Display values in HTML.
   *
   * @var string
   */
  protected string $display;

  /**
   * Display values in plain text.
   *
   * @var string
   */
  protected string $plainDisplay;

  /**
   * The types of replacable values.
   *
   * @var array
   */
  protected array $typeVals;

  /**
   * Array of replacable values.
   *
   * @var array
   */
  public array|null $vals = NULL;

  /**
   * Constructor for token class.
   *
   * @param int $eid
   *   The event ID.
   * @param int|null $mid
   *   The member ID.
   */
  public function __construct(int $eid = 1, array|int|null $mid = NULL) {
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
        // Store any extra member IDs for later.
        $extra_mids = array_slice($mid, 1);
        $mid = $mid[0];
      }
      $members = $this->loadMemberGroup($eid, $mid);

      // Replace codes with values in member data.
      $this->replaceMemberCodes($members);
      $this->vals = $members[0];

      // Check if random_key set. If not set, generate it.
      if (empty($this->vals['random_key'])) {
        $rand_key = mt_rand();
        SimpleConregStorage::update([
          'mid' => $this->vals['mid'],
          'random_key' => $rand_key,
        ]);
        $this->vals['random_key'] = $rand_key;
      }

      // Get login expiry time.
      $expiryDate = $this->updateLoginExpiryDate($mid);
      $this->html['[login_expiry]'] = $expiryDate;
      $this->html['[login_expiry_medium]'] = \Drupal::service('date.formatter')->format($expiryDate, 'medium');
      $login_url = Url::fromRoute('simple_conreg_login',
        [
          'mid' => $this->vals['mid'],
          'key' => $this->vals['random_key'],
          'expiry' => $expiryDate,
        ],
        ['absolute' => TRUE]
      )->toString();
      $this->html['[login_url]'] = $login_url;

      // If member is not group lead, we need to get payment URL and possibly
      // email from leader.
      if ($this->vals['mid'] != $this->vals['lead_mid']) {
        $leader = SimpleConregStorage::load([
          'eid' => $eid,
          'mid' => $this->vals['lead_mid'],
          'is_deleted' => 0,
        ]);
      }
      else {
        $leader = $this->vals;
      }
      $this->html['[lead_key]'] = $leader['random_key'] ?? '';
      $this->html['[lead_email]'] = $leader['email'] ?? '';

      // Add tokens for all member fields.
      foreach ($this->vals as $field => $value) {
        $this->html["[$field]"] = $value;
      }
      $this->html['[full_name]'] = trim($this->vals['first_name'] . ' ' . $this->vals['last_name']);

      // Copy all tokens into plain version. Later tokens may contain HTML.
      $this->plain = $this->html;

      // Format Login URL as a link for HTML.
      $this->html["[login_url]"] = '<a href="' . $login_url . '">' . $login_url . '</a>';

      // Add payment URL to tokens.
      if (!empty($leader['random_key'])) {
        $payment_url = Url::fromRoute('simple_conreg_payment',
          [
            'mid' => $leader['lead_mid'],
            'key' => $leader['random_key'],
            'name' => $this->html['[full_name]'],
            'postcode' => $leader['postcode'],
          ],
          ['absolute' => TRUE]
        )->toString();
        $this->html["[payment_url]"] = '<a href="' . $payment_url . '">' . $payment_url . '</a>';
        $plain["[payment_url]"] = $payment_url;
      }
      else {
        $tokens["[payment_url]"] = '';
        $plain["[payment_url]"] = '';
      }

      $this->display = '';
      $this->plainDisplay = '';
      $member_seq = 0;
      $this->getMemberDetailsToken($members, $member_seq);

      // Loop through any additional members registered.
      if (isset($extra_mids)) {
        foreach ($extra_mids as $mid) {
          $members = $this->loadMemberGroup($eid, $mid);

          // Replace codes with values in member data.
          $this->replaceMemberCodes($members);
          // Add member.
          $this->getMemberDetailsToken($members, $member_seq);
        }
      }

      $this->html['[member_details]'] = $this->display;
      $this->plain['[member_details]'] = $this->plainDisplay;
    }
  }

  /***
   * Update the date/time when the login link will expire.
   *
   * @param int $mid
   *   The member ID.
   *
   * @return int
   *   Unix timestamp of expiration time.
   *
   * @todo Currently set to one week in future. Make duration configurable.
   */
  private function updateLoginExpiryDate(int $mid): int {
    // Get current time.
    $timeNow = \Drupal::time()->getRequestTime();

    // First check previous expiry time.
    $result = SimpleConregStorage::load(['mid' => $mid]);
    $expiryTime = $result['login_exp_date'];
    // Check if previous expiry date is more than 24 hours in the future.
    // 24*3600.
    if ($expiryTime > $timeNow + 86400) {
      // Plenty of time left on previous key, just return it.
      return $expiryTime;
    }

    // Set expiry time to a week in the future.
    // Add a random number of seconds so logins generated in bulk won't
    // have the same expiry.
    // 7*24*3600 - seconds in a week.
    $expiryTime = $timeNow + 604800 + rand(0, 3600);
    SimpleConregStorage::update(['mid' => $mid, 'login_exp_date' => $expiryTime]);
    return $expiryTime;
  }

  /**
   * Function to return help text containing allowed tokens.
   *
   * @param array $extra
   *   Array of extra tokens.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The token help text.
   */
  public static function tokenHelp(array $extra = NULL): TranslatableMarkup {
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
    if (!is_null($extra) && is_array($extra)) {
      $tokens = array_merge($tokens, $extra);
    }
    return t("Available tokens: @tokens", ['@tokens' => implode(', ', $tokens)]);
  }

  /**
   * Assign token strings to each member.
   *
   * @param array $members
   *   Array of members to replace codes with literals.
   */
  public function replaceMemberCodes(array &$members): void {
    // Labels for display option and communications method.
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
      $addons = SimpleConregAddons::getMemberAddons($this->config, $val['mid']);
      $members[$index]['addons'] = $addons;
      $members[$index]['add_on_price'] = 0;
      foreach ($addons as $addon) {
        $members[$index]['add_on_price'] += $addon->value;
      }
      $members[$index]['member_total'] = $val['member_price'] + $members[$index]['add_on_price'];
      $payAmount += $members[$index]['member_total'];
    }

    // Loop through members and set payment total.
    foreach ($members as $index => $val) {
      // If member number is zero, replace with blank.
      if ($members[$index]['member_no'] == 0) {
        $members[$index]['member_no'] = '';
      }
      else {
        $members[$index]['member_no'] = $members[$index]['badge_type'] . sprintf("%0" . $digits . "d", $members[$index]['member_no']);
      }
      // Expand list values and add currency symbol.
      if (!empty($members[$index]['days'])) {
        $dayDescs = [];
        foreach (explode('|', $members[$index]['days']) as $day) {
          $dayDescs[] = $days[$day] ?? $day;
        }
        $members[$index]['days'] = implode(', ', $dayDescs);
      }
      $members[$index]['is_approved'] = $yesNoOptions[$val['is_approved']]->render();
      $members[$index]['is_paid'] = $yesNoOptions[$val['is_paid']]->render();
      $members[$index]['is_deleted'] = $yesNoOptions[$val['is_deleted']]->render();
      $members[$index]['display'] = $displayOptions[$val['display']];
      $members[$index]['member_price'] = $this->symbol . $val['member_price'];
      $members[$index]['member_total'] = $this->symbol . $val['member_total'];
      $members[$index]['payment_amount'] = $this->symbol . $payAmount;
      if (!empty($val['add_on_price'])) {
        $members[$index]['add_on_price'] = $this->symbol . $val['add_on_price'];
      }
      $members[$index]['raw_member_type'] = $members[$index]['member_type'];
      $members[$index]['member_type'] = (isset($this->typeVals[$val['member_type']]) ? $this->typeVals[$val['member_type']]->name : $val['member_type']);
      if (!empty($val['communication_method'])) {
        $members[$index]['communication_method'] = $communicationOptions[$val['communication_method']];
      }
      $members[$index]['country'] = $countryOptions[$val['country']];
      $members[$index]['extra_flag1'] = $yesNoOptions[$val['extra_flag1']]->render();
      $members[$index]['extra_flag2'] = $yesNoOptions[$val['extra_flag2']]->render();
    }
  }

  /**
   * Add the member details table to the tokens.
   *
   * @param array $members
   *   Array of members to add details to.
   * @param int $member_seq
   *   Current member number sequence.
   */
  public function getMemberDetailsToken(array $members, int &$member_seq): void {
    // If types not set, fetch them.
    if (!isset($this->typeVals)) {
      $types = SimpleConregOptions::memberTypes($this->eid, $this->config);
      $this->typeVals = $types->types;
    }
    $memberClasses = SimpleConregOptions::memberClasses($this->eid, $this->config);
    // List of fields to add to mail for each member.
    $confirms = [
      'member_type' => 'fields.membership_type',
      'days' => 'fields.membership_days',
      'first_name' => 'fields.first_name',
      'last_name' => 'fields.last_name',
      'badge_name' => 'fields.badge_name',
      'email' => 'fields.email',
      'display' => 'fields.display',
      'communications_method' => 'fields.communications_method',
      'street' => 'fields.street',
      'street2' => 'fields.street2',
      'city' => 'fields.city',
      'county' => 'fields.county',
      'postcode' => 'fields.postcode',
      'country' => 'fields.country',
      'phone' => 'fields.phone',
      'birth_date' => 'fields.birth_date',
      'age' => 'fields.age',
      'extra_flag1' => 'extras.flag1',
      'extra_flag2' => 'extras.flag2',
    ];

    $reg_date = $this->t('Registered on @date', ['@date' => \Drupal::service('date.formatter')->format($members[0]['join_date'])]);
    $this->display .= '<h3>' . $reg_date . '</h3>';
    $this->plainDisplay .= "\n$reg_date\n";
    $this->display .= '<table>';

    $fieldOptions = FieldOptions::getFieldOptions($this->eid);

    foreach ($members as $cur_member) {
      // Get member type.
      $memberType = $cur_member['raw_member_type'];
      // Get member class for selected member type.
      $curMemberClassRef = (!empty($memberType) && isset($types->types[$memberType])) ? $types->types[$memberType]->memberClass : array_key_first($memberClasses->classes);
      $curMemberClass = $memberClasses->classes[$curMemberClassRef];
      // Get member options from database.
      $memberOptions = $fieldOptions->getMemberOptions($cur_member['mid']);
      // Look up labels for fields to email.
      $member_seq++;
      $member_heading = $this->t('Member @seq', ['@seq' => $member_seq]);
      $this->display .= '<tr><th colspan="2">' . $member_heading . '</th></tr>';
      $this->plainDisplay .= "\n$member_heading\n";
      if (!empty($cur_member['member_no'])) {
        $label = $this->t('Member Number');
        $this->display .= '<tr><td>' . $label . '</td><td>' . $cur_member['member_no'] . '</td></tr>';
        $this->plainDisplay .= $label . ":\t" . $cur_member['member_no'] . "\n";
      }
      foreach ($confirms as $key => $val) {
        [$section, $entry] = explode('.', $val);
        if (!empty($curMemberClass->$section->$entry)) {
          // Override name for badge name field, as we don't want it to say
          // "Custom badge name".
          if ($key == 'badge_name') {
            $label = $this->t('Name on badge');
          }
          else {
            $label = $curMemberClass->$section->$entry;
          }
          $this->display .= '<tr><td>' . $label . '</td><td>' . $cur_member[$key] . '</td></tr>';
          $this->plainDisplay .= $label . ":\t" . $cur_member[$key] . "\n";

          if (isset($memberOptions[$key])) {
            $this->display .= '<tr><td colspan="2">' . $memberOptions[$key]['title'] . '</td></tr>';
            $this->plainDisplay .= $memberOptions[$key]['title'] . "\n";
            foreach ($memberOptions[$key]['options'] as $option) {
              $this->display .= '<tr><td>' . $option['option_title'] . '</td><td>' . $this->t('Yes') . '</td></tr>';
              $this->plainDisplay .= $option['option_title'] . ":\t" . $this->t('Yes') . "\n";
              if (isset($option['option_detail'])) {
                $this->display .= '<tr><td>' . $option['detail_title'] . '</td><td>' . $option['option_detail'] . '</td></tr>';
                $this->plainDisplay .= $option['detail_title'] . ":\t" . $option['option_detail'] . "\n";
              }
            }
            unset($memberOptions[$key]);
          }
        }
      }

      // If any extra member options, add them to end of display...
      foreach ($memberOptions as $memberOption) {
        $this->display .= '<tr><td colspan="2">' . $memberOption['title'] . '</td></tr>';
        $this->plainDisplay .= $memberOption['title'] . "\n";
        foreach ($memberOption['options'] as $option) {
          $this->display .= '<tr><td>' . $option['option_title'] . '</td><td>' . $this->t('Yes') . '</td></tr>';
          $this->plainDisplay .= $option['option_title'] . ":\t" . $this->t('Yes') . "\n";
          if (isset($option['option_detail'])) {
            $this->display .= '<tr><td>' . $option['detail_title'] . '</td><td>' . $option['option_detail'] . '</td></tr>';
            $this->plainDisplay .= $option['detail_title'] . ":\t" . $option['option_detail'] . "\n";
          }
        }
      }

      // Add price with static label.
      $label = $this->t('Price for member');
      $this->display .= '<tr><td>' . $label . '</td><td>' . $cur_member['member_price'] . '</td></tr>';
      $this->plainDisplay .= $label . ":\t" . $cur_member['member_price'] . "\n";

      // If any member add-ons, add them.
      foreach ($cur_member['addons'] as $addon) {
        if (!empty($addon->label) && !empty($addon->option)) {
          $addOnName = $addon->label;
          $this->display .= '<tr><td>' . $this->t("Add-on: @addon", ['@addon' => $addOnName]) . '</td><td>' . $addon->option . '</td></tr>';
          $this->plainDisplay .= $this->t("Add-on: @addon", ['@addon' => $addOnName]) . ":\t" . $addon->option . "\n";
        }
        if (!empty($addon->info) && !empty($addon->info)) {
          $this->display .= '<tr><td>' . $addon->info . '</td><td>' . $addon->info . '</td></tr>';
          $this->plainDisplay .= $addon->info . ":\t" . $addon->info . "\n";
        }
        if (!empty($addon->free) && !empty($addon->amount)) {
          $addOnName = $addon->free;
          $this->display .= '<tr><td>' . $this->t("Add-on: @addon", ['@addon' => $addOnName]) . '</td><td>' . $addon->amount . '</td></tr>';
          $this->plainDisplay .= $this->t("Add-on: @addon", ['@addon' => $addOnName]) . ":\t" . $addon->amount . "\n";
        }
        if (!empty($addon->amount)) {
          $this->display .= '<tr><td>' . $this->t("@addon price", ['@addon' => $addOnName]) . '</td><td>' . $addon->amount . '</td></tr>';
          $this->plainDisplay .= $this->t("@addon price", ['@addon' => $addOnName]) . ":\t" . $addon->amount . "\n";
        }
      }
      if (!empty($cur_member['add_on_price'])) {
        $label = $this->t('Add-on Total for member');
        $this->display .= '<tr><td>' . $label . '</td><td>' . $cur_member['add_on_price'] . '</td></tr>';
        $this->plainDisplay .= $label . ":\t" . $cur_member['add_on_price'] . "\n";
      }
      $label = $this->t('Member Total');
      $this->display .= '<tr><td>' . $label . '</td><td>' . $cur_member['member_total'] . '</td></tr>';
      $this->plainDisplay .= $label . ":\t" . $cur_member['member_total'] . "\n";
      $payment_amount = $cur_member['payment_amount'];
    }
    $label = $this->t('Total');
    $this->display .= '<tr><th colspan="2">' . $label . '</th></tr>';
    $this->plainDisplay .= "\n$label\n";
    $label = $this->t('Total amount paid');
    $this->display .= '<tr><td>' . $label . '</td><td>' . $payment_amount . '</td></tr>';
    $this->plainDisplay .= $label . ":\t" . $payment_amount . "\n";
    $this->display .= '</table>';
  }

  /***
   * Can be called if extra tokens are needed.
   *
   * @param array $extra
   *   Extra tokens to add.
   */
  public function addExtraTokens(array $extra): void {
    $this->plain = array_merge($this->plain, $extra);
    $this->html = array_merge($this->html, $extra);
  }

  /**
   * Apply the tokens to the message.
   *
   * @param string|array $message
   *   The message to apply tokens to.
   * @param bool $use_plain
   *   Whether the returned text should be plain text.
   *
   * @return mixed
   *   The message with tokens applied.
   */
  public function applyTokens(string|array $message, bool $use_plain = FALSE): mixed {
    // Select which set of tokens to use.
    if ($use_plain) {
      $apply = $this->plain;
    }
    else {
      $apply = $this->html;
    }
    if (is_array($apply)) {
      // Remove addons array before applying to message.
      unset($apply['addons']);
      return strtr($message, $apply);
    }
    else {
      return $message;
    }
  }

  /**
   * Preview the token replacement.
   *
   * @param string|array $message
   *   The message to apply tokens to.
   * @param bool $use_plain
   *   Whether the returned text should be plain text.
   *
   * @return mixed
   *   The message with tokens applied.
   */
  public function previewTokens(string|array $message, bool $use_plain = FALSE): mixed {
    return $this->applyTokens($message, $use_plain);
  }

  /**
   * Load members and any other members they registered.
   *
   * @param int $eid
   *   The event ID.
   * @param int $mid
   *   The member ID.
   *
   * @return array
   *   Array of members in group.
   */
  protected function loadMemberGroup(int $eid, int $mid): array {
    $members = SimpleConregStorage::loadAll([
      'eid' => $eid,
      'mid' => $mid,
      'is_deleted' => 0,
    ]);
    // Get all members registered by subject member.
    $groupMembers = SimpleConregStorage::loadAll([
      'eid' => $eid,
      'lead_mid' => $mid,
      'is_deleted' => 0,
    ]);
    // Remove the lead member from the group so they aren't duplicated.
    $groupMembers = array_filter($groupMembers, fn($member) => $member['mid'] != $mid);
    // Combine the lead member and the group, ensuring lead member is first.
    return array_merge($members, $groupMembers);
  }

}
