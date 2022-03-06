<?php

/**
 * @file
 * Hooks specific to the Node module.
 */

/**
 * Hooks triggered when convention members added/updated/removed.
 */

/**
 * Triggered when new member created.
 * @param array $member
 *   Array containing the newly added member.
 */

function hook_convention_member_added($member)
{
  \Drupal::messenger()->addMessage(t("Member %first %last has been added.", ['first' => $member['first_name'], 'last' => $member['last_name']]));
}

/**
 * Triggered when member updated.
 * @param array $member
 *   Array containing the newly added member.
 */

function hook_convention_member_updated($member)
{
  \Drupal::messenger()->addMessage(t("Member %first %last has been updated.", ['first' => $member['first_name'], 'last' => $member['last_name']]));
}

/**
 * Triggered when member deleted.
 * @param array $member
 *   Array containing the newly added member.
 */

function hook_convention_member_deleted($member)
{
  \Drupal::messenger()->addMessage(t("Member %first %last has been deleted.", ['first' => $member['first_name'], 'last' => $member['last_name']]));
}
