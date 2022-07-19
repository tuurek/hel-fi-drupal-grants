<?php

namespace Drupal\grants_handler;

use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\grants_metadata\AtvSchema;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\webform\Entity\WebformSubmission;
use GuzzleHttp\ClientInterface;

/**
 * Handle message uploading and other things related.
 */
class MessageService {

  /**
   * The helfi_helsinki_profiili.userdata service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helfiHelsinkiProfiiliUserdata;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected LoggerChannelFactory|LoggerChannelInterface|LoggerChannel $logger;

  /**
   * Log events via integration.
   *
   * @var \Drupal\grants_handler\EventsService
   */
  protected EventsService $eventsService;

  /**
   * API endopoint.
   *
   * @var string
   */
  protected string $endpoint;

  /**
   * Api username.
   *
   * @var string
   */
  protected string $username;

  /**
   * Api password.
   *
   * @var string
   */
  protected string $password;

  /**
   * Print / log debug things.
   *
   * @var bool
   */
  protected bool $debug;

  /**
   * Atv access.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Constructs a MessageService object.
   *
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata
   *   The helfi_helsinki_profiili.userdata service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Client to post data.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Log things.
   * @param \Drupal\grants_handler\EventsService $eventsService
   *   Log events to atv document.
   * @param \Drupal\helfi_atv\AtvService $atvService
   *   Access to ATV.
   */
  public function __construct(
    HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata,
    ClientInterface $http_client,
    LoggerChannelFactory $loggerFactory,
    EventsService $eventsService,
    AtvService $atvService
  ) {
    $this->helfiHelsinkiProfiiliUserdata = $helfi_helsinki_profiili_userdata;
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('grants_handler_message_service');
    $this->eventsService = $eventsService;
    $this->atvService = $atvService;

    $this->endpoint = getenv('AVUSTUS2_MESSAGE_ENDPOINT');
    $this->username = getenv('AVUSTUS2_USERNAME');
    $this->password = getenv('AVUSTUS2_PASSWORD');

    $debug = getenv('debug');

    if ($debug == 'true') {
      $this->debug = TRUE;
    }
    else {
      $this->debug = FALSE;
    }

  }

  /**
   * Send message to backend.
   *
   * @param array $unSanitizedMessageData
   *   Message data to be sanitized & used.
   * @param \Drupal\webform\Entity\WebformSubmission $submission
   *   Submission entity.
   * @param string $nextMessageId
   *   Next message id for logging.
   *
   * @return bool
   *   Return message status.
   */
  public function sendMessage(array $unSanitizedMessageData, WebformSubmission $submission, string $nextMessageId): bool {

    $submissionData = $submission->getData();
    $userData = $this->helfiHelsinkiProfiiliUserdata->getUserData();

    // Make sure data from user is sanitized.
    $messageData = AtvSchema::sanitizeInput($unSanitizedMessageData);

    if (isset($submissionData["application_number"]) && !empty($submissionData["application_number"])) {
      $messageData['caseId'] = $submissionData["application_number"];

      if ($userData === NULL) {
        $messageData['sentBy'] = 'KORJAA PROFIILIYHTEYS';
      }
      else {
        $messageData['sentBy'] = $userData['name'];
      }

      $dt = new \DateTime();
      $dt->setTimezone(new \DateTimeZone('Europe/Helsinki'));
      $messageData['sendDateTime'] = $dt->format('Y-m-d\TH:i:s');

      $messageDataJson = Json::encode($messageData);

      $res = $this->httpClient->post($this->endpoint, [
        'auth' => [$this->username, $this->password, "Basic"],
        'body' => $messageDataJson,
      ]);

      if ($this->debug == TRUE) {
        $this->logger->debug('MSG id: ' . $nextMessageId . ', JSON: ' . $messageDataJson);
      }

      if ($res->getStatusCode() == 201) {
        try {
          $this->atvService->clearCache($messageData['caseId']);
          $eventId = $this->eventsService->logEvent(
            $submissionData["application_number"],
            'MESSAGE_APP',
            t('New message for @applicationNumber.',
              ['@applicationNumber' => $submissionData["application_number"]]
            ),
            $nextMessageId
          );

          $this->logger->info('MSG id: ' . $nextMessageId . ', message sent. Event logged: ' . $eventId);

        }
        catch (EventException $e) {
          // Log event error.
          $this->logger->error($e->getMessage());
        }

        return TRUE;
      }

    }
    return FALSE;
  }

}
