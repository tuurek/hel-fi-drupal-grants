<?php

namespace Drupal\grants_attachments;

use GuzzleHttp\ClientInterface;

/**
 * AttachmentUploader service.
 */
class AttachmentUploader {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Constructs an AttachmentUploader object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * @param array $attachments
   * @param string $applicationNumber
   *
   * @return bool
   */
  public function uploadAttachments(array $attachments, string $applicationNumber): bool {

    return TRUE;

  }

}
