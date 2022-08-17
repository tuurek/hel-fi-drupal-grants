<?php

namespace Drupal\grants_oma_asiointi\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "grants_oma_asiointi_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("Oma Asiointi")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
