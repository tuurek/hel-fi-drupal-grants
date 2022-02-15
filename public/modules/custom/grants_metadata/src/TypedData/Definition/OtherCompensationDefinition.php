<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Define Application official data.
 */
class OtherCompensationDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['issuer'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
        ->setLabel('issuer')
        ->setSetting('jsonPath', [
          'compensation',
          'otherCompensationsInfo',
          'otherCompensationsArray',
          'issuer',
        ]);
      // ->addConstraint('NotBlank')
      $info['issuerName'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
        ->setLabel('issuerName')
        ->setSetting('jsonPath', [
          'compensation',
          'otherCompensationsInfo',
          'otherCompensationsArray',
          'issuerName',
        ]);
      // ->addConstraint('NotBlank')
      $info['year'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
        ->setLabel('year')
        ->setSetting('jsonPath', [
          'compensation',
          'otherCompensationsInfo',
          'otherCompensationsArray',
          'year',
        ]);
      // ->addConstraint('NotBlank')
      $info['amount'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('amount')
        ->setSetting('jsonPath', [
          'compensation',
          'otherCompensationsInfo',
          'otherCompensationsArray',
          'amount',
        ]);
      // ->addConstraint('NotBlank')
      $info['purpose'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('purpose')
        ->setSetting('jsonPath', [
          'compensation',
          'otherCompensationsInfo',
          'otherCompensationsArray',
          'purpose',
        ]);
      // ->addConstraint('NotBlank')
    }
    return $this->propertyDefinitions;
  }

}
