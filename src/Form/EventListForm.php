<?php
/**
 * @file
 * Contains \Drupal\simple_conreg\Form\EventListForm
 */
namespace Drupal\simple_conreg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregTokens;

/**
 * Configure simple_conreg settings for this site.
 */
class EventListForm extends ConfigFormBase
{
  /** 
   * {@inheritdoc}
   */
  public function getFormId() 
  {
    return 'simple_conreg_admin_event_list';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() 
  {
    return [
      'simple_conreg.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) 
  {
    return $form;
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) 
  {
  }

}