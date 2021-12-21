<?php

namespace Drupal\grants_profile\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Grants Profile routes.
 */
class GrantsProfileController extends ControllerBase {

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
