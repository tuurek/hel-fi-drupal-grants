<?php

namespace Drupal\grants_attachments;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
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
  public function __construct(
    ClientInterface $http_client,
                              MessengerInterface $messenger,
                              LoggerChannelFactory $loggerFactory,
                              Connection $connection) {
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
    $this->loggerChannel = $loggerFactory->get('grants_attachments');
    $this->connection = $connection;

    $this->debug = FALSE;

  }

  /**
   * If debug is on or not.
   *
   * @return bool
   *   TRue or false depending on if debug is on or not.
   */
  public function isDebug(): bool {
    return $this->debug;
  }

  /**
   * Set debug.
   *
   * @param bool $debug
   *   True or false.
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
   * @param string $mode
   *   Mode to run in, "application" will function as was.
   *
   * @return array[]
   *   Array keyed with FID and boolean to indicate if upload succeeded.
   */
  public function uploadAttachments(
    array $attachments,
    string $applicationNumber,
    string $mode = 'application'
  ): array {

    if (empty($attachments)) {
      // If no new files are uploaded,
      // just skip everything and return empty array.
      return [];
    }

    if ($mode !== 'application') {
      $endpoint = getenv('AVUSTUS2_MESSAGE_LIITE_ENDPOINT');
      $auth = [
        // Auth details.
        getenv('AVUSTUS2_USERNAME'),
        getenv('AVUSTUS2_PASSWORD'),
      ];
      $headers = [
        'X-Case-ID' => $applicationNumber,
        'X-Message-ID' => $mode,
      ];
    }
    else {
      $endpoint = getenv('AVUSTUS2_LIITE_ENDPOINT');
      $auth = [
        // Auth details.
        getenv('AVUSTUS2_USERNAME'),
        getenv('AVUSTUS2_PASSWORD'),
      ];
      $headers = [
        'X-Case-ID' => $applicationNumber,
      ];
    }

    $retval = [];
    foreach ($attachments as $fileId) {
      try {
        $file = File::load($fileId);

        // If for some reason the file entity does not wxist, do no more.
        if ($file === NULL) {
          continue;
        }

        $filename = $file->getFilename();

        // Get file metadata.
        $fileUri = $file->get('uri')->value;
        $filePath = \Drupal::service('file_system')->realpath($fileUri);

        // Get file data.
        $body = Utils::tryFopen($filePath, 'r');

        // Get response with built request.
        $response = $this->httpClient->request(
          'POST',
          // Liite endpoint.
          $endpoint,
          [
            'headers' => $headers,
            'auth' => $auth,
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
          $retval[$fileId] = [
            'upload' => TRUE,
            'status' => $response->getStatusCode(),
            'filename' => $file->getFilename(),
          ];
          if ($this->isDebug()) {
            $this->loggerChannel->notice('Grants attachment(@filename) upload succeeded: Response statusCode = @status', [
              '@status' => $response->getStatusCode(),
              '@filename' => $file->getFilename(),
            ]);
          }

          // Make sure that no rows remain for this FID.
          $num_deleted = $this->connection->delete('grants_attachments')
            ->condition('fid', $file->id())
            ->execute();
          if ($this->isDebug()) {
            $this->loggerChannel->notice('Removed db log row: @filename', [
              '@filename' => $filename,
            ]);
          }
        }
        else {
          $retval[$fileId] = [
            'upload' => FALSE,
            'status' => $response->getStatusCode(),
            'filename' => $file->getFilename(),
            'msg' => '',
          ];
          $this->loggerChannel->error('Grants attachment upload failed: Response statusCode = @status', [
            '@status' => $response->getStatusCode(),
          ]);
        }
      }
      catch (\Exception $e) {
        $this->messenger->addError('Attachment upload failed:' . $file->getFilename());
        if ($this->isDebug()) {
          $this->messenger->addError(printf('Grants attachment upload failed: %s', $e->getMessage()));
        }
        $this->loggerChannel->error('Grants attachment upload failed: @error', [
          '@error' => $e->getMessage(),
        ]);
        $retval[$fileId] = [
          'upload' => FALSE,
          'status' => $e->getCode(),
          'filename' => $file->getFilename(),
          'msg' => $e->getMessage(),
        ];
      }
      catch (GuzzleException $e) {
        if ($this->isDebug()) {
          $this->messenger->addError('Attachment upload failed:' . $file->getFilename());
          $this->messenger->addError($e->getMessage());
        }
        $this->loggerChannel->error('Grants attachment upload failed: @error', [
          '@error' => $e->getMessage(),
        ]);
        $retval[$fileId] = [
          'upload' => FALSE,
          'status' => $e->getCode(),
          'filename' => '',
          'msg' => $e->getMessage(),
        ];
      }
    }
    return $retval;
  }

}
