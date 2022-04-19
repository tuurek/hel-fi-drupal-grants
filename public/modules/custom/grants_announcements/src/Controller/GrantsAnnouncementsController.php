<?php

namespace Drupal\grants_announcements\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Grants Announcements routes.
 */
class GrantsAnnouncementsController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
