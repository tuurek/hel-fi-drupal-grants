<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is valid IBAN number.
 *
 * @Constraint(
 *   id = "ValidIban",
 *   label = @Translation("Valid Finnish IBAN number", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidIban extends Constraint {

  /**
   * The message that will be shown if the value is not unique.
   *
   * @var string
   */
  public string $notValidIban = '%value is not valid Finnish IBAN';

}
