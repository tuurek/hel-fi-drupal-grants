<?php

namespace Drupal\grants_profile\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Define address data.
 */
class AddressDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['street'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('street')
        ->setSetting('jsonPath', ['grantsProfile', 'addressesArray', 'street'])
        ->addConstraint('NotBlank');

      $info['city'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('city')
        ->setSetting('jsonPath', ['grantsProfile', 'addressesArray', 'city'])
        ->addConstraint('NotBlank');

      $info['postCode'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('Post code')
        ->setSetting('jsonPath', ['grantsProfile', 'addressesArray', 'postCode'])
        ->addConstraint('NotBlank');

      $info['country'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('Country')
        ->setSetting('jsonPath', ['grantsProfile', 'addressesArray', 'country'])
        ->addConstraint('NotBlank');

    }
    return $this->propertyDefinitions;
  }

}
