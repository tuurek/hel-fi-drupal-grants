<?php

namespace Drupal\grants_webform_print;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Webform share helper class.
 */
class GrantsWebformPrintHelper {

  /**
   * Determine if the current page is a webform share page.
   *
   * @return bool
   *   TRUE if the current page is a webform share page.
   */
  public static function isPage(RouteMatchInterface $route_match = NULL) {
    $route_match = $route_match ?: \Drupal::routeMatch();
    return (strpos($route_match->getRouteName(), 'entity.webform.print_page') === 0);
  }

}
