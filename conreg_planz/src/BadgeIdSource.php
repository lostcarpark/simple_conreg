<?php

/**
 * @file
 * Contains \Drupal\conreg_planz\BadgeIdSource.
 */

namespace Drupal\conreg_planz;

enum BadgeIdSource: string {
  case MemberID = "mid";
  case MemberNumber = "mno";
}