<?php

namespace Drupal\grants_handler;

use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactory;
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
   * Constructs a MessageService object.
   *
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata
   *   The helfi_helsinki_profiili.userdata service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Client to post data.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Log things.
   */
  public function __construct(
    HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata,
    ClientInterface          $http_client,
    LoggerChannelFactory     $loggerFactory,
  ) {
    $this->helfiHelsinkiProfiiliUserdata = $helfi_helsinki_profiili_userdata;
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('grants_handler_message_service');

    $this->endpoint = getenv('AVUSTUS2_MESSAGE_ENDPOINT');
    $this->username = getenv('AVUSTUS2_USERNAME');
    $this->password = getenv('AVUSTUS2_PASSWORD');

  }

  /**
   * Send message to backend.
   *
   * @param array $messageData
   *   Message content.
   * @param \Drupal\webform\Entity\WebformSubmission $submission
   *   Submission entity.
   * @param string $nextMessageId
   *   Next message id for logging.
   *
   * @return bool
   *   Return message status.
   */
  public function sendMessage(array $messageData, WebformSubmission $submission, string $nextMessageId): bool {

    $submissionData = $submission->getData();
    $userData = $this->helfiHelsinkiProfiiliUserdata->getUserData();


    if (isset($submissionData["application_number"]) && !empty($submissionData["application_number"])) {
      $messageData['caseId'] = $submissionData["application_number"];

      if ($userData === NULL) {
        $messageData['sentBy'] = 'Testi Käyttäjä';
      }
      else {
        $messageData['sentBy'] = $userData['name'];
      }

      $dt = new \DateTime();
      // $dt->setTimezone(new \DateTimeZone('Europe/Helsinki'));
      $dt->setTimezone(new \DateTimeZone('UTC'));

      $messageData['sendDateTime'] = $dt->format('Y-m-d\TH:i:s\.\0\0\0');
      //      $messageData['sendDateTime'] = $dt->format('Y-m-d\TH:i:s\.\0\0\0\Z');

      $res = $this->httpClient->post($this->endpoint, [
        'auth' => [$this->username, $this->password, "Basic"],
        'body' => Json::encode($messageData),
      ]);

      if ($res->getStatusCode() == 201) {
        $this->logger->info('MSG id: ' . $nextMessageId . ', message sent.');
        return TRUE;
      }
    }
    return FALSE;
  }

}
