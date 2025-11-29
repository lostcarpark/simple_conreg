<?php

namespace Drupal\conreg_badges\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class to add extra links to the navigation menus.
 */
class BadgesMenuDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    $events = SimpleConregEventStorage::loadAll();
    foreach ($events as $event) {
      $eid = $event['eid'];
      $links["conreg_badges_$eid"] = [
        'title' => $this->t("Badge export"),
        'route_name' => 'conreg_badges_list',
        'route_parameters' => ['eid' => $eid],
        'parent' => "simple_conreg.event_links:conreg_event_$eid",
        'weight' => 17,
      ] + $base_plugin_definition;
    }

    return $links;
  }

}
