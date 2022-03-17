<?php

namespace Drupal\grants_profile\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a menuitem block.
 *
 * @Block(
 *   id = "grants_profile_menuitem",
 *   admin_label = @Translation("Menu Items For Profile"),
 *   category = @Translation("Grants Profile")
 * )
 */
class GrantsProfileMenuItemBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $logged_in = \Drupal::currentUser()->isAuthenticated();
    if ($logged_in) {
      $build['content'] = [
        '#markup' => Link::fromTextAndUrl(t('Profile'), Url::fromUri('internal:/grants-profile'))->toString().
          Link::fromTextAndUrl(t('Logout'), Url::fromUri('internal:/user/logout'))->toString(),
      ];
    } else {
      $build['content'] = [
        '#markup' => Link::fromTextAndUrl(t('Login'), Url::fromUri('internal:/user/login'))->toString()
      ];
    }
    return $build;
  }

}
