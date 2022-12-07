<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidIban constraint.
 */
class ValidPostalCodeValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, $constraint) {
    if (!$this->isValidPostalCode($value)) {
      $this->context->addViolation($constraint->notValidPostalCode, ['%value' => $value]);
    }
  }

  /**
   * Validate postal code.
   *
   * @param string $value
   *   Postal code.
   *
   * @return bool
   *   Is postal code valid.
   */
  private function isValidPostalCode(string $value) {
    return (bool) preg_match("/^(FI-)?[0-9]{5}$/", $value);
  }

}
