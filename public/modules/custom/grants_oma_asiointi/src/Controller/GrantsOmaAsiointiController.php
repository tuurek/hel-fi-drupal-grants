<?php

namespace Drupal\grants_oma_asiointi\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Oma Asiointi routes.
 */
class GrantsOmaAsiointiController extends ControllerBase {

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
