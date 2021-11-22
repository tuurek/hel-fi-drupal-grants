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
   * Method description.
   */
  public function uploadAttachments() {

    return TRUE;

  }

}
