<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is valid IBAN number.
 *
 * @Constraint(
 *   id = "ValidPostalCode",
 *   label = @Translation("Valid Finnish postak code", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidPostalCode extends Constraint {

  /**
   * The message that will be shown if the value is not unique.
   *
   * @var string
   */
  public string $notValidPostalCode = '%value is not valid Finnish postal code';

}
