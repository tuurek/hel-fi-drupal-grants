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
        ->setLabel('Nimi')
        ->setSetting('jsonPath', ['grantsProfile', 'officialsArray', 'name'])
        ->addConstraint('NotBlank');

      $info['role'] = DataDefinition::create('integer')
        ->setRequired(TRUE)
        ->setLabel('Rooli')
        ->setSetting('jsonPath', ['grantsProfile', 'officialsArray', 'role'])
        ->addConstraint('NotBlank');

      $info['email'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('Sähköposti')
        ->setSetting('jsonPath', ['grantsProfile', 'officialsArray', 'email'])
        ->addConstraint('NotBlank')
        ->addConstraint('Email');

      $info['phone'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('Puhelinnumero')
        ->setSetting('jsonPath', ['grantsProfile', 'officialsArray', 'phone'])
        ->addConstraint('NotBlank');

    }
    return $this->propertyDefinitions;
  }

}
