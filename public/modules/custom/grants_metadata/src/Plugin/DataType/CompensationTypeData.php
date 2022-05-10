<?php

namespace Drupal\grants_metadata\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler;

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

    // Update the values and return them.
    foreach ($this->properties as $name => $property) {
      $definition = $property->getDataDefinition();
      if (!$definition->isComputed()) {
        $value = $property->getValue();
        // Only write NULL values if the whole map is not NULL.
        if (isset($this->values) || isset($value)) {
          $this->values[$name] = $value;
        }
      }
    }
    // We need to make sure amount is valid float to get validation working.
    if (isset($this->values['amount'])) {
      $this->values['amount'] = GrantsHandler::convertToFloat($this->values['amount']);
    }

    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyPath() {
    if (isset($this->parent)) {
      // The property path of this data object is the parent's path appended
      // by this object's name.
      $prefix = $this->parent->getPropertyPath();
      return ((is_string($prefix) && strlen($prefix)) ? $prefix . '.' : '') . $this->name;
    }
    // If no parent is set, this is the root of the data tree. Thus the property
    // path equals the name of this data object.
    elseif (isset($this->name)) {
      return $this->name;
    }
    return '';
  }

}
