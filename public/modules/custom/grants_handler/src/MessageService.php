<?php

namespace Drupal\grants_handler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
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
  protected $logger;

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
    ClientInterface $http_client,
    LoggerChannelFactory $loggerFactory,
  ) {
    $this->helfiHelsinkiProfiiliUserdata = $helfi_helsinki_profiili_userdata;
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('grants_handler_message_service');

    $this->endpoint = getenv('AVUSTUS2_MESSAGE_ENDPOINT');
    $this->username = getenv('AVUSTUS2_USERNAME');
    $this->password = getenv('AVUSTUS2_PASSWORD');

  }

  /**
   * Method description.
   */
  public function sendMessage($messageData, $submission): bool {

    $submissionData = $submission->getData();
    $userData = $this->helfiHelsinkiProfiiliUserdata->getUserData();

    $dt = new \DateTime();
    $dt->setTimezone(new \DateTimeZone('UTC'));

    $messageData['caseId'] = $submissionData["application_number"];
    $messageData['sentBy'] = $userData['name'];
    $messageData['sendDateTime'] = $dt->format('Y-m-d\TH:i:s\.\0\0\0\Z');

    $res = $this->httpClient->post($this->endpoint, [
      'auth' => [$this->username, $this->password, "Basic"],
      'body' => Json::encode($messageData),
    ]);

    return TRUE;
  }

}
