<?php

namespace Drupal\grants_attachments;

use Psr7\Utils;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Uploads attachments to backend.
 */
class AttachmentUploader {

  /**
   * Status code to test against for successful upload.
   *
   * @var int
   */
  protected int $validStatusCode = 201;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs an AttachmentUploader object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Print messages to user.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Print messages to log.
   * @param \Drupal\Core\Database\Connection $connection
   *   Interact with database.
   */
  public function __construct(ClientInterface $http_client,
                              MessengerInterface $messenger,
                              LoggerChannelFactory $loggerFactory,
                              Connection $connection) {
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
    $this->loggerFactory = $loggerFactory->get('grants_attachments');
    $this->connection = $connection;
  }

  /**
   * Upload every attachment from form to backend.
   *
   * @param array $attachments
   *   Array of file ids to upload.
   * @param string $applicationNumber
   *   Generated application number.
   * @param bool $debug
   *   Is debug mode on?
   *
   * @return bool[]
   *   Array keyed with FID and boolean to indicate if upload succeeded.
   */
  public function uploadAttachments(
    array $attachments,
    string $applicationNumber,
    bool $debug
  ): array {
    $retval = [];
    foreach ($attachments as $fileId) {
      $file = File::load($fileId);
      $fileUri = $file->get('uri')->value;
      $filePath = \Drupal::service('file_system')->realpath($fileUri);
      $body = Utils::tryFopen($filePath, 'r');

      try {
        $response = $this->httpClient->request(
          'POST',
          getenv('AVUSTUS2_LIITE_ENDPOINT'),
          [
            'body' => $body,
            'auth' => [
              getenv('AVUSTUS2_LIITE_USERNAME'),
              getenv('AVUSTUS2_LIITE_PASSWD'),
            ],
            'headers' => [
              'X-Case-ID' => $applicationNumber,
            ],
          ]);
        if ($response->getStatusCode() === $this->validStatusCode) {
          $retval[$fileId] = TRUE;
          $this->loggerFactory->notice('Grants attachment upload succeeded: Response statusCode = @status', [
            '@status' => $response->getStatusCode(),
          ]);
          // Make sure that no rows remain for this FID.
          $num_deleted = $this->connection->delete('grants_attachments')
            ->condition('fid', $file->id())
            ->execute();

          $this->loggerFactory->get('grants_attachments')
            ->notice('Removed file entity & db log row');
        }
        else {
          $retval[$fileId] = FALSE;
          $this->loggerFactory->error('Grants attachment upload failed: Response statusCode = @status', [
            '@status' => $response->getStatusCode(),
          ]);
        }
      }

      catch (GuzzleException $e) {
        $this->messenger->addError('Attachment upload failed:' . $file->getFilename());
        $this->loggerFactory->error('Grants attachment upload failed: @error', [
          '@error' => $e->getMessage(),
        ]);
        $retval[$fileId] = FALSE;
      }
    }
    return $retval;
  }

}
