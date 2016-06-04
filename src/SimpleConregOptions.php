<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregOptions.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregOptions {

  /**
   * Display simple thank you page.
   */
  public function communicationsMethod() {
    return ['E' => t('Electronic only'),
            'P' => t('Paper only'),
            'B' => t('Both electronic and paper')];
  }

}