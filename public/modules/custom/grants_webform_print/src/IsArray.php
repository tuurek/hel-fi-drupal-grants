<?php

namespace Drupal\grants_webform_print;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 *
 */
class IsArray extends AbstractExtension
{
  /**
   * @inheritdoc
   */
  public function getFilters(): array
  {
    return [
      new TwigFilter('isArray', [$this, 'isArray']),
    ];
  }

  /**
   * @inheritdoc
   */
  public function getFunctions(): array
  {
    return [
      new TwigFunction('isArray', [$this, 'isArray']),
    ];
  }

  /**
   * Twig function to test if something is an array or not.
   *
   * This is useful for checking if something has been eager loaded or just a db query
   * class that needs to be executed.
   *
   * @param mixed $variable
   * @return bool
   */
  public function isArray($variable): bool
  {
    return is_array($variable);
  }
}
