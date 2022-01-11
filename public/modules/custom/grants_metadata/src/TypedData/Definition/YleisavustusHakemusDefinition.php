<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

class YleisavustusHakemusDefinition extends ApplicationDefinitionBase {


  /**
   * Base data definitions for all
   *
   * @return array
   */
  public function getPropertyDefinitions(): array {
    $info = parent::getPropertyDefinitions();

    //    $info['subventions_type_6'] = DataDefinition::create('string')
    //      ->setRequired(TRUE)
    //      ->setLabel('subventionType')
    //      ->setSetting('jsonPath', [
    //        'compensation',
    //        'compensationInfo',
    //        'compensationArray',
    //        'subventionType'
    //      ])
    //      ->addConstraint('NotBlank');
    //
    //    $info['subventions_type_6_sum'] = DataDefinition::create('string')
    //      ->setRequired(TRUE)
    //      ->setLabel('amount')
    //      ->setSetting('jsonPath', [
    //        'compensation',
    //        'compensationInfo',
    //        'compensationArray',
    //        'amount'
    //      ])
    //      ->addConstraint('NotBlank');

    $info['subventions'] = ListDataDefinition::create('grants_metadata_compensation_type')
      ->setRequired(FALSE)
      ->setLabel('compensationArray')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'compensationArray',
      ])
    ;

    return $info;
  }

}