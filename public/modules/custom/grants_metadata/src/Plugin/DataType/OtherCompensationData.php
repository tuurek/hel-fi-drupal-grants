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

  use DataFormatTrait;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $retval = parent::getValue();

    if (isset($retval['issuerName'])) {
      $retval['issuer_name'] = $retval['issuerName'];
    }
    if (isset($retval['issuer_name'])) {
      $retval['issuerName'] = $retval['issuer_name'];
    }

    return $retval;
  }

  /**
   * Get values from parent.
   *
   * @return array
   *   The values.
   */
  public function getValues(): array {
    return $this->values;
  }

}
