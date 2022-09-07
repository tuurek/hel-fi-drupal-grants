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

  public function setValue($values, $notify = TRUE) {

    /**
     * Make sure if we have integrationID, the new attachemnt is set to false.
     *
     * This is because integration does not update new attachment value in content even if they do update other values.
     *
     * If this is true, integration waits for another file to arrive, and empties ALL other attachments.
     *
     */
    if (isset($values["integrationID"]) && $values["integrationID"] != '') {
      $values["isNewAttachment"] = FALSE;
    }

    parent::setValue($values, $notify);

  }

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
