<?php

namespace Drupal\grants_profile\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

/**
 * Define address data.
 */
class GrantsProfileDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;


      $info['officials'] = ListDataDefinition::create('grants_profile_application_official')
        ->setRequired(TRUE)
        ->setSetting('jsonPath', ['grantsProfile', 'officialsArray'])
        ->setLabel('Officials');

      $info['addresses'] = ListDataDefinition::create('grants_profile_address')
        ->setRequired(TRUE)
        ->setSetting('jsonPath', ['grantsProfile', 'addressesArray'])
        ->setLabel('Addresses');

      $info['bankAccounts'] = ListDataDefinition::create('grants_profile_bank_account')
        ->setRequired(TRUE)
        ->setSetting('jsonPath', ['grantsProfile', 'bankAccountsArray'])
        ->setLabel('Bank Accounts');

    }
    return $this->propertyDefinitions;
  }

}
