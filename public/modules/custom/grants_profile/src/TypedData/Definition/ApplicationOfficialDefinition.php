<?php

namespace Drupal\grants_profile\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Define Application official data.
 */
class ApplicationOfficialDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['name'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('name')
        ->addConstraint('NotBlank');

      $info['role'] = DataDefinition::create('integer')
        ->setRequired(TRUE)
        ->setLabel('role')
        ->addConstraint('NotBlank');

      $info['email'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('email')
        ->addConstraint('NotBlank')
        ->addConstraint('Email');

      $info['phone'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('phone')
        ->addConstraint('NotBlank');

    }
    return $this->propertyDefinitions;
  }

}
