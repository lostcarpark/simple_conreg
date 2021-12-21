<?php
/**
 * @file
 * Contains \Drupal\conreg_zambia\Form\ConfigZambiaForm
 */
namespace Drupal\conreg_zambia\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\FieldOptions;
use Drupal\simple_conreg\SimpleConregTokens;
use Drupal\simple_conreg\Member;
use Drupal\conreg_zambia\Zambia;
use Drupal\conreg_zambia\ZambiaUser;

use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class ConfigZambiaForm extends ConfigFormBase
{
  private $zambia;

  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'conreg_config_zambia_options';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'conreg_zambia.settings',
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

    $config = SimpleConregConfig::getConfig($eid);
    $fieldOptions = FieldOptions::getFieldOptions($eid);
    $this->zambia = new Zambia($config);

    $form['#attached'] = [
      'library' => ['simple_conreg/conreg_admin'],
    ];

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-new_addon',
    );

    $form['zambia'] = array(
      '#type' => 'details',
      '#title' => $this->t('Zambia Details'),
      '#group' => 'admin',
      '#weight' => -100,
    );

    $form['zambia']['zambia_authenticate'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Zambia Database'),
      '#tree' => TRUE,
    );

    $form['zambia']['zambia_authenticate']['target'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Database "target" selector',),
      '#description' => $this->t('This matches the final array selector in the $databases array, e.g. $databases[\'zambia\'][\'default\']. Unless you need multiple Zambia instances, leave at "default".'),
      '#default_value' => $this->zambia->target,
    );

    $zambiaRoles = [];
    $test = $this->zambia->test($count);
    if ($test == NULL) {
      $form['zambia']['zambia_authenticate']['info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Please ensure that the following is present in settings.php:'),
      );

      $form['zambia']['zambia_authenticate']['db_settings'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_db">',
        '#suffix' => '</div>',
        '#markup' => "<pre>\$databases['zambia']['default'] = array (
    'database' => 'DATABASE_NAME',
    'username' => 'USERNAME',
    'password' => 'PASSWORD',
    'prefix' => '',
    'host' => 'localhost',
    'port' => '3306',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => 'mysql',
  );</pre>",
      );
    
    }
    elseif ($test == FALSE) {
      $form['zambia']['zambia_authenticate']['info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Database found, but failed to read CongoDump table. Please check Zambia schema in place.'),
      );
    }
    else {
      $form['zambia']['zambia_authenticate']['info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Tested Zambia database connection. @count Zambia users found.', ['@count' => $count]),
      );
    }

    $form['zambia']['zambia_authenticate']['prefix'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zambia badgeid prefix',),
      '#description' => $this->t('Specify a letter to prefix the member number.'),
      '#default_value' => $config->get('zambia.prefix'),
    );
    
    $form['zambia']['zambia_authenticate']['digits'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zambia digits in badgeid',),
      '#description' => $this->t('Specify number of digits to pad member number.'),
      '#default_value' => $config->get('zambia.digits'),
    );
    
    // Put checkboxes on roles.
    if ($test) {
      $zambiaRoles = $this->zambia->getPermissionRoles();
      $form['zambia']['zambia_authenticate']['roles'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Select Zambia Roles to assign new members'),
        '#tree' => TRUE,
      );
      foreach ($zambiaRoles as $role) {
        $form['zambia']['zambia_authenticate']['roles'][$role['permroleid']] = array(
          '#type' => 'checkbox',
          '#title' => $role['permrolename'],
          '#default_value' => $config->get('zambia.roles.'.$role['permroleid']),
        );
      }
    }

    $form['zambia']['zambia_authenticate']['interested_default'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Default "Interested" to true when adding new members.'),
      '#default_value' => $config->get('zambia.interested_default'),
    );

    $form['zambia']['zambia_authenticate']['url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zambia URL',),
      '#description' => $this->t('The URL of the Zambia site (to include in invitation email).'),
      '#default_value' => $config->get('zambia.url'),
    );

    /**
     * Field mappings for option fields.
     */

    $form['option_fields'] = array(
      '#type' => 'details',
      '#title' => $this->t('Option Fields'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['option_fields']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="conreg_info">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Select which option fields to invite member to Zambia if selected.'),
    );

    foreach ($fieldOptions->options as $option) {
      $form['option_fields'][$option->optionId] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Invite members who select "@option"', ['@option' => $option->title]),
        '#default_value' => $config->get('zambia.option_fields.'.$option->optionId),
      );
    }

    /**
     * Options for auto member adding.
     */

    $form['auto_member'] = array(
      '#type' => 'details',
      '#title' => $this->t('Automatic Member Adding'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['auto_member']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="auto_member">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Automatically add members to Zambia when approved, if any of specified options selected.'),
    );

    $form['auto_member']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('zambia_auto.enabled'),
    );

    $form['auto_member']['template_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Invite email subject'),
      '#default_value' => $config->get('zambia_auto.template_subject'),
    );

    $form['auto_member']['template_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('InviteBulk email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp(['zambia_user', 'zambia_password', 'zambia_url'])]),
      '#default_value' => $config->get('zambia_auto.template_body'),
      '#format' => $config->get('zambia_auto.template_format'),
    );

    /**
     * Manual member invites.
     */

    $form['manual_invites'] = array(
      '#type' => 'details',
      '#title' => $this->t('Manual Member Invites'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['manual_invites']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="email_members">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Manually add members and send invite emails.'),
    );

    $form['manual_invites']['member_search'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Search for member'),
    );

    $form['manual_invites']['search_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#ajax' => [
        'wrapper' => 'search-results',
        'callback' => array($this, 'callbackSearch'),
        'event' => 'click',
      ],
    ];

    $form['manual_invites']['search_results'] = array(
      '#type' => 'markup',
      '#prefix' => '<div id="search-results">',
      '#suffix' => '</div>',
    );

    $form['manual_invites']['member_range'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Member Number Range to Invite'),
      '#description' => $this->t('Enter Member Nos to invite. Use commas (,) to separate ranges and hyphens (-) to separate range limits, e.g. "1,3,5-7".')
    );

    $form['manual_invites']['override'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Override member options - all members in range will be added'),
    );

    $form['manual_invites']['reset'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Reset passwords for existing members - if checked will update password of members already existing on Zambia with new random ones'),
    );

    $form['manual_invites']['dont_email'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Don\'t email - if checked no emails will be sent, member info will display on page. Only use for members who have difficulty receiving emails.'),
    );

    $form['manual_invites']['manual_add'] = [
      '#type' => 'button',
      '#value' => $this->t('Manual Add to Zambia'),
      '#ajax' => [
        'wrapper' => 'manual-add-result',
        'callback' => array($this, 'callbackManualAdd'),
        'event' => 'click',
      ],
    ];

    $form['manual_invites']['result'] = array(
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
    $config->set('zambia.target', $vals['zambia_authenticate']['target']);
    $config->set('zambia.prefix', $vals['zambia_authenticate']['prefix']);
    $config->set('zambia.digits', $vals['zambia_authenticate']['digits']);
    foreach ($vals['zambia_authenticate']['roles'] as $key => $val) {
      $config->set('zambia.roles.' . $key, $val);
    }
    $config->set('zambia.interested_default', $vals['zambia_authenticate']['interested_default']);
    $config->set('zambia.url', $vals['zambia_authenticate']['url']);
    foreach ($vals['option_fields'] as $key => $val) {
      $config->set('zambia.option_fields.' . $key, $val);
    }
    $config->set('zambia_auto.enabled', $vals['auto_member']['enabled']);
    $config->set('zambia_auto.template_subject', $vals['auto_member']['template_subject']);
    $config->set('zambia_auto.template_body', $vals['auto_member']['template_body']['value']);
    $config->set('zambia_auto.template_format', $vals['auto_member']['template_body']['format']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  public function callbackManualAdd(array $form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $output = [];
    $vals = $form_state->getValues();
    if (preg_match('/^([0-9]+(\-[0-9]+)?,)*[0-9]+(\-[0-9]+)?$/', $vals['manual_invites']['member_range']) == 1) {
      foreach (explode(',', $vals['manual_invites']['member_range']) as $range) {
        list($min, $max) = array_pad(explode('-', $range), 2, '');
        If (empty($max)) {
          // If no max set, range is single number in min.
          $output[] = $this->addMemberToZambia($eid, $min, $vals['manual_invites']['override'], $vals['manual_invites']['reset'], $vals['manual_invites']['dont_email'], $vals['option_fields']);
        }
        else {
          for ($num = $min; $num <= $max; $num++) {
            $output[] = $this->addMemberToZambia($eid, $num, $vals['manual_invites']['override'], $vals['manual_invites']['reset'], $vals['manual_invites']['dont_email'], $vals['option_fields']);
          }
        }
      // Log an event to show a member check occurred.
      //\Drupal::logger('conreg_zambia')->info("Manual Add pressed.");
      }
      $form['manual_invites']['result']['#markup'] = implode("\n", $output);
    }
    else {
      $form['manual_invites']['result']['#markup'] = $this->t('Member numbers not in correct format.');
    }

    return $form['manual_invites']['result'];
  }

  public function callbackSearch(array $form, FormStateInterface $form_state) {
    $vals = $form_state->getValues();
    $eid = $form_state->get('eid');

    $form['manual_invites']['search_results']['head'] = [
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => $this->t('Search results for @terms.', ['@terms' => $vals['manual_invites']['member_search']]),
    ];

    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->leftJoin('conreg_zambia', 'z', 'm.mid = z.mid');
    $select->addField('z', 'badgeid');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition('m.is_approved', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    foreach (explode(' ', $vals['manual_invites']['member_search']) as $word) {
      // Escape search word to prevent dangerous characters.
      $esc_word = '%'.$connection->escapeLike($word).'%';
      $likes = $select->orConditionGroup()
        ->condition('m.first_name', $esc_word, 'LIKE')
        ->condition('m.last_name', $esc_word, 'LIKE')
        ->condition('m.badge_name', $esc_word, 'LIKE')
        ->condition('m.email', $esc_word, 'LIKE');
      $select->condition($likes);
    }
    $select->orderBy('m.member_no');
    // Make sure we only get items 0-49, for scalability reasons.
    //$select->range(0, 50);

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $rows = array();
    $headers = array(
      t('Member No'),
      t('First Name'),
      t('Last Name'),
      t('email'),
      t('Zambia Badge ID'),
    );

    foreach ($entries as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    
    $form['manual_invites']['search_results']['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
      '#sticky' => TRUE,
    );

    return $form['manual_invites']['search_results'];
  }
  
  private function addMemberToZambia($eid, $memberNo, $override, $reset, $dontEmail, $optionFields)
  {
    $member = Member::loadMemberByMemberNo($eid, $memberNo);
    $match = FALSE;
    foreach ($optionFields as $optId => $optVal) {
      if ($optVal) {
        if (isset($member->options[$optId]) && $member->options[$optId]->isSelected) {
          $match = TRUE;
        }
      }
    }
    if ($match || $override) {
      $user = new ZambiaUser($this->zambia);
      $user->load($member->mid);
      $user->save($member, $reset);
      
      if (!$dontEmail) {
        // Send email to user.
        $this->zambia->sendInviteEmail($user);
      }
      
      return $this->t('<p>Member: @first_name @last_name<br />'.
                      'Badge id: @badgeid.<br />'.
                      'Password: @password<br />'.
                      'URL: @url</p>',
                      ['@first_name' => $member->first_name, '@last_name' => $member->last_name, '@badgeid' => $user->badgeId, '@password' => $user->password, '@url' => $this->zambia->config->get('zambia.url')]);
    }
  }

}



