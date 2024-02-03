<?php

namespace Drupal\simple_conreg\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\simple_conreg\SimpleConregStorage;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for ConReg - Simple Convention Registration routes.
 */
class LoginController extends ControllerBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The currently logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|null
   */
  protected $currentUser;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\user\UserInterface $current_user
   *   The currently logged in user.
   */
  public function __construct(TimeInterface $time, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, AccountProxyInterface $current_user) {
    $this->time = $time;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('current_user')
    );
  }

  /**
   * Check valid member credentials, login and redirect to member portal.
   */
  public function memberLoginAndRedirect($mid, $key, $expiry) {

    // Check member credentials valid.
    $member = SimpleConregStorage::load([
      'mid' => $mid,
      'random_key' => $key,
      'login_exp_date' => $expiry,
      'is_deleted' => 0,
    ]);
    if (empty($member['mid'])) {
      $content['markup'] = [
        '#markup' => '<p>Invalid credentials.</p>',
      ];
      return $content;
    }

    // Check if login has expired.
    if (empty($member['login_exp_date'] > $this->time->getRequestTime())) {
      $content['markup'] = [
        '#markup' => '<p>Login has expired. Please use Member Check to generate a new login link.</p>',
      ];
      return $content;
    }

    // Check if user already exists.
    $user = user_load_by_mail($member['email']);

    // Check if user already logged in. If so, redirect to member portal.
    if ($this->currentUser && $user && $user->id() == $this->currentUser->id()) {
      // Redirect to member portal.
      return $this->redirect('simple_conreg_portal', ['eid' => $member['eid']], ['absolute' => TRUE]);
    }

    // If user doesn't exist, create new user.
    if (!$user) {
      $language = $this->languageManager->getCurrentLanguage()->getId();
      $user = User::create([
        'name' => $member['email'],
        'mail' => $member['email'],
      ]);
      $user->set("langcode", $language);
      $user->set("preferred_langcode", $language);
      $user->set("preferred_admin_langcode", $language);
      // Set the user timezone to the site default timezone.
      $dateConfig = $this->configFactory->get('system.date');
      $config_data_default_timezone = $dateConfig->get('timezone.default');
      $user->set('timezone', $config_data_default_timezone ?: @date_default_timezone_get());
      // NOTE: login will fail silently if not activated!
      $user->activate();
      $user->save();
    }

    // Check if role needs to be added.
    $config = SimpleConregConfig::getConfig($member['eid']);
    $addRole = $config->get('member_portal.add_role');
    if ($addRole) {
      // Check if user has role already.
      if (!$user->hasRole($addRole)) {
        // They don't, so we need to add it.
        $user->addRole($addRole);
        $user->save();
      }
    }

    // Login user.
    user_login_finalize($user);

    // Redirecting at the same time as login was causing trouble, so after
    // loading, load JS to reload page. Redirect will happen on reload. As
    // fallback, display link to member portal.
    $url_object = Url::fromRoute('simple_conreg_portal', ['eid' => $member['eid']], ['absolute' => TRUE, 'query' => ['redirect' => 'redirect']]);
    $output = [
      // '#attached' => [
      //   'library' => ['simple_conreg/conreg_login'],
      // ],
      'link' => [
        '#type' => 'link',
        '#url' => $url_object,
        '#title' => $this->t('Enter Member Portal'),
      ]
    ];
    return $output;
  }

}
