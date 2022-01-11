<?php

namespace Drupal\grants_metadata\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Address DataType.
 *
 * @DataType(
 * id = "grants_metadata_other_compensation",
 * label = @Translation("Other Compensation"),
 * definition_class = "\Drupal\grants_metadata\TypedData\Definition\OtherCompensationDefinition"
 * )
 */
class OtherCompensationData extends Map {



  public function getValue() {
    $retval = parent::getValue();

    if(isset($retval['issuerName'])){
      $retval['issuer_name'] = $retval['issuerName'];
      unset($retval['issuerName']);
    }

    return $retval;
  }

  /**
   * @return array
   */
  public function getValues(): array {
    return $this->values;
  }

}
