<?php

namespace Drupal\grants_metadata\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Address DataType.
 *
 * @DataType(
 * id = "grants_metadata_compensation_type",
 * label = @Translation("Compensation types"),
 * definition_class =
 *   "\Drupal\grants_metadata\TypedData\Definition\CompensationTypeDefinition"
 * )
 */
class CompensationTypeData extends Map {

  /**
   * {@inheritdoc}
   */
  public function getValue() {

    $retval = parent::getValue();

    return $retval;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyPath() {
    if (isset($this->parent)) {
      // The property path of this data object is the parent's path appended
      // by this object's name.
      $prefix = $this->parent->getPropertyPath();
      return (strlen($prefix) ? $prefix . '.' : '') . $this->name;
    }
    // If no parent is set, this is the root of the data tree. Thus the property
    // path equals the name of this data object.
    elseif (isset($this->name)) {
      return $this->name;
    }
    return '';
  }

}
