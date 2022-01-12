<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Define Application official data.
 */
class GrantsAttachmentDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['description'] = DataDefinition::create('string')
        ->setLabel('description')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'description',
        ]);

      $info['filename'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('fileName')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'fileName',
        ]);

      $info['filetype'] = DataDefinition::create('integer')
        ->setRequired(TRUE)
        ->setLabel('fileType')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'fileType',
        ]);

      $info['integration_id'] = DataDefinition::create('string')
        ->setRequired(FALSE)
        ->setLabel('integrationID')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'integrationID',
        ]);

      $info['is_delivered_later'] = DataDefinition::create('boolean')
        ->setRequired(TRUE)
        ->setLabel('isDeliveredLater')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'isDeliveredLater',
        ]);

      $info['is_included_in_other_file'] = DataDefinition::create('boolean')
        ->setRequired(TRUE)
        ->setLabel('isIncludedInOtherFile')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'isIncludedInOtherFile',
        ]);

      $info['is_new_attachment'] = DataDefinition::create('boolean')
        ->setRequired(TRUE)
        ->setLabel('isNewAttachment')
        ->setSetting('jsonPath', [
          'attachmentsInfo',
          'attachmentsArray',
          'isNewAttachment',
        ]);

    }
    return $this->propertyDefinitions;
  }

}
