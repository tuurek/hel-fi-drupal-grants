<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is valid IBAN number.
 *
 * @Constraint(
 *   id = "ValidUrl",
 *   label = @Translation("Valid URL", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidUrl extends Constraint {

  /**
   * The message that will be shown if the value is not unique.
   *
   * @var string
   */
  public string $notValidUrl = '%value is not valid url';

}
