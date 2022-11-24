<?php

namespace Drupal\grants_handler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\grants_metadata\AtvSchema;
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
  public static array $eventTypes = [
    'STATUS_UPDATE' => 'STATUS_UPDATE',
    'MESSAGE_AVUS2' => 'MESSAGE_AVUS2',
    'MESSAGE_APP' => 'MESSAGE_APP',
    'MESSAGE_READ' => 'MESSAGE_READ',
    'HANDLER_ATT_OK' => 'HANDLER_ATT_OK',
    'INTEGRATION_INFO_ATT_OK' => 'INTEGRATION_INFO_ATT_OK',
    'INTEGRATION_INFO_APP_OK' => 'INTEGRATION_INFO_APP_OK',
    'EVENT_INFO' => 'EVENT_INFO',
    'HANDLER_ATT_DELETED' => 'HANDLER_ATT_DELETED',
  ];

  /**
   * Debug on?
   *
   * @var bool
   */
  protected bool $debug;

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

    $debug = getenv('debug');

    if ($debug == 'true') {
      $this->debug = TRUE;
    }
    else {
      $this->debug = FALSE;
    }

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
   * @param array $eventData
   *   If we have already built-up event data, use this.
   *
   * @return array|null
   *   EventID if success, otherways NULL
   *
   * @throws \Drupal\grants_handler\EventException
   */
  public function logEvent(
    string $applicationNumber,
    string $eventType,
    string $eventDescription,
    string $eventTarget,
    array $eventData = []
  ): ?array {

    if (empty($eventData)) {
      $eventData = self::getEventData($eventType, $applicationNumber, $eventDescription, $eventTarget);
    }

    $eventDataJson = Json::encode($eventData);

    if ($this->debug == TRUE) {
      $this->logger->debug(
        'Event ID: %eventId, JSON:  %json',
        [
          '%eventId' => $eventData['eventID'],
          '%json' => $eventDataJson,
        ]);
    }

    try {

      $res = $this->httpClient->post($this->endpoint, [
        'auth' => [$this->username, $this->password, "Basic"],
        'body' => $eventDataJson,
      ]);

      if ($res->getStatusCode() == 201) {
        $this->logger->info('Event logged: %eventId, message sent.', ['%eventId' => $eventData['eventID']]);
        return $eventData;
      }

    }
    catch (\Exception $e) {
      throw new EventException($e->getMessage());
    }

    return NULL;
  }

  /**
   * Filter events by given key.
   *
   * @param array $events
   *   Events to be filtered.
   * @param string $typeKey
   *   Event type wanted.
   *
   * @return array
   *   Filtered events.
   */
  public static function filterEvents(array $events, string $typeKey): array {
    $messageEvents = array_filter($events, function ($event) use ($typeKey) {
      if ($event['eventType'] == self::$eventTypes[$typeKey]) {
        return TRUE;
      }
      return FALSE;
    });

    return [
      'events' => $messageEvents,
      'event_targets' => array_column($messageEvents, 'eventTarget'),
      'event_ids' => array_column($messageEvents, 'eventID'),
    ];
  }

  /**
   * Build event object/array from given data.
   *
   * @param string $eventType
   *   Type of event, must be in self::$eventTypes.
   * @param string $applicationNumber
   *   Application number for event.
   * @param string $eventDescription
   *   Event description.
   * @param string $eventTarget
   *   Eent target.
   *
   * @return array
   *   Event data in array.
   *
   * @throws \Drupal\grants_handler\EventException
   */
  public static function getEventData(string $eventType, string $applicationNumber, string $eventDescription, string $eventTarget): array {
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

    $dt = new \DateTime();
    $dt->setTimezone(new \DateTimeZone('Europe/Helsinki'));

    $eventData['timeCreated'] = $eventData['timeUpdated'] = $dt->format('Y-m-d\TH:i:s');
    return $eventData;
  }

}
