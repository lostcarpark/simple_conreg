<?php
/**
 * @file
 * Contains \Drupal\conreg_planz\Form\ConfigPlanZForm
 */
namespace Drupal\conreg_planz\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\FieldOptions;
use Drupal\simple_conreg\SimpleConregTokens;
use Drupal\conreg_planz\PlanZ;
use Drupal\conreg_planz\BadgeIdSource;

use Drupal\devel;
use BadFunctionCallException;

/**
 * Configure simple_conreg settings for this site.
 */
class ConfigPlanZForm extends ConfigFormBase
{
  private PlanZ $planz;

  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'conreg_config_planz_options';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'conreg_planz.settings',
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

    $config = \Drupal::config('simple_conreg.settings.'.$eid.'.planz');
    $fieldOptions = FieldOptions::getFieldOptions($eid);
    $this->planz = new PlanZ($config);

    $form['#attached'] = [
      'library' => ['simple_conreg/conreg_admin'],
    ];

    $form['admin'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-new_addon',
    );

    $form['planz'] = array(
      '#type' => 'details',
      '#title' => $this->t('PlanZ Database'),
      '#group' => 'admin',
      '#weight' => -100,
    );

    $form['planz']['authenticate'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('PlanZ Database'),
      '#tree' => TRUE,
    );

    $form['planz']['authenticate']['target'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Database "target" selector',),
      '#description' => $this->t('This matches the final array selector in the $databases array, e.g. $databases[\'planz\'][\'default\']. Unless you need multiple PlanZ instances, leave at "default".'),
      '#default_value' => $this->planz->target,
    );

    $planZRoles = [];
    $count = 0;
    $test = $this->planz->test($count);
    if ($test == NULL) {
      $form['planz']['authenticate']['info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Please ensure that the following is present in settings.php:'),
      );

      $form['planz']['authenticate']['db_settings'] = [
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_db">',
        '#suffix' => '</div>',
        '#markup' => "<pre>\$databases['planz']['default'] = [
          'database' => 'DATABASE_NAME',
          'username' => 'USERNAME',
          'password' => 'PASSWORD',
          'prefix' => '',
          'host' => 'localhost',
          'port' => '3306',
          'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
          'driver' => 'mysql',
        ];</pre>",
      ];
    
    }
    elseif ($test == FALSE) {
      $form['planz']['authenticate']['info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Database found, but failed to read CongoDump table. Please check PlanZ schema in place.'),
      );
    }
    else {
      $form['planz']['authenticate']['info'] = array(
        '#type' => 'markup',
        '#prefix' => '<div class="conreg_info">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Tested PlanZ database connection. @count PlanZ users found.', ['@count' => $count]),
      );
    }

    $form['members'] = array(
      '#type' => 'details',
      '#title' => $this->t('Member details'),
      '#group' => 'admin',
      '#weight' => -100,
      '#tree' => TRUE,
    );

    $badgeIdOptions = [
      (BadgeIdSource::MemberID->value) => $this->t('Member ID (can invite newly joined member)'),
      (BadgeIdSource::MemberNumber->value) => $this->t('Member Number (not assigned until member approved)')
    ];
    $form['members']['badge_id_source'] =  [
      '#type' => 'select',
      '#title' => $this->t('Source for badge ID'),
      '#options' => $badgeIdOptions,
      '#default_value' => $this->planz->badgeIdSource->value,
    ];

    $form['members']['prefix'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('PlanZ badgeid prefix',),
      '#description' => $this->t('Specify a letter to prefix the member number.'),
      '#default_value' => $this->planz->prefix,
    );
    
    $form['members']['digits'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('PlanZ digits in badgeid',),
      '#description' => $this->t('Specify number of digits to pad member number.'),
      '#default_value' => $this->planz->digits,
    );
    
    $form['members']['generate_password'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Generate password when adding to PlanZ.'),
      '#default_value' => $this->planz->generatePassword,
    );

    // Put checkboxes on roles.
    if ($test) {
      $planZRoles = $this->planz->getPermissionRoles();
      $form['members']['roles'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Select PlanZ Roles to assign new members'),
        '#tree' => TRUE,
      );
      foreach ($planZRoles as $role) {
        $form['members']['roles'][$role['permroleid']] = array(
          '#type' => 'checkbox',
          '#title' => $role['permrolename'],
          '#default_value' => $this->planz->roles[$role['permroleid']],
        );
      }
    }

    $form['members']['interested_default'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Default "Interested" to true when adding new members.'),
      '#default_value' => $this->planz->interestedDefault,
    );

    $form['members']['url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('PlanZ URL',),
      '#description' => $this->t('The base URL of the PlanZ site (to include in invitation email). Should start with http:// or https://'),
      '#default_value' => $this->planz->planZUrl,
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
      '#markup' => $this->t('Select which option fields to invite member to PlanZ if selected.'),
    );

    foreach ($fieldOptions->options as $option) {
      $form['option_fields'][$option->optionId] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Invite members who select "@option"', ['@option' => $option->title]),
        '#default_value' => $this->planz->optionFields[$option->optionId],
      );
    }

    /**
     * Options for auto member adding.
     */

    $form['auto'] = array(
      '#type' => 'details',
      '#title' => $this->t('Automatic Member Adding'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['auto']['info'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="auto_member">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Automatically add members to PlanZ when approved, if any of specified options selected.'),
    );

    $form['auto']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic invites'),
      '#default_value' => $this->planz->autoEnabled,
    );

    $form['auto']['when_confirmed'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Only send invitation when member confirmed (if using member ID for badge ID, members will be invited immediately after joining if this is not checked)'),
      '#default_value' => $this->planz->autoWhenConfirmed,
    );

    /**
     * Options for auto member adding.
     */

    $form['email'] = array(
      '#type' => 'details',
      '#title' => $this->t('Email invitation'),
      '#group' => 'admin',
      '#weight' => 1,
      '#tree' => TRUE,
    );

    $form['email']['template_subject'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Invite email subject'),
      '#default_value' => $this->planz->emailTemplateSubject,
    );

    $form['email']['template_body'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('InviteBulk email body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp(['planz_user', 'planz_password', 'planz_url'])]),
      '#default_value' => $this->planz->emailTemplateBody,
      '#format' => $this->planz->emailTemplateFormat,
    );

    return parent::buildForm($form, $form_state);
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');

    $vals = $form_state->getValues();
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.'.$eid.'.planz');
    $config->set('target', $vals['authenticate']['target']);
    $config->set('badge_id_source', $vals['members']['badge_id_source']);
    $config->set('prefix', $vals['members']['prefix']);
    $config->set('digits', $vals['members']['digits']);
    $config->set('generate_password', $vals['members']['generate_password']);
    foreach ($vals['members']['roles'] as $key => $val) {
      $config->set('roles.' . $key, $val);
    }
    $config->set('interested_default', $vals['members']['interested_default']);
    $config->set('url', $vals['members']['url']);
    foreach ($vals['option_fields'] as $key => $val) {
      $config->set('option_fields.' . $key, $val);
    }
    $config->set('auto.enabled', $vals['auto']['enabled']);
    $config->set('auto.when_confirmed', $vals['auto']['when_confirmed']);
    $config->set('email.template_subject', $vals['email']['template_subject']);
    $config->set('email.template_body', $vals['email']['template_body']['value']);
    $config->set('email.template_format', $vals['email']['template_body']['format']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}



