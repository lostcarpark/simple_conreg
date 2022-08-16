<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAdminMailoutEmails
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Link;
use Drupal\Core\URL;
use Drupal\Component\Utility\Html;
use Drupal\devel;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMailoutEmails extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID()
  {
    return 'simple_conreg_admin_mailout_emails';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    $config = $this->config('simple_conreg.settings.'.$eid);

    $form = array(
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    );

    $options = SimpleConregOptions::communicationMethod($eid, $config, TRUE);
    $form['communication_method'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Communications method'),
      '#options' => $options,
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ],
    );

    $languages = \Drupal::languageManager()->getLanguages();
    $langOptions = [];
    foreach ($languages as $language) {
      $langOptions[$language->getId()] = $language->getName();
    }
    $form['language'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Preferred languages'),
      '#options' => $langOptions,
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ],
    );

    $fieldOptions = [
      'name' => $this->t('Name'),
      'method' => $this->t('Communications method'),
      'language' => $this->t('Language'),
    ];
    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Show additional fields'),
      '#options' => $fieldOptions,
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => array($this, 'updateDisplayCallback'),
        'event' => 'change',
      ],
    );

    //Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();
    if (count($form_values)) {

      $showName = $form_values['fields']['name'];
      $showMethod = $form_values['fields']['method'];
      $showLanguage = $form_values['fields']['language'];

      $methods = [];
      foreach ($form_values['communication_method'] as $key=>$val) if ($val) $methods[] = $key;

      $languages = [];
      foreach ($form_values['language'] as $key=>$val) if ($val) $languages[] = $key;

      $headers = [];
      if ($showName) {
        $headers['first_name'] = ['data' => t('First name'), 'field' => 'm.first_name'];
        $headers['last_name'] = ['data' => t('Last name'), 'field' => 'm.last_name'];
      }
      $headers['email'] = ['data' => t('Email'), 'field' => 'm.email'];
      if ($showMethod) {
        $headers['communication_method'] = ['data' => t('Communication method'), 'field' => 'm.communication_method'];
      }
      if ($showLanguage) {
        $headers['language'] = ['data' => t('Language'), 'field' => 'm.language'];
      }

      $form['table'] = array(
        '#type' => 'table',
        '#header' => $headers,
        '#attributes' => array('id' => 'simple-conreg-admin-member-list'),
        '#empty' => t('No entries available.'),
        '#sticky' => TRUE,
      );      

      if (!empty($methods) && !empty($languages)) {
        // Fetch all entries for selected option or group.
        $mailoutMembers = SimpleConregStorage::adminMailoutListLoad($eid, $methods, $languages);
        
        // Now loop through the combined results.
        foreach ($mailoutMembers as $entry) {
          $row = array();
          if ($showName) {
            $row['first_name'] = array(
              '#markup' => Html::escape($entry['first_name']),
            );
            $row['last_name'] = array(
              '#markup' => Html::escape($entry['last_name']),
            );
          }
          $row['email'] = array(
            '#markup' => Html::escape($entry['email']),
          );
          if ($showMethod) {
            $row['communication_method'] = array(
              '#markup' => Html::escape($entry['communication_method']),
            );
          }
          if ($showLanguage) {
            $row['language'] = array(
              '#markup' => Html::escape($langOptions[$entry['language']]),
            );
          }
          $form['table'][] = $row;
        }
      }
    
    }
    return $form;
  }

  // Callback function for "display" drop down.
  public function updateDisplayCallback(array $form, FormStateInterface $form_state)
  {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }

}    

