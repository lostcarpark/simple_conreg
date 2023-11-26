<?php

namespace Drupal\simple_conreg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for ConReg - Simple Convention Registration routes.
 */
class BulkMailController extends ControllerBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager service.
   */
  public function __construct(LanguageManagerInterface $language_manager, MailManagerInterface $mail_manager) {
    $this->languageManager = $language_manager;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * Send an email to a member when triggered by bulk emailer.
   *
   * @param int $mid
   *   The member id.
   *
   * @return array
   *   Content array containing send status.
   */
  public function bulkSend(int $mid): array {
    // Look up email address for member.
    $member = SimpleConregStorage::load([
      'mid' => $mid,
      'is_deleted' => 0,
    ]);

    $config = SimpleConregConfig::getConfig($member['eid']);

    // Set up parameters for receipt email.
    $params = ['eid' => $member['eid'], 'mid' => $member['mid']];
    $params['subject'] = $config->get('bulkemail.template_subject');
    $params['body'] = $config->get('bulkemail.template_body');
    $params['body_format'] = $config->get('bulkemail.template_format');
    $module = "simple_conreg";
    $key = "template";
    $to = $member["email"];
    $language_code = $this->languageManager->getDefaultLanguage()->getId();

    // Send confirmation email to member.
    if (!empty($member["email"])) {
      $this->mailManager->mail($module, $key, $to, $language_code, $params);
    }

    $content['markup'] = [
      '#markup' => '<p>Bulk send.</p>',
    ];
    return $content;
  }

}
