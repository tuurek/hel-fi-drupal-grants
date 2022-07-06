<?php

namespace Drupal\grants_handler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvDocument;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * ApplicationUploader service.
 */
class ApplicationHandler {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The helfi_helsinki_profiili.userdata service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helfiHelsinkiProfiiliUserdata;

  /**
   * Atv access.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Atv data mapper.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * Grants profile access.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Holds document fetched from ATV for checks.
   *
   * @var \Drupal\helfi_atv\AtvDocument
   */
  protected AtvDocument $atvDocument;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * Show messages messages.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Handle events with applications.
   *
   * @var \Drupal\grants_handler\EventsService
   */
  protected EventsService $eventsService;

  /**
   * Holds application statuses in.
   *
   * @var string[]
   */
  public static array $applicationStatuses = [
    'DRAFT' => 'DRAFT',
    'SENT' => 'SENT',
    // => Vastaanotettu
    'SUBMITTED' => 'SUBMITTED',
    // => Vastaanotettu
    'RECEIVED' => 'RECEIVED',
    'PENDING' => 'PENDING',
    // => Käsittelyssä
    'PROCESSING' => 'PROCESSING',
    // => Valmis
    'READY' => 'READY',
    // => Valmis
    'DONE' => 'DONE',
    'REJECTED' => 'REJECTED',
    'DELETED' => 'DELETED',
    'CANCELED' => 'CANCELED',
    'CANCELLED' => 'CANCELLED',
    'CLOSED' => 'CLOSED',
  ];

  /**
   * Application type codes & their translations.
   *
   * Array key is name of the form as is set to third party information.
   * That contains strings for every language code.
   *
   * @var string[][]
   */
  public static array $applicationTypes = [
    'ECONOMICGRANTAPPLICATION' => [
      'code' => 'YLEIS',
      'fi' => 'Yleisavustushakemus',
      'en' => 'EN Yleisavustushakemus',
      'sv' => 'SV Yleisavustushakemus',
      'ru' => 'RU Yleisavustushakemus',
    ],
  ];

  /**
   * Debug status.
   *
   * @var bool
   */
  protected bool $debug;

  /**
   * Endpoint used for integration.
   *
   * @var string
   */
  protected string $endpoint;

  /**
   * Username for REST endpoint.
   *
   * @var string
   */
  protected string $username;

  /**
   * Password for endpoint.
   *
   * @var string
   */
  protected string $password;

  /**
   * New status header text for integration.
   *
   * @var string
   */
  protected string $newStatusHeader;

  /**
   * Constructs an ApplicationUploader object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata
   *   The helfi_helsinki_profiili.userdata service.
   * @param \Drupal\helfi_atv\AtvService $atvService
   *   Access to ATV.
   * @param \Drupal\grants_metadata\AtvSchema $atvSchema
   *   ATV schema mapper.
   * @param \Drupal\grants_profile\GrantsProfileService $grantsProfileService
   *   Access grants profile data.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerChannelFactory
   *   Logger.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger.
   * @param \Drupal\grants_handler\EventsService $eventsService
   *   Access to events.
   */
  public function __construct(
    ClientInterface $http_client,
    HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata,
    AtvService $atvService,
    AtvSchema $atvSchema,
    GrantsProfileService $grantsProfileService,
    LoggerChannelFactory $loggerChannelFactory,
    Messenger $messenger,
    EventsService $eventsService
  ) {

    $this->httpClient = $http_client;
    $this->helfiHelsinkiProfiiliUserdata = $helfi_helsinki_profiili_userdata;
    $this->atvService = $atvService;
    $this->atvSchema = $atvSchema;
    $this->grantsProfileService = $grantsProfileService;

    $this->atvSchema->setSchema(getenv('ATV_SCHEMA_PATH'));

    $this->messenger = $messenger;
    $this->logger = $loggerChannelFactory->get('grants_application_handler');
    $this->eventsService = $eventsService;

    $this->endpoint = getenv('AVUSTUS2_ENDPOINT');
    $this->username = getenv('AVUSTUS2_USERNAME');
    $this->password = getenv('AVUSTUS2_PASSWORD');

    $this->newStatusHeader = '';

  }

  /*
   * Static methods
   */

  /**
   * Check if given submission status can be set to SUBMITTED.
   *
   * Ie, will submission be sent to Avus2 by integration. Currently only DRAFT
   * -> SUBMITTED is allowed for end user.
   *
   * @param \Drupal\webform\Entity\WebformSubmission|null $submission
   *   Submission in question.
   * @param string|null $status
   *   If no object is available, do text comparison.
   *
   * @return bool
   *   Is submission editable?
   */
  public static function canSubmissionBeSubmitted(?WebformSubmission $submission, ?string $status): bool {
    if (NULL === $submission) {
      $submissionStatus = $status;
    }
    else {
      $data = $submission->getData();
      $submissionStatus = $data['status'];
    }

    if (in_array($submissionStatus, [
      'DRAFT',
    ])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check if given submission is allowed to be edited.
   *
   * @param \Drupal\webform\Entity\WebformSubmission|null $submission
   *   Submission in question.
   * @param string|null $status
   *   If no object is available, do text comparison.
   *
   * @return bool
   *   Is submission editable?
   */
  public static function isSubmissionEditable(?WebformSubmission $submission, ?string $status): bool {
    if (NULL === $submission) {
      $submissionStatus = $status;
    }
    else {
      $data = $submission->getData();
      $submissionStatus = $data['status'];
    }

    if (in_array($submissionStatus, [
      self::$applicationStatuses['DRAFT'],
      self::$applicationStatuses['SUBMITTED'],
      self::$applicationStatuses['SENT'],
      self::$applicationStatuses['RECEIVED'],
    ])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Figure out status for new or updated application submission.
   *
   * @param string $triggeringElement
   *   Element clicked.
   * @param array $form
   *   Form specs.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State of form.
   * @param array $submittedFormData
   *   Submitted data.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Submission object.
   *
   * @return string
   *   Status for application, unchanged if no specific update done.
   */
  public function getNewStatus(
    string $triggeringElement,
    array $form,
    FormStateInterface $form_state,
    array $submittedFormData,
    WebformSubmissionInterface $webform_submission
  ): string {

    if ($triggeringElement == '::submitForm') {
      return ApplicationHandler::$applicationStatuses['DRAFT'];
    }

    if ($triggeringElement == '::submit') {
      // Try to update status only if it's allowed.
      if (self::canSubmissionBeSubmitted($webform_submission, NULL)) {
        if (
          $submittedFormData['status'] == 'DRAFT' ||
          !isset($submittedFormData['status']) ||
          $submittedFormData['status'] == '') {
          // If old status is draft or it's not set, we'll update status in
          // document with HEADER as well.
          $this->newStatusHeader = ApplicationHandler::$applicationStatuses['SUBMITTED'];
        }

        return ApplicationHandler::$applicationStatuses['SUBMITTED'];
      }
    }

    // If no other status determined, return existing one without changing.
    // submission should ALWAYS have status set if it's something else
    // than DRAFT.
    return $submittedFormData['status'] ?? self::$applicationStatuses['DRAFT'];
  }

  /**
   * Check if given submission is allowed to be messaged.
   *
   * @param \Drupal\webform\Entity\WebformSubmission|null $submission
   *   Submission in question.
   * @param string|null $status
   *   If no object is available, do text comparison.
   *
   * @return bool
   *   Is submission editable?
   */
  public static function isSubmissionMessageable(?WebformSubmission $submission, ?string $status): bool {

    if (NULL === $submission) {
      $submissionStatus = $status;
    }
    else {
      $data = $submission->getData();
      $submissionStatus = $data['status'];
    }

    if (in_array($submissionStatus, [
      self::$applicationStatuses['DRAFT'],
      self::$applicationStatuses['SUBMITTED'],
      self::$applicationStatuses['SENT'],
      self::$applicationStatuses['RECEIVED'],
      self::$applicationStatuses['PENDING'],
      self::$applicationStatuses['PROCESSING'],
    ])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Return Application environment shortcode.
   *
   * @return string
   *   Shortcode from current environment.
   */
  public static function getAppEnv(): string {
    $appEnv = getenv('APP_ENV');

    if ($appEnv == 'development') {
      $appParam = 'DEV';
    }
    else {
      if ($appEnv == 'production') {
        $appParam = 'PROD';
      }
      else {
        if ($appEnv == 'testing') {
          $appParam = 'TEST';
        }
        else {
          if ($appEnv == 'staging') {
            $appParam = 'STAGE';
          }
          else {
            $appParam = 'LOCAL';
          }
        }
      }
    }
    return $appParam;
  }

  /**
   * Generate application number from submission id.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $submission
   *   Webform data.
   *
   * @return string
   *   Generated number.
   */
  public static function createApplicationNumber(WebformSubmission $submission): string {

    $appParam = self::getAppEnv();

    $serial = $submission->serial();

    $applicationType = $submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');

    $typeCode = self::$applicationTypes[$applicationType]['code'] ?? '';

    return 'GRANTS-' . $appParam . '-' . $typeCode . '-' . sprintf('%08d', $serial);
  }

  /**
   * Extract serial numbor from application number string.
   *
   * @param string $applicationNumber
   *   Application number.
   *
   * @return string
   *   Webform submission serial.
   */
  public static function getSerialFromApplicationNumber(string $applicationNumber): string {
    $exploded = explode('-', $applicationNumber);
    $number = end($exploded);
    return ltrim($number, '0');
  }

  /**
   * Get submission object from local database & fill form data from ATV.
   *
   * Or if local submission is not found, create new and set data.
   *
   * @param string $applicationNumber
   *   String to try and parse submission id from. Ie GRANTS-DEV-00000098.
   * @param \Drupal\helfi_atv\AtvDocument|null $document
   *   Document to extract values from.
   *
   * @return \Drupal\webform\Entity\WebformSubmission|null
   *   Webform submission.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   */
  public static function submissionObjectFromApplicationNumber(
    string $applicationNumber,
    AtvDocument $document = NULL,
    bool $refetch = TRUE
  ): ?WebformSubmission {

    $submissionSerial = self::getSerialFromApplicationNumber($applicationNumber);

    $result = \Drupal::entityTypeManager()
      ->getStorage('webform_submission')
      ->loadByProperties([
        'serial' => $submissionSerial,
      ]);

    /** @var \Drupal\helfi_atv\AtvService $atvService */
    $atvService = \Drupal::service('helfi_atv.atv_service');

    /** @var \Drupal\grants_metadata\AtvSchema $atvSchema */
    $atvSchema = \Drupal::service('grants_metadata.atv_schema');

    if ($document == NULL) {
      try {
        $document = $atvService->searchDocuments(
          [
            'transaction_id' => $applicationNumber,
          ],
          $refetch
        );
        /** @var \Drupal\helfi_atv\AtvDocument $document */
        $document = reset($document);

      }
      catch (TempStoreException | AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
        return NULL;
      }
    }

    // If there's no local submission with given serial
    // we can actually create that object on the fly and use that for editing.
    if (empty($result)) {
      throw new AtvDocumentNotFoundException('Submission not found.');
    }
    else {
      $submissionObject = reset($result);

      $sData = $atvSchema->documentContentToTypedData(
        $document->getContent(),
        YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus')
      );

      $sData['messages'] = self::parseMessages($sData);

      // Set submission data from parsed mapper.
      $submissionObject->setData($sData);

      return $submissionObject;
    }
  }

  /**
   * Atv document holding this application.
   *
   * @param string $transactionId
   *   Id of the transaction.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   FEtched document.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function getAtvDocument(string $transactionId): AtvDocument {

    if (!isset($this->atvDocument)) {
      $res = $this->atvService->searchDocuments([
        'transaction_id' => $transactionId,
      ]);
      $this->atvDocument = reset($res);
    }

    return $this->atvDocument;
  }

  /**
   * Get typed data object for webform data.
   *
   * @param array $submittedFormData
   *   Form data.
   * @param string $definitionClass
   *   Class name of the definition class.
   * @param string $definitionKey
   *   Name of the definition.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   Typed data with values set.
   */
  public function webformToTypedData(
    array $submittedFormData,
    string $definitionClass,
    string $definitionKey
  ): TypedDataInterface {

    $dataDefinition = $definitionClass::create($definitionKey);

    $typeManager = $dataDefinition->getTypedDataManager();
    $applicationData = $typeManager->create($dataDefinition);

    $applicationData->setValue($submittedFormData);

    return $applicationData;
  }

  /**
   * Validate application data so that it is correct for saving to AVUS2.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $applicationData
   *   Typed data object.
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state object.
   * @param \Drupal\webform\Entity\WebformSubmission $webform_submission
   *   Submission object.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   Constraint violation object.
   */
  public function validateApplication(
    TypedDataInterface $applicationData,
    array &$form,
    FormStateInterface &$formState,
    WebformSubmission $webform_submission
  ): ConstraintViolationListInterface {

    $violations = $applicationData->validate();
    $webform = $webform_submission->getWebform();

    $appProps = $applicationData->getProperties();

    $erroredItems = [];

    if ($violations->count() > 0) {
      foreach ($violations as $violation) {
        $propertyPath = $violation->getPropertyPath();
        $root = $violation->getRoot();
        $cause = $violation->getCause();
        $constraint = $violation->getConstraint();
        $code = $violation->getCode();

        $thisProperty = $appProps[$propertyPath];
        // $webformElement = $webformElements[$propertyPath];
        $thisDefinition = $thisProperty->getDataDefinition();
        $label = $thisDefinition->getLabel();
        $thisDefinitionSettings = $thisDefinition->getSettings();
        $parent = $thisProperty->getParent();
        $message = $violation->getMessage();

        // formErrorElement setting controls what element on form errors
        // if data validation fails.
        if (isset($thisDefinitionSettings['formSettings']['formElement'])) {
          // Set property path to one defined in settings.
          $propertyPath = $thisDefinitionSettings['formSettings']['formElement'];
          // If not added already.
          if (!in_array($propertyPath, $erroredItems)) {
            $errorMsg = $thisDefinitionSettings['formSettings']['formError'] ?? $violation->getMessage();

            // Set message.
            $message = t(
              '@label: @msg',
              [
                '@label' => $label,
                '@msg' => $errorMsg,
              ]
            );
            // Add errors to form.
            $formState->setErrorByName(
              $propertyPath,
              $message
            );
            // Add propertypath to errored items to have only
            // single error from whole address item.
            $erroredItems[] = $propertyPath;
          }
        }
        else {
          // Add errors to form.
          $formState->setErrorByName(
            $propertyPath,
            $message
          );
          // Add propertypath to errored items to have only
          // single error from whole address item.
          $erroredItems[] = $propertyPath;
        }
      }
    }
    return $violations;
  }

  /**
   * Take in typed data object, export to Avus2 document structure & upload.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $applicationData
   *   Typed data object.
   * @param string $applicationNumber
   *   Used application number.
   *
   * @return bool
   *   Result.
   */
  public function handleApplicationUpload(
    TypedDataInterface $applicationData,
    string $applicationNumber
  ): bool {

    /** @var \Drupal\Core\TypedData\DataDefinitionInterface $applicationData */
    $appDocument = $this->atvSchema->typedDataToDocumentContent($applicationData);

    if ($this->isDebug()) {
      $t_args = [
        '@endpoint' => $this->endpoint,
      ];
      $this->logger
        ->debug(t('DEBUG: Endpoint: @endpoint', $t_args));
    }

    $myJSON = Json::encode($appDocument);

    // If debug, print out json.
    if ($this->isDebug()) {
      $t_args = [
        '@myJSON' => $myJSON,
      ];
      $this->logger
        ->debug('DEBUG: Sent JSON: @myJSON', $t_args);
    }

    try {

      $headers = [];
      if ($this->newStatusHeader && $this->newStatusHeader != '') {
        $headers['X-Case-Status'] = $this->newStatusHeader;
      }

      // Current environment as a header to be added to meta -fields.
      $headers['X-hki-appEnv'] = self::getAppEnv();
      // Set application number to meta as well to enable better searches.
      $headers['X-hki-applicationNumber'] = $applicationNumber;
      // Set application number to meta as well to enable better searches.
      $headers['X-hki-saveId'] = Uuid::uuid4()->toString();

      $res = $this->httpClient->post($this->endpoint, [
        'auth' => [
          $this->username,
          $this->password,
          "Basic",
        ],
        'body' => $myJSON,
        'headers' => $headers,
      ]);

      $status = $res->getStatusCode();

      if ($this->isDebug()) {
        $t_args = [
          '@status' => $status,
        ];
        $this->logger
          ->debug('Data sent to integration, response status: @status', $t_args);
      }

      if ($status === 201) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
      $this->logger->error($e->getMessage());
      return FALSE;
    }
    return FALSE;
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
   * Figure out from events which messages are unread.
   *
   * @param array $data
   *   Submission data.
   *
   * @return array
   *   Parsed messages with read information
   */
  public static function parseMessages(array $data) {

    $messageEvents = array_filter($data['events'], function ($event) {
      if ($event['eventType'] == EventsService::$eventTypes['MESSAGE_READ']) {
        return TRUE;
      }
      return FALSE;
    });

    $eventIds = array_column($messageEvents, 'eventTarget');

    $messages = [];

    foreach ($data['messages'] as $key => $message) {
      if (in_array($message['messageId'], $eventIds)) {
        $message['messageStatus'] = 'READ';
      }
      else {
        $message['messageStatus'] = 'UNREAD';
      }
      $messages[] = $message;
    }
    return $messages;
  }

  /**
   *
   */
  public static function getFakeMessages() {

    $d = Json::decode('[
                        {
                        "caseId": "GRANTS-TEST-YLEIS-00000411",
                        "messageId": "2d78f97b-75ff-48f8-b5a9-16cbca7f7ba1",
                        "body": "Where does Santa go on holiday? Why is grandma old? Can I eat a half chewed mini cheddar that my brother gave me? Where does the tallest man live? Can I eat the food on your plate? Why do cats miaow? Can I eat the last biscuit? Are we going to the park? Can I eat this pizza?",
                        "sentBy": "Mika Hietanen",
                        "sendDateTime": "2022-06-15T14:54:59"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000411",
                        "messageId": "8a52a0e9-a90e-429f-a12c-b2925b05ab6b",
                        "body": "Stella! Hey, Stella! I am big! It&#39;s the pictures that got small. Here&#39;s Johnny! Why so serious? I&#39;m just one stomach flu away from my goal weight. When you realize you want to spend the rest of your life with somebody, you want the rest of your life to start as soon as possible. Gentlemen, you can&#39;t fight in here! This is the war room!",
                        "sentBy": "Mika Hietanen",
                        "sendDateTime": "2022-06-15T14:56:39"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000411",
                        "messageId": "8a52a0e9-a90e-429f-a12c-52334523452345",
                        "body": "fasdfa aösdfha södföjahs föashf öasjdfh ",
                        "sentBy": "AVustus tms asdf",
                        "sendDateTime": "2022-06-15T14:56:39"
                    }
    ]');

    return $d;
  }

  /**
   *
   */
  public static function getFakeEvents() {
    return Json::decode('
    [
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T06:46:29Z",
                        "timeCreated": "2022-07-01T06:46:29Z",
                        "eventDescription": "File added successfully",
                        "eventID": "a84a7d37-5156-47fb-a9f7-97c24453cb19",
                        "eventTarget": "2022-07-01T09-46-20vahvistettu_tuloslaskelma_sub.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T06:46:33Z",
                        "timeCreated": "2022-07-01T06:46:33Z",
                        "eventDescription": "File added successfully",
                        "eventID": "dea20252-4405-4f97-8bb6-19ba77e8f985",
                        "eventTarget": "2022-07-01T09-46-20toimintakertomus_copy.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T06:46:37Z",
                        "timeCreated": "2022-07-01T06:46:37Z",
                        "eventDescription": "File added successfully",
                        "eventID": "20b6e35c-b604-45c0-abd2-b8f3bdd8d4c9",
                        "eventTarget": "2022-07-01T09-46-20arviosuunnitelma_0.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_APP_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T06:47:17Z",
                        "timeCreated": "2022-07-01T06:47:17Z",
                        "eventDescription": "Application sent Avustus2",
                        "eventID": "498e2464-0a69-4457-ba05-bd1d2e3daa91",
                        "eventTarget": "GRANTS-TEST-YLEIS-00000443"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "STATUS_UPDATE",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T06:47:21Z",
                        "timeCreated": "2022-07-01T06:47:21Z",
                        "eventDescription": "RECEIVED",
                        "eventID": "e167243e-1e52-4e4e-a8f0-e384b56ef4e1"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "MESSAGE_AVUS2",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T06:51:12Z",
                        "timeCreated": "2022-07-01T06:51:12Z",
                        "eventID": "26256604-aa6a-4194-9c29-c52119b8e52c",
                        "eventTarget": "8a52a0e9-a90e-429f-a12c-b2925b05ab6b"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "MESSAGE_AVUS2",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T06:51:12Z",
                        "timeCreated": "2022-07-01T06:51:12Z",
                        "eventID": "26256604-aa6a-4194-9c29-c52119b8e52c",
                        "eventTarget": "8a52a0e9-a90e-429f-a12c-52334523452345"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:03:25Z",
                        "timeCreated": "2022-07-01T07:03:25Z",
                        "eventDescription": "File added successfully",
                        "eventID": "ab7e569c-3835-444b-a23d-cbbb62408151",
                        "eventTarget": "2022-07-01T10-03-03toimintakertomus_copy.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:03:29Z",
                        "timeCreated": "2022-07-01T07:03:29Z",
                        "eventDescription": "File added successfully",
                        "eventID": "ee758abe-fbd8-4bdc-a2fa-b2e4b5c3d13e",
                        "eventTarget": "2022-07-01T10-03-03vuosikokouksen_poytakirja.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:03:33Z",
                        "timeCreated": "2022-07-01T07:03:33Z",
                        "eventDescription": "File added successfully",
                        "eventID": "ab307db3-de01-4388-949a-259bcf2491b7",
                        "eventTarget": "2022-07-01T10-03-03toimintasuunnitelma_draft.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:03:37Z",
                        "timeCreated": "2022-07-01T07:03:37Z",
                        "eventDescription": "File added successfully",
                        "eventID": "3979b7e8-f788-46d0-8dc7-47bd10a9e888",
                        "eventTarget": "2022-07-01T10-03-03talousarvio_submitted.docx"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:03:41Z",
                        "timeCreated": "2022-07-01T07:03:41Z",
                        "eventDescription": "File added successfully",
                        "eventID": "9bbd21f3-0e33-4640-a122-8182a4e5ea75",
                        "eventTarget": "2022-07-01T10-03-03kopio_la_leiriavustusselvitys_liite_tiedot_toteutuneista_leireista.xls"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_ATT_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:03:45Z",
                        "timeCreated": "2022-07-01T07:03:45Z",
                        "eventDescription": "File added successfully",
                        "eventID": "dc28a59c-69ef-4602-9270-526c1baf426e",
                        "eventTarget": "2022-07-01T10-03-03juhlavuosibn300_0.doc"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "INTEGRATION_INFO_APP_OK",
                        "eventCode": 0,
                        "eventSource": "Avustus integration",
                        "timeUpdated": "2022-07-01T07:04:31Z",
                        "timeCreated": "2022-07-01T07:04:31Z",
                        "eventDescription": "Application sent Avustus2",
                        "eventID": "a4db6dcd-1131-42ff-bd7a-76e06dc09a8f",
                        "eventTarget": "GRANTS-TEST-YLEIS-00000443"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "STATUS_UPDATE",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T07:04:34Z",
                        "timeCreated": "2022-07-01T07:04:34Z",
                        "eventDescription": "RECEIVED",
                        "eventID": "8b75cd44-927b-426f-9ed2-bb4602aa84ff"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "EVENT_INFO",
                        "eventCode": 0,
                        "eventSource": "Avustusten kasittelyjarjestelma",
                        "timeCreated": "2022-07-01T10:13:15",
                        "eventDescription": "Hakemustanne käsittelee Nimi - Puhakka Tero, Puhelinnumero - 09 310 36070 Sähköpostiosoite - tero.puhakka@hel.fi"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "STATUS_UPDATE",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T07:13:23Z",
                        "timeCreated": "2022-07-01T07:13:23Z",
                        "eventDescription": "RECEIVED",
                        "eventID": "f80d2acb-c62c-41be-b94c-c0e15cc53fc5"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "STATUS_UPDATE",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T07:14:17Z",
                        "timeCreated": "2022-07-01T07:14:17Z",
                        "eventDescription": "PROCESSING",
                        "eventID": "b77191b2-da07-4303-a2b2-8de59af6896c"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "STATUS_UPDATE",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T07:27:50Z",
                        "timeCreated": "2022-07-01T07:27:50Z",
                        "eventDescription": "CLOSED",
                        "eventID": "a68aea2d-bba9-48fc-9af1-c7d09fff8b86"
                    },
                    {
                        "caseId": "GRANTS-TEST-YLEIS-00000443",
                        "eventType": "STATUS_UPDATE",
                        "eventCode": 0,
                        "eventSource": "Avustustenkasittelyjarjestelma",
                        "timeUpdated": "2022-07-01T07:40:23Z",
                        "timeCreated": "2022-07-01T07:40:23Z",
                        "eventDescription": "CLOSED",
                        "eventID": "0d467981-377a-4061-a8ec-acb0087b014e"
                    }
                ]
    ');
  }

}
