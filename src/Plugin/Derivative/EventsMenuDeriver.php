<?php

namespace Drupal\simple_conreg\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class to add extra links to the navigation menus.
 */
class EventsMenuDeriver extends DeriverBase implements ContainerDeriverInterface {

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

    $weight = -1;
    $events = SimpleConregEventStorage::loadAll();
    foreach ($events as $event) {
      $eid = $event['eid'];
      $parent_id = "conreg_event_$eid";
      $links[$parent_id] = [
        'title' => $event['event_name'],
        'route_name' => 'simple_conreg_admin_member_summary',
        'route_parameters' => ['eid' => $eid],
        'parent' => 'simple_conreg.events',
        'weight' => $weight,
      ] + $base_plugin_definition;

      $links["conreg_summary_$eid"] = [
        'title' => $this->t("Member summary"),
        'route_name' => 'simple_conreg_admin_member_summary',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 0,
      ] + $base_plugin_definition;

      $links["conreg_admin_$eid"] = [
        'title' => $this->t("Administer members"),
        'route_name' => 'simple_conreg_admin_members',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 1,
      ] + $base_plugin_definition;

      $links["conreg_details_$eid"] = [
        'title' => $this->t("List all member details"),
        'route_name' => 'simple_conreg_admin_member_list',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 2,
      ] + $base_plugin_definition;

      $links["conreg_emaillist_$eid"] = [
        'title' => $this->t("Export email mailing list"),
        'route_name' => 'simple_conreg_admin_mailout_emails',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 3,
      ] + $base_plugin_definition;

      $links["conreg_options_$eid"] = [
        'title' => $this->t("Selected options"),
        'route_name' => 'simple_conreg_admin_member_options',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 5,
      ] + $base_plugin_definition;

      $links["conreg_addons_$eid"] = [
        'title' => $this->t("Add-ons"),
        'route_name' => 'simple_conreg_admin_member_addons',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 6,
      ] + $base_plugin_definition;

      $links["conreg_children_$eid"] = [
        'title' => $this->t("Child members"),
        'route_name' => 'simple_conreg_admin_child_member_ages',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 7,
      ] + $base_plugin_definition;

      $links["conreg_fantable_$eid"] = [
        'title' => $this->t("Fan table registration"),
        'route_name' => 'simple_conreg_admin_fantable',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 11,
      ] + $base_plugin_definition;

      $links["conreg_checkin_$eid"] = [
        'title' => $this->t("Check-in"),
        'route_name' => 'simple_conreg_admin_checkin',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 15,
      ] + $base_plugin_definition;

      $links["conreg_badge_print_$eid"] = [
        'title' => $this->t("Badge printing"),
        'route_name' => 'simple_conreg_admin_member_badges',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 18,
      ] + $base_plugin_definition;

      $links["conreg_bulk_email_$eid"] = [
        'title' => $this->t("Bulk email sending"),
        'route_name' => 'simple_conreg_admin_bulk_email',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 20,
      ] + $base_plugin_definition;

      $links["conreg_config_$eid"] = [
        'title' => $this->t("Configure registration"),
        'route_name' => 'simple_conreg_config',
        'route_parameters' => ['eid' => $eid],
        'parent' => $base_plugin_definition['id'] . ':' . $parent_id,
        'weight' => 25,
      ] + $base_plugin_definition;

      $weight--;
    }

    return $links;
  }

}
