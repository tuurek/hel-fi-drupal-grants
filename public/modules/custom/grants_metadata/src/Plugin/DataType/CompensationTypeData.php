<?php

namespace Drupal\grants_metadata\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Address DataType.
 *
 * @DataType(
 * id = "grants_metadata_compensation_type",
 * label = @Translation("Compensation types"),
 * definition_class = "\Drupal\grants_metadata\TypedData\Definition\CompensationTypeDefinition"
 * )
 */
class CompensationTypeData extends Map {


  public function getValue() {

    $retval = parent::getValue();

//    if(isset($retval['subventionType'])){
//      $retval['subvention_type']
//    }

    return $retval;
  }

}
