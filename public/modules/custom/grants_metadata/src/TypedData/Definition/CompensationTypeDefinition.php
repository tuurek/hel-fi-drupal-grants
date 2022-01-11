<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\DataDefinition;

class CompensationTypeDefinition extends ApplicationDefinitionBase {


  /**
   * Data definition for different subventions
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
   */
  public function getPropertyDefinitions(): array {
    $info = parent::getPropertyDefinitions();

    $info['subvention_type'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('subventionType')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'compensationArray',
        'subventionType'
      ])
      ->addConstraint('NotBlank');

    $info['subvention_amount'] = DataDefinition::create('float')
      ->setRequired(TRUE)
      ->setLabel('amount')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'compensationArray',
        'amount'
      ])
      ->addConstraint('NotBlank');

    // And here we will add later fields as well
    return $info;
  }

}