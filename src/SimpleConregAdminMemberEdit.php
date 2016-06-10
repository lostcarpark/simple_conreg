<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMemberEdit
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\node\NodeInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_admin_member_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mid = NULL) {
    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    //dpm($form_values);
    $memberPrices = array();

dpm($mid);

    $config = $this->config('simple_conreg.settings');
    list($typeOptions, $typePrices) = SimpleConregOptions::memberTypes($config);
    list($addOnOptions, $addOnPrices) = SimpleConregOptions::memberAddons($config);
    $symbol = $config->get('payments.symbol');
    $countryOptions = SimpleConregOptions::memberCountries($config);
    $defaultCountry = $config->get('reference.default_country');
    
    $member = SimpleConregStorage::load(['mid' => 23]);

    $form = array(
      '#tree' => TRUE,
      '#prefix' => '<div id="regform">',
      '#suffix' => '</div>',
      '#attached' => array(
        'library' => array('simple_conreg/conreg_form')
      ),
    );

    $form['member'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Member details'),
    );

    $form['member']['first_name'] = array(
      '#type' => 'textfield',
      '#title' => $config->get('fields.first_name_label'),
      '#size' => 29,
      '#default_value' => $member['first_name'],
      '#attributes' => array(
        'id' => "edit-member-first-name",
        'class' => array('edit-members-first-name')),
      '#required' => ($config->get('fields.first_name_mandatory') ? TRUE : FALSE),
    );

    $form['member']['last_name'] = array(
      '#type' => 'textfield',
      '#title' => $config->get('fields.last_name_label'),
      '#size' => 29,
      '#default_value' => $member['last_name'],
      '#attributes' => array(
        'id' => "edit-member-last-name",
        'class' => array('edit-members-last-name')),
      '#required' => ($config->get('fields.last_name_mandatory') ? TRUE : FALSE),
    );

    $form['member']['email'] = array(
      '#type' => 'email',
      '#title' => $config->get('fields.email_label'),
      '#default_value' => $member['email'],
    );

    $form['member']['type'] = array(
      '#type' => 'select',
      '#title' => $config->get('fields.membership_type_label'),
      '#options' => $typeOptions,
      '#default_value' => $member['member_type'],
      '#required' => TRUE,
    );

    if (!empty($config->get('add_ons.label'))) {
      $form['member']['add_on'] = array(
        '#type' => 'select',
        '#title' => $config->get('add_ons.label'),
        '#description' => $config->get('add_ons.description'),
        '#options' => $addOnOptions,
        '#default_value' => $member['add_on'],
        '#required' => TRUE,
      );

      if (!empty($config->get('add_on_info.label'))) {
        $form['member']['add_on_extra']['info'] = array(
          '#type' => 'textfield',
          '#title' => $config->get('add_on_info.label'),
          '#description' => $config->get('add_on_info.description'),
          '#default_value' => $member['add_on_info'],
        );
      }
    }

    $form['member']['badge_name']['other'] = array(
      '#type' => 'textfield',
      '#title' => $config->get('fields.badge_name_label'),
      '#default_value' => $member['badge_name'],
      '#required' => TRUE,
      '#attributes' => array(
        'id' => "edit-members-member$cnt-badge-name",
        'class' => array('edit-members-badge-name')),
    );

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
  }

}
