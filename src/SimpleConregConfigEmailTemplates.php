<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregConfigEmailTemplates
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;

/**
 * Configure simple_conreg settings for this site.
 */
class SimpleConregConfigEmailTemplates extends ConfigFormBase {
  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_config';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'simple_conreg.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get email templates config.
    $config = $this->config('simple_conreg.email_templates');
    if (empty($count = $config->get('count'))) {
      $count = 0;
    }

    // We want to show one more templates than the number saved.
    $count++;

    // Container for templates.
    $form['templates'] = array(
      '#prefix' => '<div id="templates">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    );

    for ($template = 1; $template <= $count; $template++) {
      /*
       * Fields for email templates.
       */

      $form['templates']['template'.$template] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Email template @template', ['@template' => $template]),
        '#tree' => TRUE,
      );

      $form['templates']['template'.$template]['subject'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $config->get('template'.$template.'subject'),
      );

      $form['templates']['template'.$template]['body'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Body'),
      '#description' => $this->t('Text for the email body. you may use the following tokens: @tokens.', ['@tokens' => SimpleConregTokens::tokenHelp()]),
        '#default_value' => $config->get('template'.$template.'body'),
      );  
    }


    // Make sure last template is blank.
    $form['templates']['template'.$count]['subject']['#default_value'] = '';
    $form['templates']['template'.$count]['body']['#default_value'] = '';

    return parent::buildForm($form, $form_state);
  }
  
  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $vals = $form_state->getValues();

    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.email_templates');
    $count = 0;
    foreach ($vals['templates'] as $key => $template) {
      if (!empty($template['subject']) || !empty($template['body'])) {
        $count++;
        $config->set('template'.$count.'subject', $template['subject']);
        $config->set('template'.$count.'body', $template['body']);
      }
    }
    $config->set('count', $count);
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
