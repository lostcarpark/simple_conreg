<?php
/**
 * @file
 * Contains \Drupal\conreg_discord\Form\ConfigDiscordForm
 */
namespace Drupal\conreg_discord\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\FieldOptions;
use Drupal\simple_conreg\SimpleConregTokens;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\Member;
use Drupal\conreg_discord\Discord;

use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class ConfigDiscordForm extends ConfigFormBase
{
  private $config;
  private $discord;
  private $memberTypes;

  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'conreg_config_discord_options';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'conreg_discord.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    // Fetch event name from Event table.
    if (count($event = SimpleConregEventStorage::load(['eid' => $eid])) < 3) {
      // Event not in database. Display error.
      $form['simple_conreg_event'] = array(
        '#markup' => $this->t('Event not found. Please contact site admin.'),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      );
      return parent::buildForm($form, $form_state);
    }

    $this->config = SimpleConregConfig::getConfig($eid);
    $fieldOptions = FieldOptions::getFieldOptions($eid);
    $types = SimpleConregOptions::memberTypes($eid, $this->config);

    $form['#attached'] = [
      'library' => ['simple_conreg/conreg_admin'],
    ];

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-new_addon',
    );

    $form['discord'] = array(
      '#type' => 'details',
      '#title' => $this->t('Discord Details'),
      '#group' => 'admin',
      '#weight' => -100,
    );

    $form['discord']['discord_authenticate'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Discord Server'),
      '#tree' => TRUE,
    );

    $token = $this->config->get('discord.token');
    $form['discord']['discord_authenticate']['token'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Discord Bot Token',),
      '#description' => $this->t('To connect to Discord you must visit <a href="https://discord.com/developers/applications">https://discord.com/developers/applications</a> and create an API Application, then copy the Bot token and paste here.'),
      '#default_value' => $token,
    );

    $channelId = $this->config->get('discord.channel_id');
    $form['discord']['discord_authenticate']['channel_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Discord Channel ID',),
      '#description' => $this->t('To connect to Discord you must visit <a href="https://discord.com/developers/applications">https://discord.com/developers/applications</a> and create an API Application, then get the Bot token.'),
      '#default_value' => $channelId,
    );

    if (!empty($token) && !empty($channelId)) {
      $this->discord = new Discord($token, $channelId);
      $channel = $this->discord->getChannel();
      $form['discord']['discord_authenticate']['channel_info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Channel Name: @name', ['@name' => $channel->name]),
      );
    }
/*
    $form['discord']['zambia_authenticate']['prefix'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zambia badgeid prefix',),
      '#description' => $this->t('Specify a letter to prefix the member number.'),
      '#default_value' => $this->config->get('zambia.prefix'),
    );
    
    $form['discord']['zambia_authenticate']['digits'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zambia digits in badgeid',),
      '#description' => $this->t('Specify number of digits to pad member number.'),
      '#default_value' => $this->config->get('zambia.digits'),
    );
    
    // Put checkboxes on roles.
    if ($test) {
      $zambiaRoles = $this->zambia->getPermissionRoles();
      $form['discord']['zambia_authenticate']['roles'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Select Zambia Roles to assign new members'),
        '#tree' => TRUE,
      );
      foreach ($zambiaRoles as $role) {
        $form['discord']['zambia_authenticate']['roles'][$role['permroleid']] = array(
          '#type' => 'checkbox',
          '#title' => $role['permrolename'],
          '#default_value' => $this->config->get('zambia.roles.'.$role['permroleid']),
        );
      }
    }

    $form['discord']['zambia_authenticate']['interested_default'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Default "Interested" to true when adding new members.'),
      '#default_value' => $this->config->get('zambia.interested_default'),
    );

    $form['discord']['zambia_authenticate']['url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zambia URL',),
      '#description' => $this->t('The URL of the Zambia site (to include in invitation email).'),
      '#default_value' => $this->config->get('zambia.url'),
    );
*/
    /**
     * Member types to generate invites for.
     */

    $form['member_types'] = array(
      '#type' => 'details',
      '#title' => $this->t('Member Types'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['member_types']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Select which member types to generate Discord invites for.'),
    );

    $this->memberTypes = [];
    foreach ($types->types as $code => $type) {
      $typeVal = $this->config->get('discord.types.'.$code);
      if ($typeVal) {
        $this->memberTypes[] = $code;
      }
      $form['member_types'][$code] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Invite members of type "@type"', ['@type' => $type->name]),
        '#default_value' => $typeVal,
      );
    }
    $form_state->set('memberTypes', $this->memberTypes);

    /**
     * Options for auto member adding.
     */

    $form['invite_template'] = array(
      '#type' => 'details',
      '#title' => $this->t('Invite Template'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['invite_template']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="auto_member">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Automatically add members to Discord when approved, if any of specified options selected.'),
    );

    $form['invite_template']['template_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Invite email subject'),
      '#default_value' => $this->config->get('discord.template_subject'),
    );

    $form['invite_template']['template_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('InviteBulk email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp(['[invite_url]'])]),
      '#default_value' => $this->config->get('discord.template_body'),
      '#format' => $this->config->get('discord.template_format'),
    );

    /**
     * Manual member invites.
     */

    $form['discord_invites'] = array(
      '#type' => 'details',
      '#title' => $this->t('Discord Invites'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['discord_invites']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="email_members">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Generate Discord invitation links and send invite emails.'),
    );

    $form['discord_invites']['member_range'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Member Number Range to Invite'),
      '#description' => $this->t('Enter Member Nos to invite. Use commas (,) to separate ranges and hyphens (-) to separate range limits, e.g. "1,3,5-7". Leave blank to send to all uninvited members.')
    );

    $maxInvites = $this->config->get('discord.max_invites');
    $form['discord_invites']['max_invites'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum number of invites sent per run'),
      '#description' => $this->t('Enter the maximum number of invitations that will be sent in one run. Avoid setting too high, or the server may time out.'),
      '#default_value' => (empty($maxInvites) ? 50 : $maxInvites),
    );

    $form['discord_invites']['override'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Override member types - all members in range will be added. Leave unchecked unless you need to invite a member who wouldn\'t normally get one.'),
    );

    $form['discord_invites']['resend'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Resend invite link - members in range will be resent email with existing invite link.'),
    );

    $form['discord_invites']['regenerate'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate invite link - members in range will get a new invite link, even if they received one already.'),
    );

    $form['discord_invites']['dont_email'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Don\'t email - if checked no emails will be sent, invite info will display on page. Only use for members who have difficulty receiving emails.'),
    );

    $form['discord_invites']['replace'] = array(
      '#type' => 'markup',
      '#prefix' => '<div id="discord-invite-replace">',
      '#suffix' => '</div>',
    );

    $form['discord_invites']['replace']['waiting'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->getAwaitingInviteCount($eid),
    );

    $form['discord_invites']['replace']['invite'] = [
      '#type' => 'button',
      '#value' => $this->t('Invite to Discord'),
      '#ajax' => [
        'wrapper' => 'discord-invite-replace',
        'callback' => array($this, 'callbackGenerateInvites'),
        'event' => 'click',
      ],
    ];

    $form['discord_invites']['replace']['result'] = array(
      '#type' => 'markup',
      '#prefix' => '<div id="manual-add-result">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Results will go here.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $vals = $form_state->getValues();
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid);
    $config->set('discord.token', $vals['discord_authenticate']['token']);
    $config->set('discord.channel_id', $vals['discord_authenticate']['channel_id']);
    foreach ($vals['member_types'] as $code => $val) {
      $config->set('discord.types.' . $code, $val);
    }

    $config->set('discord.template_subject', $vals['invite_template']['template_subject']);
    $config->set('discord.template_body', $vals['invite_template']['template_body']['value']);
    $config->set('discord.template_format', $vals['invite_template']['template_body']['format']);
    $config->set('discord.max_invites', $vals['discord_invites']['max_invites']);

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /** 
   * Callback function for "Generate Invites" button.
   */
  public function callbackGenerateInvites(array $form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    //$this->memberTypes = $form_state->get('memberTypes');
    //$form['discord_invites']['replace']['result']['#markup'] = print_r($this->memberTypes, TRUE);
    //$memberNos = $this->getAwaitingMemberNos($eid);
    //$form['discord_invites']['replace']['result']['#markup'] = print_r($memberNos, TRUE);

    $output = [];
    $vals = $form_state->getValues();

    if (empty($vals['discord_invites']['max_invites'])) {
      $form['discord_invites']['replace']['result']['#markup'] = $this->t('You must specify a maximum number of invites.');
    }
    else if (empty(trim($vals['discord_invites']['member_range']))) {
      $this->inviteAllMembers($eid, $form, $vals);
      //$form['discord_invites']['replace']['result']['#markup'] = $this->t('Inviting all members...');
    }
    else if (preg_match('/^([0-9]+(\-[0-9]+)?,)*[0-9]+(\-[0-9]+)?$/', $vals['discord_invites']['member_range']) == 1) {
      $this->inviteMemberRange($eid, $form, $vals);
    }
    else {
      $form['discord_invites']['replace']['result']['#markup'] = $this->t('Member numbers not in correct format.');
    }
    
    // Update count of members awaiting invites.
    $form['discord_invites']['replace']['waiting']['#markup'] = $this->getAwaitingInviteCount($eid);

    return $form['discord_invites']['replace'];
  }

  /** 
   * Invite all members - up to maximum number - when no member range specified.
   */
  private function inviteAllMembers($eid, array $form, $vals) {
    $max = $vals['discord_invites']['max_invites'];
    $count = 0;
    if ($vals['discord_invites']['override'] || $vals['discord_invites']['resend'] || $vals['discord_invites']['regenerate'] || $vals['discord_invites']['dont_email']) {
      $form['discord_invites']['replace']['result']['#markup'] = $this->t('You must either specify a member number range, or leave checkboxes unchecked.');
      return;
    }
    $form['discord_invites']['replace']['result']['#markup'] = $this->t('Inviting all members...');
    $memberNos = $this->getAwaitingMemberNos($eid);
    $output = []; 
    foreach ($memberNos as $memRec) {
      //$invite = print_r($memRec['member_no'], TRUE);
      $invite = $this->inviteToDiscord($eid, $memRec['member_no'], $vals['discord_invites']['override'], $vals['discord_invites']['resend'], $vals['discord_invites']['regenerate'], $vals['discord_invites']['dont_email'], $vals['option_fields']);
      if (!empty($invite)) {
        $output[] = $invite;
        $count++;
        if ($count >= $max) {
          break;
        }
      }
    }
    $form['discord_invites']['replace']['result']['#markup'] = implode("\n", $output);
    $form['discord_invites']['replace']['result']['#markup'] .= '<p>Invites sent: ' . $count . '</p>';
  }

  /** 
   * Invite members in specified range.
   */
  private function inviteMemberRange($eid, array $form, $vals) {
    $max = $vals['discord_invites']['max_invites'];
    $count = 0;
    $output = []; 
    foreach (explode(',', $vals['discord_invites']['member_range']) as $range) {
      list($min, $max) = array_pad(explode('-', $range), 2, '');
      If (empty($max)) {
        // If no max set, range is single number in min.
        $invite = $this->inviteToDiscord($eid, $min, $vals['discord_invites']['override'], $vals['discord_invites']['resend'], $vals['discord_invites']['regenerate'], $vals['discord_invites']['dont_email'], $vals['option_fields']);
        if (!empty($invite)) {
          $output[] = $invite;
          $count++;
          if ($count >= $max) {
            break;
          }
        }
      }
      else {
        for ($num = $min; $num <= $max; $num++) {
          $invite = $this->inviteToDiscord($eid, $num, $vals['discord_invites']['override'], $vals['discord_invites']['resend'], $vals['discord_invites']['regenerate'], $vals['discord_invites']['dont_email'], $vals['option_fields']);
          if (!empty($invite)) {
            $output[] = $invite;
            $count++;
            if ($count >= $max) {
              break 2; // Break out of both for loops.
            }
          }
        }
      }
    // Log an event to show a member check occurred.
    //\Drupal::logger('conreg_discord')->info("Manual Add pressed.");
    }
    $form['discord_invites']['replace']['result']['#markup'] = implode("\n", $output);
    $form['discord_invites']['replace']['result']['#markup'] .= '<p>Invites sent: ' . $count . '</p>';
  }

  private function inviteToDiscord($eid, $memberNo, $override, $resend, $regenerate, $dontEmail, $optionFields)
  {
    $connection = \Drupal::database();

    // Get the member details.    
    $member = Member::loadMemberByMemberNo($eid, $memberNo);

    // Check if the member type is enabled for Discord, unless override checked.
    if (!$override) {
      $match = FALSE;
      foreach ($this->memberTypes as $type) {
        if ($member->member_type == $type) {
          $match = TRUE;
        }
      }
      if (!$match) {
        return FALSE;
      }
    }

    // Check if the member has been sent a Discord invite already.
    $query = $connection->select('conreg_discord', 'd');
    $query->addField('d', 'invite_code');
    $query->condition('d.mid', $member->mid);
    $inviteCode = $query->execute()->fetchField();

    // Decide whether we need to generate an invite.
    if (empty($inviteCode) || $regenerate) {
      $newCode = $this->discord->getChannelInvite();
      $inviteCode = $newCode;
      if (!empty($inviteCode)) {
        $connection->upsert('conreg_discord')
          ->fields([
                  'mid' => $member->mid,
                  'invite_code' => $inviteCode,
                  'update_date' => time(),
                  ])
          ->key('mid')
          ->execute();
      }
    }

    //return "<p>Invite member no $memberNo; member ".$member->mid."; type ".$member->member_type."; match $match; invite code $inviteCode; new code $newCode</p>";
    if (!empty($newCode) || $resend) {    
      $inviteUrl = Discord::INVITE_URL . $inviteCode;
      if (!$dontEmail) {
        // Send email to user.
        $this->sendInviteEmail($member, $inviteUrl);
      }
      
      return $this->t('<p>Member Name: @first_name @last_name<br />'.
                      'Member No: @member_no<br />'.
                      'Email: @email<br />'.
                      'Invite URL: @url</p>',
                      ['@first_name' => $member->first_name, '@last_name' => $member->last_name, '@member_no' => $member->member_no, '@email' => $member->email, '@url' => $inviteUrl]);
    }
    // If invite not sent, return false.
    return FALSE;
  }
  
  private function getAwaitingInviteCount($eid)
  {
    $query = $this->getQuery($eid);
    return $this->t('Number of members awaiting invitations: @count', ['@count' => $query->countQuery()->execute()->fetchField()]);
  }
  
  private function getAwaitingMemberNos($eid)
  {
    $query = $this->getQuery($eid);
    $query->addField('m', 'member_no');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function getQuery($eid)
  {
    $connection = \Drupal::database();

    $subquery = $connection->select('conreg_discord', 'd');
    $subquery->addExpression('NULL');
    $subquery->where("m.mid = d.mid");
    
    $query = $connection->select('conreg_members', 'm');
    $query->condition('m.eid', $eid);
    $query->condition('m.is_paid', 1);
    $query->condition('m.is_approved', 1);
    $query->condition("m.is_deleted", 0); //Only include members who aren't deleted.
    if (isset($this->memberTypes) && is_array($this->memberTypes) && count($this->memberTypes) > 0) {
      $query->condition('m.member_type', $this->memberTypes, 'IN');
    }
    $query->notExists($subquery);
    return $query;
  }
  
  public function sendInviteEmail(Member $member, $inviteUrl)
  {
    // Get ConReg tokens, so we can add Zambia tokens.
    $tokens = new SimpleConregTokens($member->eid, $member->mid);
    $tokens->addExtraTokens(['[invite_url]' => $inviteUrl]);
    
    // Set up parameters for receipt email.
    $params = ['eid' => $member->eid, 'mid' => $member->mid, 'tokens' => $tokens];
    $params['subject'] = $this->config->get('discord.template_subject');
    $params['body'] = $this->config->get('discord.template_body');
    $params['body_format'] = $this->config->get('discord.template_format');
    $module = "simple_conreg";
    $key = "template";
    $to = $member->email;
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    if (!empty($member->email))
      $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);
  }
}



