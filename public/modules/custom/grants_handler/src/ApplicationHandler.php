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
    'CANCELLED' => 'CANCELLED',
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
   */
  public function __construct(
    ClientInterface $http_client,
    HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata,
    AtvService $atvService,
    AtvSchema $atvSchema,
    GrantsProfileService $grantsProfileService,
    LoggerChannelFactory $loggerChannelFactory,
    Messenger $messenger
  ) {

    $this->httpClient = $http_client;
    $this->helfiHelsinkiProfiiliUserdata = $helfi_helsinki_profiili_userdata;
    $this->atvService = $atvService;
    $this->atvSchema = $atvSchema;
    $this->grantsProfileService = $grantsProfileService;

    $this->atvSchema->setSchema(getenv('ATV_SCHEMA_PATH'));

    $this->messenger = $messenger;
    $this->logger = $loggerChannelFactory->get('grants_application_handler');

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
    AtvDocument $document = NULL
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
          TRUE
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

      // Set submission data from parsed mapper.
      $submissionObject->setData($atvSchema->documentContentToTypedData(
        $document->getContent(),
        YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus')
      ));

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
      $headers['X-Meta-appEnv'] = self::getAppEnv();
      // Set application number to meta as well to enable better searches.
      $headers['X-Meta-applicationNumber'] = $applicationNumber;

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

}
