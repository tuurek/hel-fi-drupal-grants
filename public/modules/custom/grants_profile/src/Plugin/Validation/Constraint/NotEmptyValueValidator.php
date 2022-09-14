<?php

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validate empty values. Custom class to override default.
 */
class NotEmptyValueValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    // If (!$constraint instanceof NotEmptyValue) {
    // throw new UnexpectedTypeException($constraint, NotEmptyValue::class);
    // }
    //
    // if ($constraint->allowNull && null === $value) {
    // return;
    // }
    //
    // if (\is_string($value) && null !== $constraint->normalizer) {
    // $value = ($constraint->normalizer)($value);
    // }
    //
    // if (false === $value || (empty($value) && '0' != $value)) {
    // $this->context->buildViolation($constraint->message)
    // ->setParameter('{{ value }}', $this->formatValue($value))
    // ->setCode(NotEmptyValue::IS_BLANK_ERROR)
    // ->addViolation();
    // }
  }

}
