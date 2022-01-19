<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drupal\grants_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 *
 */
class NotEmptyValueValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
      $d = 'adsf';
//        if (!$constraint instanceof NotEmptyValue) {
//            throw new UnexpectedTypeException($constraint, NotEmptyValue::class);
//        }
//
//        if ($constraint->allowNull && null === $value) {
//            return;
//        }
//
//        if (\is_string($value) && null !== $constraint->normalizer) {
//            $value = ($constraint->normalizer)($value);
//        }
//
//        if (false === $value || (empty($value) && '0' != $value)) {
//            $this->context->buildViolation($constraint->message)
//                ->setParameter('{{ value }}', $this->formatValue($value))
//                ->setCode(NotEmptyValue::IS_BLANK_ERROR)
//                ->addViolation();
//        }
    }
}
