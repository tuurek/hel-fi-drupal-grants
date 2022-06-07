<?php

namespace Drupal\grants_handler;

use DateTime;
use DateTimeZone;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\grants_metadata\AtvSchema;
use Exception;
use GuzzleHttp\ClientInterface;
use Ramsey\Uuid\Uuid;

/**
 * Send event updates to documents via integration.
 */
class EventsService {

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
   * Event types that are supported.
   *
   * @var array|string[]
   */
  protected static array $eventTypes = [
    'STATUS_UPDATE' => 'STATUS_UPDATE',
    'MESSAGE_NEW' => 'MESSAGE_NEW',
    'MESSAGE_READ' => 'MESSAGE_READ',
    'ATTACHMENT_UPLOAD' => 'ATTACHMENT_UPLOAD',
    'DATA_UPDATE_ATV' => 'DATA_UPDATE_ATV',
    'DATA_UPDATE_AVUS2' => 'DATA_UPDATE_AVUS2',
  ];

  /**
   * Constructs a MessageService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Client to post data.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Log things.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactory $loggerFactory,
  ) {
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('grants_handler_events_service');

    $this->endpoint = getenv('AVUSTUS2_EVENT_ENDPOINT');
    $this->username = getenv('AVUSTUS2_USERNAME');
    $this->password = getenv('AVUSTUS2_PASSWORD');

  }

  /**
   * Log event to document via event integration.
   *
   * @param string $applicationNumber
   *   Application to be logged.
   * @param string $eventType
   *   Type of event, must be configured in this class as active one.
   * @param string $eventDescription
   *   Free message to be added.
   * @param string $eventTarget
   *   Target ID for event.
   *
   * @return string|null
   *   EventID if success, otherways NULL
   *
   * @throws \Drupal\grants_handler\EventException
   */
  public function logEvent(
    string $applicationNumber,
    string $eventType,
    string $eventDescription,
    string $eventTarget
  ): ?string {

    $eventData = [];

    if (!in_array($eventType, self::$eventTypes)) {
      throw new EventException('Not valid event type: ' . $eventType);
    }
    else {
      $eventData['eventType'] = $eventType;
    }

    $eventData['eventID'] = Uuid::uuid4()->toString();
    $eventData['caseId'] = $applicationNumber;
    $eventData['eventDescription'] = AtvSchema::sanitizeInput($eventDescription);
    $eventData['eventTarget'] = $eventTarget;

    if (!isset($eventData['eventSource'])) {
      $eventData['eventSource'] = getenv('EVENTS_SOURCE');
    }

    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('Europe/Helsinki'));

    $eventData['timeCreated'] = $eventData['timeUpdated'] = $dt->format('Y-m-d\TH:i:s');

    try {
      $res = $this->httpClient->post($this->endpoint, [
        'auth' => [$this->username, $this->password, "Basic"],
        'body' => Json::encode($eventData),
      ]);

      if ($res->getStatusCode() == 201) {
        $this->logger->info('Event logged:  ' . $eventData['eventID'] . ', message sent.');
        return $eventData['eventID'];
      }

    }
    catch (Exception $e) {
      throw new EventException($e->getMessage());
    }

    return NULL;
  }

  /**
   * Figure out from events which messages are unread.
   *
   * @param array $events
   *   Events from document.
   * @param array $messages
   *   Messages from document.
   */
  public static function unreadMessages(array $events, array $messages) {

  }

}
