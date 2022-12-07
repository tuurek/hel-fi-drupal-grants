<?php

declare(strict_types=1);

namespace Drupal\grants_webform_print;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig filter to check if variable is an array or not.
 */
class IsVariableArray extends AbstractExtension {

  /**
   * Filter list.
   *
   * @return array
   *   Our filter.
   */
  public function getFilters(): array {
    return [
      new TwigFilter('isArray', [$this, 'isArray']),
    ];
  }

  /**
   * Get functions.
   *
   * @return array
   *   Out functions.
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('isArray', [$this, 'isArray']),
    ];
  }

  /**
   * Twig function to test if something is an array or not.
   *
   * This is useful for checking if something has been eager loaded or
   * just a db query class that needs to be executed.
   *
   * @param mixed $variable
   *   VAriable to test.
   *
   * @return bool
   *   True if array.
   */
  public function isArray($variable): bool {
    return is_array($variable);
  }

}
