<?php

namespace Drupal\grants_attachments;

use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * AttachmentRemover service.
 */
class AttachmentRemover {

  /**
   * The file.usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Constructs an AttachmentRemover object.
   *
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file.usage service.
   */
  public function __construct(FileUsageInterface $file_usage) {
    $this->fileUsage = $file_usage;
  }

  /**
   * Method description.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeGrantAttachments(array $attachments): bool {
    foreach ($attachments as $fileId) {
      $file = File::load($fileId);
      $file->delete();
    }
    return TRUE;
  }

  public function purgeAllAttachments(){

  }

}
