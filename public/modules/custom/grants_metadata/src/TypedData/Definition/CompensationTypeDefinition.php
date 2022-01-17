<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Data definition for compensations.
 */
class CompensationTypeDefinition extends ApplicationDefinitionBase {

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
    $info = parent::getPropertyDefinitions();

    $info['subventionType'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('subventionType')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'compensationArray',
        'subventionType',
      ])
      ->addConstraint('NotBlank');

    $info['amount'] = DataDefinition::create('float')
      ->setRequired(TRUE)
      ->setLabel('amount')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'compensationArray',
        'amount',
      ])
      ->addConstraint('NotBlank');

    // And here we will add later fields as well.
    return $info;
  }

}
