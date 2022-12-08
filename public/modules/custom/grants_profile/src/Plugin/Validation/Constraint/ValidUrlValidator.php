<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidIban constraint.
 */
class ValidUrlValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, $constraint) {
    if (!$this->isValidUrl($value)) {
      $this->context->addViolation($constraint->notValidUrl, ['%value' => $value]);
    }
  }

  /**
   * Validate given url.
   *
   * @param string $value
   *   Url.
   *
   * @return bool
   *   Is url valid?
   */
  private function isValidUrl(string $value): bool {
    return UrlHelper::isValid($value, TRUE);
  }

}
