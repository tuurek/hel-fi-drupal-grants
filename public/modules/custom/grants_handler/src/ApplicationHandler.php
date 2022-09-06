<?php

namespace Drupal\grants_handler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\grants_attachments\AttachmentHandler;
use Drupal\grants_metadata\AtvSchema;
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
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * ApplicationUploader service.
 */
class ApplicationHandler {

  /**
   * Name of the table where log entries are stored.
   */
  const TABLE = 'grants_handler_saveids';

  /**
   * Name of the navigation handler.
   */
  const HANDLER_ID = 'application_handler';


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
      'dataDefinition' => [
        'definitionClass' => 'Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
        'definitionId' => 'grants_metadata_yleisavustushakemus',
      ],
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
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * @param \Drupal\Core\Database\Connection $datababse
   *   Database connection.
   */
  public function __construct(
    ClientInterface $http_client,
    HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata,
    AtvService $atvService,
    AtvSchema $atvSchema,
    GrantsProfileService $grantsProfileService,
    LoggerChannelFactory $loggerChannelFactory,
    Messenger $messenger,
    EventsService $eventsService,
    Connection $datababse,
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
    $this->database = $datababse;
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
   * @param string $status
   *   If no object is available, do text comparison.
   *
   * @return bool
   *   Is submission editable?
   */
  public static function isSubmissionEditable(?WebformSubmission $submission, string $status = ''): bool {
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
   * Check if given submission is allowed to be edited.
   *
   * @param \Drupal\webform\Entity\WebformSubmission|null $submission
   *   Submission in question.
   * @param string $status
   *   If no object is available, do text comparison.
   *
   * @return bool
   *   Is submission editable?
   */
  public static function isSubmissionFinished(?WebformSubmission $submission, string $status = ''): bool {
    if (NULL === $submission) {
      $submissionStatus = $status;
    }
    else {
      $data = $submission->getData();
      $submissionStatus = $data['status'];
    }

    if (in_array($submissionStatus, [
      self::$applicationStatuses['READY'],
      self::$applicationStatuses['DONE'],
      self::$applicationStatuses['DELETED'],
      self::$applicationStatuses['CANCELED'],
      self::$applicationStatuses['CANCELLED'],
      self::$applicationStatuses['CLOSED'],
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
    // self::$applicationStatuses['DRAFT'],.
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
   * @param bool $refetch
   *   Force refetch from ATV.
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
    bool $refetch = FALSE
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

    /** @var \Drupal\grants_metadata\AtvSchema $atvSchema */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $destination = \Drupal::request()->getRequestUri();
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    // If no company selected, no mandates no access.
    if ($selectedCompany == NULL) {
      $redirectUrl = new Url('grants_mandate.mandateform', [], ['destination' => $destination]);
      $redirectResponse = new RedirectResponse($redirectUrl->toString());
      $redirectResponse->send();
    }

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
      if (self::getAppEnv() == 'LOCAL') {
        $submissionObject = WebformSubmission::create(['webform_id' => 'yleisavustushakemus']);
        $submissionObject->set('serial', $submissionSerial);
        $submissionObject->save();
      }
      else {
        throw new AtvDocumentNotFoundException('Submission not found.');
      }
    }
    else {
      $submissionObject = reset($result);
    }
    if ($submissionObject) {

      $dataDefinition = self::getDataDefinition($document->getType());

      $sData = $atvSchema->documentContentToTypedData(
        $document->getContent(),
        $dataDefinition,
        $document->getMetadata()
      );

      if ($selectedCompany['identifier'] !== $sData['company_number']) {
        throw new AccessException('Selected company ID does not match with one from document');
      }

      $sData['messages'] = self::parseMessages($sData);

      // Set submission data from parsed mapper.
      $submissionObject->setData($sData);

      return $submissionObject;
    }
    return NULL;
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
    string $definitionClass = '',
    string $definitionKey = ''
  ): TypedDataInterface {

    $dataDefinitionKeys = self::getDataDefinitionClass($submittedFormData['application_type']);

    $dataDefinition = $dataDefinitionKeys['definitionClass']::create($dataDefinitionKeys['definitionId']);

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
   * @param Drupal\Core\TypedData\TypedDataInterface $applicationData
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
    $myJSON = Json::encode($appDocument);

    if ($this->isDebug()) {
      $t_args = [
        '@endpoint' => $this->endpoint,
      ];
      $this->logger
        ->debug(t('DEBUG: Endpoint: @endpoint', $t_args));

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

      $this->logSubmissionSaveid(NULL, $applicationNumber, $headers['X-hki-saveId']);

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
        $this->atvService->clearCache($applicationNumber);
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
   * @param bool $onlyUnread
   *   Return only unread messages.
   *
   * @return array
   *   Parsed messages with read information
   */
  public static function parseMessages(array $data, $onlyUnread = FALSE) {

    $messageEvents = array_filter($data['events'], function ($event) {
      if ($event['eventType'] == EventsService::$eventTypes['MESSAGE_READ']) {
        return TRUE;
      }
      return FALSE;
    });

    $eventIds = array_column($messageEvents, 'eventTarget');

    $messages = [];
    $unread = [];

    foreach ($data['messages'] as $key => $message) {
      $msgUnread = NULL;
      $ts = strtotime($message["sendDateTime"]);
      if (in_array($message['messageId'], $eventIds)) {
        $message['messageStatus'] = 'READ';
        $msgUnread = FALSE;
      }
      else {
        $message['messageStatus'] = 'UNREAD';
        $msgUnread = TRUE;
      }

      if ($onlyUnread == TRUE && $msgUnread == TRUE) {
        $unread[$ts] = $message;
      }
      $messages[$ts] = $message;
    }
    if ($onlyUnread == TRUE) {
      return $unread;
    }
    return $messages;
  }

  /**
   * Set up sender details from helsinkiprofiili data.
   */
  public function parseSenderDetails() {
    // Set sender information after save so no accidental saving of data.
    $userProfileData = $this->helfiHelsinkiProfiiliUserdata->getUserProfileData();
    $userData = $this->helfiHelsinkiProfiiliUserdata->getUserData();

    $senderDetails = [];

    if (isset($userProfileData["myProfile"])) {
      $data = $userProfileData["myProfile"];
    }
    else {
      $data = $userProfileData;
    }

    // If no userprofile data, we need to hardcode these values.
    if ($userProfileData == NULL || $userData == NULL) {
      throw new ApplicationException('No profile data found for user.');
    }
    else {
      $senderDetails['sender_firstname'] = $data["verifiedPersonalInformation"]["firstName"];
      $senderDetails['sender_lastname'] = $data["verifiedPersonalInformation"]["lastName"];
      $senderDetails['sender_person_id'] = $data["verifiedPersonalInformation"]["nationalIdentificationNumber"];
      $senderDetails['sender_user_id'] = $userData["sub"];
      $senderDetails['sender_email'] = $data["primaryEmail"]["email"];
    }

    return $senderDetails;
  }

  /**
   * Access method to clear cache in atv service.
   *
   * @param string $applicationNumber
   *   Application number.
   */
  public function clearCache(string $applicationNumber) {
    $this->atvService->clearCache($applicationNumber);
  }

  /**
   * Get data definition class from application type.
   *
   * @param string $type
   *   Type of the application.
   */
  public static function getDataDefinition(string $type) {
    $defClass = self::$applicationTypes[$type]['dataDefinition']['definitionClass'];
    $defId = self::$applicationTypes[$type]['dataDefinition']['definitionId'];
    return $defClass::create($defId);
  }

  /**
   * Get data definition class from application type.
   *
   * @param string $type
   *   Type of the application.
   */
  public static function getDataDefinitionClass(string $type) {
    return self::$applicationTypes[$type]['dataDefinition'];
  }

  /**
   * Get company applications, either sorted by finished or all in one array.
   *
   * @param array $selectedCompany
   *   Company data.
   * @param string $appEnv
   *   Environment.
   * @param bool $sortByFinished
   *   When true, results will be sorted by finished status.
   * @param bool $sortByStatus
   *   Sort by application status.
   * @param string $themeHook
   *   Use theme hook to render content. Set this to theme hook wanted to use,
   *   and sen #submission to webform submission.
   *
   * @return array
   *   Submissions in array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function getCompanyApplications(
    array $selectedCompany,
    string $appEnv,
    bool $sortByFinished = FALSE,
    bool $sortByStatus = FALSE,
  string $themeHook = '') {

    /** @var \Drupal\helfi_atv\AtvService $atvService */
    $atvService = \Drupal::service('helfi_atv.atv_service');

    $applications = [];
    $finished = [];
    $unfinished = [];

    try {
      $applicationDocuments = $atvService->searchDocuments([
        'service' => 'AvustushakemusIntegraatio',
        'business_id' => $selectedCompany['identifier'],
        'lookfor' => 'appenv:' . $appEnv,
      ]);

      /**
       * Create rows for table.
       *
       * @var integer $key
       * @var  \Drupal\helfi_atv\AtvDocument $document
       */
      foreach ($applicationDocuments as $key => $document) {
        // Make sure we only use submissions from this env and the type is
        // acceptable one.
        if (
          str_contains($document->getTransactionId(), $appEnv) &&
          array_key_exists($document->getType(), ApplicationHandler::$applicationTypes)
        ) {

          try {
            $submissionObject = self::submissionObjectFromApplicationNumber($document->getTransactionId(), $document);
            $submissionData = $submissionObject->getData();
            $ts = strtotime($submissionData['form_timestamp']);
            if ($themeHook !== '') {
              $submission = [
                '#theme' => $themeHook,
                '#submission' => $submissionObject,
                '#document' => $document,
              ];
            }
            else {
              $submission = $submissionObject;
            }
            if ($sortByFinished == TRUE) {
              if (self::isSubmissionFinished($submission)) {
                $finished[$ts] = $submission;
              }
              else {
                $unfinished[$ts] = $submission;
              }
            }
            elseif ($sortByStatus == TRUE) {
              $applications[$submissionData['status']][] = $submission;
            }
            else {
              $applications[$ts] = $submission;
            }
          }
          catch (AtvDocumentNotFoundException $e) {
          }
        }
      }
    }
    catch (\Exception $e) {
    }

    if ($sortByFinished == TRUE) {
      ksort($finished);
      ksort($unfinished);
      return [
        'finished' => $finished,
        'unifinished' => $unfinished,
      ];
    }
    else {
      ksort($applications);
      return $applications;
    }
  }

  /**
   * Logs the current submission page.
   *
   * @param \Drupal\webform\WebformSubmissionInterface|null $webform_submission
   *   A webform submission entity.
   * @param string $applicationNumber
   *   The page to log.
   * @param string $saveId
   *   Submission save id.
   *
   * @throws \Exception
   */
  public function logSubmissionSaveid(
    ?WebformSubmissionInterface $webform_submission,
    string $applicationNumber,
    string $saveId
  ) {

    if ($webform_submission == NULL) {
      $webform_submission = ApplicationHandler::submissionObjectFromApplicationNumber($applicationNumber);
    }

    $userData = $this->helfiHelsinkiProfiiliUserdata->getUserData();
    $fields = [
      'webform_id' => ($webform_submission) ? $webform_submission->getWebform()->id() : '',
      'sid' => ($webform_submission) ? $webform_submission->id() : 0,
      'handler_id' => self::HANDLER_ID,
      'application_number' => $applicationNumber,
      'saveid' => $saveId,
      'uid' => \Drupal::currentUser()->id(),
      'user_uuid' => $userData['sub'] ?? '',
      'timestamp' => (string) \Drupal::time()->getRequestTime(),
    ];

    $query = $this->database->insert(self::TABLE, $fields);
    $query->fields($fields)->execute();

  }

  /**
   * Validate submission data integrity.
   *
   * Validates file uploads as well, we can't allow other updates to data
   * before all attachment related things are done properly with integration.
   *
   * @param \Drupal\webform\WebformSubmissionInterface|null $webform_submission
   *   Webform submission object, if known. If this is not set,
   *   submission data must be provided.
   * @param array|null $submissionData
   *   Submission data. If no submission object, this is required.
   * @param string $applicationNumber
   *   Application number.
   * @param string $saveIdToValidate
   *   Save uuid to validate data integrity against.
   *
   * @return string
   *   Data integrity status.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   */
  public function validateDataIntegrity(
    ?WebformSubmissionInterface $webform_submission,
    ?array $submissionData,
    string $applicationNumber,
    string $saveIdToValidate): string {

    if ($submissionData == NULL || empty($submissionData)) {
      if ($webform_submission == NULL) {
        $webform_submission = ApplicationHandler::submissionObjectFromApplicationNumber($applicationNumber);
      }
      $submissionData = $webform_submission->getData();
    }
    if ($submissionData == NULL || empty($submissionData)) {
      $this->logger->error('No submissiondata when trying to validate saveid: @saveid', ['@saveid' => $saveIdToValidate]);
      return 'NO_SUBMISSION_DATA';
    }

    $query = $this->database->select(self::TABLE, 'l');
    $query->condition('application_number', $applicationNumber);
    $query->fields('l', [
      'lid',
      'saveid',
    ]);
    $query->orderBy('l.lid', 'DESC');
    $query->range(0, 1);

    $saveid_log = $query->execute()->fetch();
    $latestSaveid = !empty($saveid_log->saveid) ? $saveid_log->saveid : '';

    if ($saveIdToValidate !== $latestSaveid) {
      return 'DATA_NOT_SAVED_ATV';
    }

    $applicationEvents = EventsService::filterEvents($submissionData['events'] ?? [], 'INTEGRATION_INFO_APP_OK');

    if (!in_array($saveIdToValidate, $applicationEvents['event_targets'])) {
      if ($submissionData['status'] != 'DRAFT') {
        return 'DATA_NOT_SAVED_AVUS2';
      }
    }

    $attachmentEvents = EventsService::filterEvents($submissionData['events'] ?? [], 'INTEGRATION_INFO_ATT_OK');

    $fileFieldNames = AttachmentHandler::getAttachmentFieldNames();

    $nonUploaded = 0;
    foreach ($fileFieldNames as $fieldName) {
      $fileField = $submissionData[$fieldName] ?? NULL;
      if ($fileField == NULL) {
        continue;
      }
      if (self::isMulti($fileField)) {
        foreach ($fileField as $muu_liite) {
          if (isset($muu_liite['fileName'])) {
            if (!in_array($muu_liite['fileName'], $attachmentEvents["event_targets"])) {
              // $nonUploaded++;
            }
          }
        }
      }
      else {
        if (isset($fileField['fileName'])) {
          if (!in_array($fileField['fileName'], $attachmentEvents["event_targets"])) {
            $nonUploaded++;
          }
        }

      }
    }

    if ($nonUploaded !== 0) {
      return 'FILE_UPLOAD_PENDING';
    }

    return 'OK';

  }

  /**
   * Is array multidimensional.
   *
   * @param array $arr
   *   Array to be inspected.
   *
   * @return bool
   *   True or false.
   */
  public static function isMulti(array $arr) {
    foreach ($arr as $v) {
      if (is_array($v)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Clear application data for noncopyable elements.
   *
   * @param array $data
   *   Data to copy from.
   *
   * @return array
   *   Cleaned values.
   */
  public static function clearDataForCopying(array $data): array {
    unset($data["application_number"]);
    unset($data["sender_firstname"]);
    unset($data["sender_lastname"]);
    unset($data["sender_person_id"]);
    unset($data["sender_user_id"]);
    unset($data["sender_email"]);
    unset($data["metadata"]);
    unset($data["attachments"]);

    $data['events'] = [];
    $data['messages'] = [];
    $data['status_updates'] = [];

    // Clear uploaded files..
    foreach (AttachmentHandler::getAttachmentFieldNames() as $fieldName) {
      unset($data[$fieldName]);
    }

    return $data;

  }

}
