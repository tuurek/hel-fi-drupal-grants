<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Data definition for compensations.
 */
class CompensationTypeDefinition extends ComplexDataDefinitionBase {

  /**
   * Data definition for different subventions.
   *
   *   "amountInLetters",
   *   "eventBegin",
   *   "eventEnd",
   *   "primaryArt",
   *   "purpose",
   *   "isFestival",
   *   "letterNumber",
   *   "letterDate",
   *   "supportTimeBegin",
   *   "supportTimeEnd",
   *   "studentName",
   *   "caretakerName",
   *   "caretakerAddress",
   *   "totalCosts"
   *
   * @return array
   *   Property definitions.
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {

      $info = &$this->propertyDefinitions;

      $info['subventionType'] = DataDefinition::create('string')
        ->setLabel('subventionType')
        ->setSetting('jsonPath', [
          'compensation',
          'compensationInfo',
          'compensationArray',
          'subventionType',
        ]);
      // ->setRequired(TRUE)
      // ->addConstraint('NotBlank')
      $info['amount'] = DataDefinition::create('float')
        ->setLabel('amount')
        ->setSetting('jsonPath', [
          'compensation',
          'compensationInfo',
          'compensationArray',
          'amount',
        ])
        ->setSetting('valueCallback', [
          '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
          'convertToFloat',
        ])
        ->setSetting('typeOverride', [
          'dataType' => 'string',
          'jsonType' => 'float',
        ])
        ->setSetting('defaultValue', 0)
        ->setRequired(TRUE)
        ->addConstraint('NotBlank');
    }
    // And here we will add later fields as well.
    return $this->propertyDefinitions;
  }

}
