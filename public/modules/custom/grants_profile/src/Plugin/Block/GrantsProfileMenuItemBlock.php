<?php

namespace Drupal\grants_profile\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
    $initials = 'NaN';
    if ($logged_in) {
      $current_user = \Drupal::currentUser();
      $name = $current_user->getDisplayName();
      $words = explode(' ', $name);
      if (count($words) >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
      }
      else {
        preg_match_all('#([A-Z]+)#', $name, $capitals);
        if (count($capitals[1]) >= 2) {
          $initials = substr(implode('', $capitals[1]), 0, 2);
        }
        else {
          $initials = strtoupper(substr($name, 0, 2));
        }

      }

    }

    $build['#theme'] = 'block__grants_profile_menuitem';
    $build['notifications'] = 1;
    $build['colorscheme'] = 0;
    $build['initials'] = $initials;
    $build['loggedin'] = $logged_in;
    return $build;
  }

}
