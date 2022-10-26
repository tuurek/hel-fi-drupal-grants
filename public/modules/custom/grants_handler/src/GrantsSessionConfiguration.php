<?php

namespace Drupal\grants_handler;

use Drupal\Core\Session\SessionConfiguration;
use Symfony\Component\HttpFoundation\Request;

/**
 * Configure session length.
 */
class GrantsSessionConfiguration extends SessionConfiguration {

  /**
   * {@inheritdoc}
   */
  public function getOptions(Request $request) {
    $options = parent::getOptions($request);

    // Allows the cookie to be destroyed when closing browser.
    $options['cookie_lifetime'] = 0;

    // Put the session available for the Garbage Collector.
    $options['gc_maxlifetime'] = 3600;

    return $options;
  }

}
