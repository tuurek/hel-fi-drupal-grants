<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Custom validation constraint.
 *
 * @Constraint(
 *   id = "NotEmptyValue",
 *   label = @Translation("Not empty value", context = "Validation"),
 *   type = "string"
 * )
 */
class NotEmptyValue extends Constraint {

  /**
   * The message.
   *
   * @var string
   */
  public $message = 'This value should not be blank.';

}
