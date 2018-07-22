<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregOptions.
 */

namespace Drupal\simple_conreg;

use Drupal\user\Entity\User;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregUser {

  /**
   * Return list of membership types from config.
   *
   * Parameters: Optional config.
   */
  public static function getUserTokens($eid, $email) {
    $userTokens = [];
    $user = user_load_by_mail($email);
    //dpm ($user);
    if (!is_object($user)) {
      dpm("Creating user $email");
      $user = user::create();
      $user->setUsername($email);
      $user->setEmail($email);
      $user->activate();
      $result = $user->save();
      dpm($result);
    }
    //$userTokens['login_url'] = (string)login_one_time_button($user->user);
    $userTokens['[login_url]'] = user_pass_reset_url($user);
    return $userTokens;
  }
  
}
