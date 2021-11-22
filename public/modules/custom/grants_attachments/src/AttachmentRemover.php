<?php

namespace Drupal\grants_attachments;

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
   */
  public function removeGrantAttachments(): bool {

    return TRUE;
  }

}
