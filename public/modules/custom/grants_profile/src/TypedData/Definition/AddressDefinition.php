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
        ->addConstraint('NotBlank');

      $info['city'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('city')
        ->addConstraint('NotBlank');
      $info['post_code'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('Post code')
        ->addConstraint('NotBlank');
      $info['country'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('Country')
        ->addConstraint('NotBlank');

    }
    return $this->propertyDefinitions;
  }

}
