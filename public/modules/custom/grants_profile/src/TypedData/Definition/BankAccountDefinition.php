<?php

namespace Drupal\grants_profile\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Define bank account data.
 */
class BankAccountDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['bank_account'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('bank_account')
      // ->addConstraint('NotBlank')
        ->addConstraint('ValidIban');

    }
    return $this->propertyDefinitions;
  }

}
