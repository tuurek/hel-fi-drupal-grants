<?php

namespace Drupal\grants_metadata\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Attachment DataType.
 *
 * @DataType(
 * id = "grants_metadata_attachment",
 * label = @Translation("Attachment"),
 * definition_class = "\Drupal\grants_metadata\TypedData\Definition\GrantsAttachmentDefinition"
 * )
 */
class GrantsAttachmentData extends Map {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $retval = parent::getValue();

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
