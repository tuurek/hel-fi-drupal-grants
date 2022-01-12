<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\ListDataDefinition;

/**
 * Define Yleisavustushakemus data.
 */
class YleisavustusHakemusDefinition extends ApplicationDefinitionBase {

  /**
   * Base data definitions for all.
   *
   * @return array
   *   Property definitions.
   */
  public function getPropertyDefinitions(): array {
    $info = parent::getPropertyDefinitions();

    $info['subventions'] = ListDataDefinition::create('grants_metadata_compensation_type')
      ->setRequired(FALSE)
      ->setLabel('compensationArray')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'compensationArray',
      ]);

    return $info;
  }

}
