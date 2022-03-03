<?php

namespace Drupal\grants_attachments;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
use Drupal\views\Plugin\views\field\Boolean;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;

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
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $loggerChannel;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Debug prints?
   *
   * @var bool
   */
  protected bool $debug;


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
    $this->loggerChannel = $loggerFactory->get('grants_attachments');
    $this->connection = $connection;
  }

  /**
   * @return bool
   */
  public function isDebug(): bool {
    return $this->debug;
  }

  /**
   * @param bool $debug
   */
  public function setDebug(bool $debug): void {
    $this->debug = $debug;
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
    $this->setDebug($debug);

    $retval = [];
    foreach ($attachments as $fileId) {
      try {
        $file = File::load($fileId);
        $filename = $file->getFilename();

        // If for some reason the file entity does not wxist, do no more.
        if ($file === NULL) {
          continue;
        }
        // Get file metadata.
        $fileUri = $file->get('uri')->value;
        $filePath = \Drupal::service('file_system')->realpath($fileUri);

        // Get file data.
        $body = Utils::tryFopen($filePath, 'r');

        // Get response with built request.
        $response = $this->httpClient->request(
          'POST',
          // Liite endpoint.
          getenv('AVUSTUS2_LIITE_ENDPOINT'),
          [
            'headers' => [
              'X-Case-ID' => $applicationNumber,
            ],
            'auth' => [
              // Auth details.
              getenv('AVUSTUS2_USERNAME'),
              getenv('AVUSTUS2_PASSWORD'),
            ],
            // Form data.
            'multipart' => [
              [
                'name' => $file->getFilename(),
                'filename' => $file->getFilename(),
                'contents' => $body,
              ],
            ],
          ]
        );
        if ($response->getStatusCode() === $this->validStatusCode) {
          $retval[$fileId] = TRUE;
          if ($this->isDebug()) {
            $this->loggerChannel->notice('Grants attachment(@filename) upload succeeded: Response statusCode = @status', [
              '@status' => $response->getStatusCode(),
              '@filename' => $file->getFilename()
            ]);
          }

//          // Make sure that no rows remain for this FID.
//          $num_deleted = $this->connection->delete('grants_attachments')
//            ->condition('fid', $file->id())
//            ->execute();
//
//          if ($this->isDebug()) {
//            $this->loggerChannel->notice('Removed db log row: @filename', [
//              '@filename' => $filename
//            ]);
//          }
        }
        else {
          $retval[$fileId] = FALSE;
          $this->loggerChannel->error('Grants attachment upload failed: Response statusCode = @status', [
            '@status' => $response->getStatusCode(),
          ]);
        }
      }
      catch (\Exception $e) {
        $this->messenger->addError('Attachment upload failed:' . $file->getFilename());
        if ($this->isDebug()) {
          $this->messenger->addError(printf('Grants attachment upload failed: %s', [$e->getMessage()]));
        }
        $this->loggerChannel->error('Grants attachment upload failed: @error', [
          '@error' => $e->getMessage(),
        ]);
        $retval[$fileId] = FALSE;
      }
      catch (GuzzleException $e) {
        if ($this->isDebug()) {
          $this->messenger->addError('Attachment upload failed:' . $file->getFilename());
          $this->messenger->addError($e->getMessage());
        }
        $this->loggerChannel->error('Grants attachment upload failed: @error', [
          '@error' => $e->getMessage(),
        ]);
        $retval[$fileId] = FALSE;
      }
    }
    return $retval;
  }

}
