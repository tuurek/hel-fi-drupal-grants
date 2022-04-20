<?php

namespace Drupal\grants_announcements\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "grants_announcements_listing_block",
 *   admin_label = @Translation("Grants Annoucements Listing Block"),
 *   category = @Translation("Grants Announcements")
 * )
 */
class AnnoucementListBlock extends BlockBase {

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
