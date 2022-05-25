<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\grants_attachments\AttachmentRemover;
use Drupal\grants_attachments\AttachmentUploader;
use Drupal\grants_attachments\Plugin\WebformElement\GrantsAttachments;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvDocument;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Webform example handler.
 *
 * @WebformHandler(
 *   id = "grants_handler",
 *   label = @Translation("Grants Handler"),
 *   category = @Translation("helfi"),
 *   description = @Translation("Grants webform handler"),
 *   cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class GrantsHandler extends WebformHandlerBase {

  /**
   * Form data saved because the data in saved submission is not preserved.
   *
   * @var array
   *   Holds submitted data for processing in confirmForm.
   *
   * When we want to delete all submitted data before saving
   * submission to database. This way we can still use webform functionality
   * while not saving any sensitive data to local drupal.
   */
  private array $submittedFormData = [];

  /**
   * Field names for attachments.
   *
   * @var string[]
   *
   * @todo get field names from form where field type is attachment.
   */
  protected static array $attachmentFieldNames = [
    'vahvistettu_tilinpaatos' => 43,
    'vahvistettu_toimintakertomus' => 4,
    'vahvistettu_tilin_tai_toiminnantarkastuskertomus' => 5,
    'vuosikokouksen_poytakirja' => 8,
    'toimintasuunnitelma' => 1,
    'talousarvio' => 2,
    'muu_liite' => 0,
  ];

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
  ];

  /**
   * Array containing added file ids for removal & upload.
   *
   * @var array
   */
  private array $attachmentFileIds;

  /**
   * Uploader service.
   *
   * @var \Drupal\grants_attachments\AttachmentUploader
   */
  protected AttachmentUploader $attachmentUploader;

  /**
   * Remover service.
   *
   * @var \Drupal\grants_attachments\AttachmentRemover
   */
  protected AttachmentRemover $attachmentRemover;

  /**
   * Application type.
   *
   * @var string
   */
  protected string $applicationType;

  /**
   * Applicant type.
   *
   * Private / registered / UNregistered.
   *
   * @var string
   */
  protected string $applicantType;

  /**
   * Application type ID.
   *
   * @var string
   */
  protected string $applicationTypeID;

  /**
   * Generated application number.
   *
   * @var string
   */
  protected string $applicationNumber;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * User data from helsinkiprofiili & auth methods.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $userExternalData;

  /**
   * Access ATV backend.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * Access GRants profile.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Access ATV backend.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected DateFormatter $dateFormatter;

  /**
   * Holds document fetched from ATV for checks.
   *
   * @var \Drupal\helfi_atv\AtvDocument
   */
  protected AtvDocument $atvDocument;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    /** @var \Drupal\Core\DependencyInjection\Container $container */
    $instance->attachmentUploader = $container->get('grants_attachments.attachment_uploader');
    $instance->attachmentRemover = $container->get('grants_attachments.attachment_remover');

    // Make sure we have empty array as initial value.
    $instance->attachmentFileIds = [];

    $instance->currentUser = $container->get('current_user');

    $instance->userExternalData = $container->get('helfi_helsinki_profiili.userdata');

    /** @var \Drupal\helfi_atv\AtvService atvService */
    $instance->atvService = $container->get('helfi_atv.atv_service');

    /** @var \Drupal\grants_metadata\AtvSchema atvSchema */
    $instance->atvSchema = $container->get('grants_metadata.atv_schema');
    $instance->atvSchema->setSchema(getenv('ATV_SCHEMA_PATH'));

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $instance->grantsProfileService = \Drupal::service('grants_profile.service');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $instance->dateFormatter = \Drupal::service('date.formatter');

    return $instance;
  }

  /*
   * Static methods
   */

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
   * Get file fields.
   *
   * @return string[]
   *   Attachment fields.
   */
  public static function getAttachmentFieldNames($preventKeys = FALSE): array {
    if ($preventKeys) {
      return self::$attachmentFieldNames;
    }
    return array_keys(self::$attachmentFieldNames);
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

  /*
   * Non static methods.
   */

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
   */
  protected function getAtvDocument(string $transactionId): AtvDocument {

    if (!isset($this->atvDocument)) {
      $res = $this->atvService->searchDocuments([
        'transaction_id' => $transactionId,
      ]);
      $this->atvDocument = reset($res);
    }

    return $this->atvDocument;
  }

  /**
   * Convert EUR format value to "double" .
   *
   * @param string $value
   *   Value to be converted.
   *
   * @return float
   *   Floated value.
   */
  private function grantsHandlerConvertToFloat(string $value): float {
    $value = str_replace(['€', ',', ' '], ['', '.', ''], $value);
    $value = (float) $value;
    return $value;
  }

  /**
   * Convert EUR format value to "double" .
   *
   * @param string|null $value
   *   Value to be converted.
   *
   * @return float
   *   Floated value.
   */
  public static function convertToFloat(?string $value = ''): float {
    $value = str_replace(['€', ',', ' '], ['', '.', ''], $value);
    $value = (float) $value;
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // Development.
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, every handler method invoked will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * Calculate & set total values from added elements in webform.
   */
  protected function setTotals() {

    if (isset($this->submittedFormData['myonnetty_avustus']) &&
      is_array($this->submittedFormData['myonnetty_avustus'])) {
      $tempTotal = 0;
      foreach ($this->submittedFormData['myonnetty_avustus'] as $key => $item) {
        $amount = $this->grantsHandlerConvertToFloat($item['amount']);
        $tempTotal += $amount;
      }
      $this->submittedFormData['myonnetty_avustus_total'] = $tempTotal;
    }

    if (isset($this->submittedFormData['haettu_avustus_tieto']) &&
      is_array($this->submittedFormData['haettu_avustus_tieto'])) {
      $tempTotal = 0;
      foreach ($this->submittedFormData['haettu_avustus_tieto'] as $item) {
        $amount = $this->grantsHandlerConvertToFloat($item['amount']);
        $tempTotal += $amount;
      }
      $this->submittedFormData['haettu_avustus_tieto_total'] = $tempTotal;

    }

    // @todo properly get amount
    $this->submittedFormData['compensation_total_amount'] = $tempTotal;

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

    return 'GRANTS-' . $appParam . '-' . sprintf('%08d', $submission->serial());
  }

  /**
   * Get submission object from local database & fill form data from ATV or if
   * local submission is not found, create new and set data.
   *
   * @param string $applicationNumber
   *   String to try and parse submission id from. Ie GRANTS-DEV-00000098.
   *
   * @return \Drupal\webform\Entity\WebformSubmission|null
   *   Webform submission.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function submissionObjectFromApplicationNumber(string $applicationNumber): ?WebformSubmission {

    $exploded = explode('-', $applicationNumber);
    $number = end($exploded);
    $submissionSerial = ltrim($number, '0');

    $result = \Drupal::entityTypeManager()
      ->getStorage('webform_submission')
      ->loadByProperties([
        'serial' => $submissionSerial,
      ]);

    /** @var \Drupal\helfi_atv\AtvService $atvService */
    $atvService = \Drupal::service('helfi_atv.atv_service');

    /** @var \Drupal\grants_metadata\AtvSchema $atvSchema */
    $atvSchema = \Drupal::service('grants_metadata.atv_schema');

    // If there's no local submission with given serial
    // we can actually create that object on the fly and use that for editing.
    if (empty($result)) {
      try {
        // Create submission.
        // @todo remove hardcoded form type at some point.
        $createdSubmissionObject = WebformSubmission::create([
          'webform_id' => 'yleisavustushakemus',
          'serial' => $submissionSerial,
        ]);
        // Make sure serial is set.
        $createdSubmissionObject->set('serial', $submissionSerial);

        // Get document from ATV.
        $document = $atvService->searchDocuments([
          'transaction_id' => $applicationNumber,
        ],
          TRUE);

        /** @var \Drupal\helfi_atv\AtvDocument $document */
        $document = reset($document);

        // Save submission BEFORE setting data so we don't accidentally
        // save anything.
        $createdSubmissionObject->save();

        // Set submission data from parsed mapper.
        $createdSubmissionObject->setData($atvSchema->documentContentToTypedData(
          $document->getContent(),
          YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus')));

        return $createdSubmissionObject;

      } catch (
      AtvDocumentNotFoundException|
      AtvFailedToConnectException|
      GuzzleException|
      TempStoreException|
      EntityStorageException $e) {
        return NULL;
      }
    }
    else {
      $submissionObject = reset($result);

      // Get document from ATV.
      try {
        $document = $atvService->searchDocuments([
          'transaction_id' => $applicationNumber,
        ],
          TRUE);
      } catch (TempStoreException|AtvDocumentNotFoundException|AtvFailedToConnectException|GuzzleException $e) {
        return NULL;
      }

      /** @var \Drupal\helfi_atv\AtvDocument $document */
      $document = reset($document);

      // Set submission data from parsed mapper.
      $submissionObject->setData($atvSchema->documentContentToTypedData(
        $document->getContent(),
        YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus')));

      return $submissionObject;
    }
  }

  /**
   * Set up sender details from helsinkiprofiili data.
   */
  private function parseSenderDetails() {
    // Set sender information after save so no accidental saving of data.
    // @todo Think about how sender info should be parsed, maybe in own.
    $userProfileData = $this->userExternalData->getUserProfileData();
    $userData = $this->userExternalData->getUserData();

    if (isset($userProfileData["myProfile"])) {
      $data = $userProfileData["myProfile"];
    }
    else {
      $data = $userProfileData;
    }

    // If no userprofile data, we need to hardcode these values.
    // @todo Remove hardcoded values when tunnistamo works.
    if ($userProfileData == NULL || $userData == NULL) {
      $this->submittedFormData['sender_firstname'] = 'NoTunnistamo';
      $this->submittedFormData['sender_lastname'] = 'NoTunnistamo';
      $this->submittedFormData['sender_person_id'] = 'NoTunnistamo';
      $this->submittedFormData['sender_user_id'] = '280f75c5-6a20-4091-b22d-dfcdce7fef60';
      $this->submittedFormData['sender_email'] = 'NoTunnistamo';

    }
    else {
      $userData = $this->userExternalData->getUserData();
      $this->submittedFormData['sender_firstname'] = $data["verifiedPersonalInformation"]["firstName"];
      $this->submittedFormData['sender_lastname'] = $data["verifiedPersonalInformation"]["lastName"];
      $this->submittedFormData['sender_person_id'] = $data["verifiedPersonalInformation"]["nationalIdentificationNumber"];
      $this->submittedFormData['sender_user_id'] = $userData["sub"];
      $this->submittedFormData['sender_email'] = $data["primaryEmail"]["email"];
    }
  }

  /**
   * Format form values to be consumed with typedata.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $webform_submission
   *   Submission object.
   *
   * @return mixed
   *   Massaged values.
   */
  protected function massageFormValuesFromWebform(WebformSubmission $webform_submission): mixed {
    $values = $webform_submission->getData();

    if (isset($values['community_address']) && $values['community_address'] !== NULL) {
      $values += $values['community_address'];
      unset($values['community_address']);
      unset($values['community_address_select']);
    }

    if (isset($values['bank_account']) && $values['bank_account'] !== NULL) {
      $values['account_number'] = $values['bank_account']['account_number'];
      unset($values['bank_account']);
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array                      &$form,
    FormStateInterface         $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    parent::validateForm($form, $form_state, $webform_submission);

    //    $officials = $form_state->getValue('community_officials');

    // Get current page.
    $currentPage = $form["progress"]["#current_page"];

    // 1_hakijan_tiedot
    // 2_avustustiedot
    // 3_yhteison_tiedot
    // lisatiedot_ja_liitteet
    // webform_preview
    $this->submittedFormData = $this->massageFormValuesFromWebform($webform_submission);
    $this->submittedFormData['applicant_type'] = $form_state->getValue('applicant_type');

    foreach ($this->submittedFormData["myonnetty_avustus"] as $key => $value) {
      $this->submittedFormData["myonnetty_avustus"][$key]['issuerName'] = $value['issuer_name'];
      unset($this->submittedFormData["myonnetty_avustus"][$key]['issuer_name']);
    }
    foreach ($this->submittedFormData["haettu_avustus_tieto"] as $key => $value) {
      $this->submittedFormData["haettu_avustus_tieto"][$key]['issuerName'] = $value['issuer_name'];
      unset($this->submittedFormData["haettu_avustus_tieto"][$key]['issuer_name']);
    }

    // Only validate set forms.
    if ($currentPage === 'lisatiedot_ja_liitteet' || $currentPage === 'webform_preview') {
      // Loop through fieldnames and validate fields.
      foreach (self::getAttachmentFieldNames() as $fieldName) {
        $fValues = $form_state->getValue($fieldName);
        $this->validateAttachmentField(
          $fieldName,
          $form_state,
          $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$fieldName]["#title"]
        );
      }
    }

    $errors = $form_state->getErrors();
    if (!empty($errors)) {
      $this->messenger()
        ->addWarning($this->t('Errors in form data, please fix them before going on.'));
    }
  }

  /**
   * Validate single attachment field.
   *
   * @param string $fieldName
   *   Name of the field in validation.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $fieldTitle
   *   Field title for errors.
   *
   * @todo think about how attachment validation logic could be moved to the
   *   component.
   */
  private function validateAttachmentField(string $fieldName, FormStateInterface $form_state, string $fieldTitle) {
    // Get value.
    $values = $form_state->getValue($fieldName);

    $args = [];
    if (isset($values[0]) && is_array($values[0])) {
      $args = $values;
    }
    else {
      $args[] = $values;
    }

    foreach ($args as $value) {
      // Muu liite is optional.
      if ($fieldName !== 'muu_liite' && ($value === NULL || empty($value))) {
        $form_state->setErrorByName($fieldName, $this->t('@fieldname field is required', [
          '@fieldname' => $fieldTitle,
        ]));
      }
      if ($value !== NULL) {
        // If attachment is uploaded, make sure no other field is selected.
        if (isset($value['attachment']) && is_int($value['attachment'])) {
          if ($value['isDeliveredLater'] === "1") {
            $form_state->setErrorByName("[" . $fieldName . "][isDeliveredLater]", $this->t('@fieldname has file added, it cannot be added later.', [
              '@fieldname' => $fieldTitle,
            ]));
          }
          if ($value['isIncludedInOtherFile'] === "1") {
            $form_state->setErrorByName("[" . $fieldName . "][isIncludedInOtherFile]", $this->t('@fieldname has file added, it cannot belong to other file.', [
              '@fieldname' => $fieldTitle,
            ]));
          }
        }
        else {
          if ((!empty($value) && !isset($value['attachment']) && ($value['attachment'] === NULL && $value['attachmentName'] === ''))) {
            if (empty($value['isDeliveredLater']) && empty($value['isIncludedInOtherFile'])) {
              $form_state->setErrorByName("[" . $fieldName . "][isDeliveredLater]", $this->t('@fieldname has no file uploaded, it must be either delivered later or be included in other file.', [
                '@fieldname' => $fieldTitle,
              ]));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {

    // These both are required to be selected.
    // probably will change when we have proper company selection process.
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $applicantType = $this->grantsProfileService->getApplicantType();
    if ($applicantType === NULL) {

      \Drupal::messenger()
        ->addError(t('You need to select applicant type.'));

      $url = Url::fromRoute('grants_profile.applicant_type', [
        'destination' => $values["uri"],
      ])
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($url);
      $response->send();
    }
    else {
      $this->applicantType = $this->grantsProfileService->getApplicantType();
    }

    if ($selectedCompany == NULL) {
      \Drupal::messenger()
        ->addError(t("You need to select company you're acting behalf of."));

      $url = Url::fromRoute('grants_profile.show', [
        'destination' => $values["uri"],
      ])
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($url);
      $response->send();
    }

  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    // If submission has applicant type set, ie we're editing submission
    // use that, if not then get selected from profile.
    // we know that.
    $submissionData = $this->massageFormValuesFromWebform($webform_submission);
    if (isset($submissionData['applicant_type'])) {
      $applicantType = $submissionData['applicant_type'];
    }
    else {
      $applicantTypeString = $this->grantsProfileService->getApplicantType();
      $applicantType = '0';
      switch ($applicantTypeString) {
        case 'registered_community':
          $applicantType = '0';
          break;

        case 'unregistered_community':
          $applicantType = '1';
          break;

        case 'private_person':
          $applicantType = '2';
          break;
      }
    }

    $form["elements"]["1_hakijan_tiedot"]["yhteiso_jolle_haetaan_avustusta"]["applicant_type"] = [
      '#type' => 'hidden',
      '#value' => $applicantType,
    ];

    $thisYear = (integer) date('Y');
    $thisYearPlus1 = $thisYear + 1;
    $thisYearPlus2 = $thisYear + 2;

    $form["elements"]["2_avustustiedot"]["avustuksen_tiedot"]["acting_year"]['#required'] = TRUE;
    $form["elements"]["2_avustustiedot"]["avustuksen_tiedot"]["acting_year"]["#options"] = [
      $thisYear => $thisYear,
      $thisYearPlus1 => $thisYearPlus1,
      $thisYearPlus2 => $thisYearPlus2,
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {

    // don't save ip address.
    $webform_submission->remote_addr->value = '';

    if (empty($this->submittedFormData)) {
      $this->submittedFormData = $this->massageFormValuesFromWebform($webform_submission);
    }

    // If for some reason applicant type is not present, make sure it get's
    // added otherwise validation fails.
    if (!isset($this->submittedFormData['applicant_type'])) {
      $this->submittedFormData['applicant_type'] = $this->grantsProfileService->getApplicantType();
    }

    if (isset($this->submittedFormData["community_purpose"])) {
      $this->submittedFormData["business_purpose"] = $this->submittedFormData["community_purpose"];
    }

    if (!empty($this->submittedFormData)) {
      $this->setTotals();
      $this->parseSenderDetails();
      // Set submission data to empty.
      // form will still contain submission details, IP time etc etc.
      $webform_submission->setData([]);
    }
  }

  /**
   * Method to figure out if formUpdate should be false/true?
   *
   * The thing is that the Avustus2 is not very clear about when it fetches
   * data from ATV. Initial import from ATV MUST have fromUpdate FALSE, and
   * any subsequent update will have to have it as FALSE. The application status
   * handling makes this possibly very complicated, hence separate method
   * figuring it out.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $webformSubmission
   *   Submission being saved. If status of submission is needed.
   */
  private function setFormUpdate(WebformSubmission $webformSubmission) {
    if (!isset($this->submittedFormData['application_number']) && $this->submittedFormData['status'] === 'DRAFT') {
      $this->submittedFormData['form_update'] = FALSE;
    }
    elseif (!isset($this->submittedFormData['application_number']) && $this->submittedFormData['status'] === 'SUBMITTED') {
      $this->submittedFormData['form_update'] = FALSE;
    }
    elseif (isset($this->submittedFormData['application_number']) && $this->submittedFormData['status'] === 'SUBMITTED') {
      $this->submittedFormData['form_update'] = FALSE;
    }
    else {
      $this->submittedFormData['form_update'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->applicationType = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');
    $this->applicationTypeID = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationTypeID');

    $dt = new \DateTime();
    // $dt->setTimestamp($webform_submission->getCreatedTime());

    $dt->setTimezone(new \DateTimeZone('Europe/Helsinki'));
    $this->submittedFormData['form_timestamp'] = $dt->format('Y-m-d\TH:i:s');
    //    $this->submittedFormData['form_timestamp'] = $dt->format('Y-m-d\TH:i:s\.\0\0\0\Z');

    // Get regdate from profile data and format it for Avustus2
    // This data is immutable for end user so safe to this way.
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $grantsProfile = $this->grantsProfileService->getGrantsProfileContent($selectedCompany);


    $regDate = new DrupalDateTime($grantsProfile["registrationDate"], 'Europe/Helsinki');
    $this->submittedFormData["registration_date"] = $regDate->format('Y-m-d\TH:i:s');
    //    $this->submittedFormData["registration_date"] = $regDate->format('Y-m-d\TH:i:s\.\0\0\0\Z');

    if (isset($this->submittedFormData["finalize_application"]) &&
      $this->submittedFormData["finalize_application"] == 1) {
      $this->submittedFormData['status'] = 'SUBMITTED';
    }

    // This needs to be called before setting applicationNumber
    // with new submission.
    $this->setFormUpdate($webform_submission);

    if (!isset($this->submittedFormData['application_number'])) {
      $this->applicationNumber = self::createApplicationNumber($webform_submission);
      $this->submittedFormData['application_type_id'] = $this->applicationTypeID;
      $this->submittedFormData['application_type'] = $this->applicationType;
      $this->submittedFormData['application_number'] = $this->applicationNumber;
    }
    else {
      $this->applicationNumber = $this->submittedFormData['application_number'];
    }

    // Because of funky naming convention, we need to manually
    // set purpose field value.
    // This is populated from grants profile so it's just passing this on.
    if (isset($this->submittedFormData["community_purpose"])) {
      $this->submittedFormData["business_purpose"] = $this->submittedFormData["community_purpose"];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(
    array                      &$form,
    FormStateInterface         $form_state,
    WebformSubmissionInterface $webform_submission) {

    $dataDefinition = YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus');

    $typeManager = $dataDefinition->getTypedDataManager();
    $applicationData = $typeManager->create($dataDefinition);

    $this->submittedFormData['attachments'] = $this->parseAttachments($form);

    try {
      $applicationData->setValue($this->submittedFormData);
    } catch (\Exception $e) {
    }

    $violations = $applicationData->validate();

    if ($violations->count() > 0) {
      foreach ($violations as $violation) {
        $this->getLogger('grants_handler')
          ->debug($this->t('Error with data. Property: %property. Message: %message', [
            '%property' => $violation->getPropertyPath(),
            '%message' => $violation->getMessage(),
          ]));
        $this->messenger()
          ->addError($this->t('Data not saved, error with data. (This functionality WILL change before production.) Property: %property. Message: %message', [
            '%property' => $violation->getPropertyPath(),
            '%message' => $violation->getMessage(),
          ]));
      }
      return;
    }

    // If there's violations in data.
    if ($violations->count() == 0) {

      $appDocument = $this->atvSchema->typedDataToDocumentContent($applicationData);

      $endpoint = getenv('AVUSTUS2_ENDPOINT');
      $username = getenv('AVUSTUS2_USERNAME');
      $password = getenv('AVUSTUS2_PASSWORD');

      if (!empty($this->configuration['debug'])) {
        $t_args = [
          '@endpoint' => $endpoint,
        ];
        $this->messenger()
          ->addMessage($this->t('DEBUG: Endpoint:: @endpoint', $t_args));
      }

      $myJSON = Json::encode($appDocument);

      // If debug, print out json.
      if ($this->isDebug()) {
        $t_args = [
          '@myJSON' => $myJSON,
        ];
        $this->getLogger('grants_handler')
          ->debug('DEBUG: Sent JSON: @myJSON', $t_args);
      }
      // If backend mode is dev, then don't post things to backend.
      if (getenv('BACKEND_MODE') === 'dev') {
        $this->messenger()
          ->addWarning($this->t('Backend DEV mode on, no posting to backend is done.'));
      }
      else {
        try {
          $client = \Drupal::httpClient();
          $res = $client->post($endpoint, [
            'auth' => [$username, $password, "Basic"],
            'body' => $myJSON,
          ]);

          $status = $res->getStatusCode();

          if ($this->isDebug()) {
            $t_args = [
              '@status' => $status,
            ];
            $this->getLogger('grants_handler')
              ->debug('Data sent to integration, response status: @status', $t_args);
          }

          if ($status === 201) {
            $this->attachmentUploader->setDebug($this->isDebug());
            $attachmentResult = $this->attachmentUploader->uploadAttachments(
              $this->attachmentFileIds,
              $this->applicationNumber,
              $this->isDebug()
            );

            foreach ($attachmentResult as $attResult) {
              if ($attResult['upload'] === TRUE) {
                $this->messenger()
                  ->addStatus(
                    $this->t(
                      'Attachment (@filename) uploaded',
                      [
                        '@filename' => $attResult['filename'],
                      ]));
              }
              else {
                $this->messenger()
                  ->addStatus(
                    $this->t(
                      'Attachment (@filename) upload failed with message: @msg. Event has been logged.',
                      [
                        '@filename' => $attResult['filename'],
                        '@msg' => $attResult['msg'],
                      ]));
              }
            }

            $url = Url::fromRoute(
              'grants_profile.view_application',
              ['document_uuid' => $this->applicationNumber],
              [
                'attributes' => [
                  'data-drupal-selector' => 'application-saved-successfully-link',
                ],
              ]
            );

            $this->messenger()
              ->addStatus(
                $this->t(
                  'Grant application (<span id="saved-application-number">@number</span>) saved, 
                  see application status from @link',
                  [
                    '@number' => $this->applicationNumber,
                    '@link' => Link::fromTextAndUrl('here', $url)->toString(),
                  ]));

            $this->attachmentRemover->removeGrantAttachments(
              $this->attachmentFileIds,
              $attachmentResult,
              $this->applicationNumber,
              $this->isDebug(),
              $webform_submission->id()
            );
          }
        } catch (\Exception $e) {
          $this->messenger()->addError($e->getMessage());
          $this->getLogger('grants_handler')->error($e->getMessage());
        }

      }
    }
  }

  /**
   * Helper to find out if we're debugging or not.
   *
   * @return bool
   *   If debug mode is on or not.
   */
  protected function isDebug(): bool {
    return !empty($this->configuration['debug']);
  }

  /**
   * Display the invoked plugin method to end user.
   *
   * @param string $method_name
   *   The invoked method name.
   * @param string $context1
   *   Additional parameter passed to the invoked method name.
   */
  protected function debug($method_name, $context1 = NULL) {
    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@id' => $this->getHandlerId(),
        '@class_name' => get_class($this),
        '@method_name' => $method_name,
        '@context1' => $context1,
      ];
      $this->messenger()
        ->addWarning($this->t('Invoked @id: @class_name:@method_name @context1', $t_args), TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    $this->debug(__FUNCTION__);
  }

  /**
   * Parse attachments from POST.
   *
   * @return array[]
   *   Parsed attchments.
   */
  private function parseAttachments($form): array {

    // $thisDocument = $this->getAtvDocument($this->applicationNumber);
    $attachmentsArray = [];
    $attachmentHeaders = GrantsAttachments::$fileTypes;
    $filenames = [];
    foreach (self::getAttachmentFieldNames() as $attachmentFieldName) {
      $field = $this->submittedFormData[$attachmentFieldName];
      $descriptionKey = self::$attachmentFieldNames[$attachmentFieldName];

      // $descriptionValue = $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#title"];
      $descriptionValue = $attachmentHeaders[$descriptionKey];

      $fileType = NULL;

      // Since we have to support multiple field elements, we need to
      // handle all as they were a multifield.
      $args = [];
      if (isset($field[0]) && is_array($field[0])) {
        $args = $field;
      }
      else {
        $args[] = $field;
      }

      // Loop args & create attachement field.
      foreach ($args as $fieldElement) {
        if (is_array($fieldElement)) {

          if (isset($fieldElement["fileType"]) && $fieldElement["fileType"] !== "") {
            $fileType = $fieldElement["fileType"];
          }
          else {
            if (isset($form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#filetype"])) {
              $fileType = $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#filetype"];
            }
            else {
              $fileType = '0';
            }
          }

          $parsedArray = $this->getAttachmentByFieldValue(
            $fieldElement, $descriptionValue, $fileType);

          if (!empty($parsedArray)) {
            if (!isset($parsedArray['fileName']) || !in_array($parsedArray['fileName'], $filenames)) {
              $attachmentsArray[] = $parsedArray;
              if (isset($parsedArray['fileName'])) {
                $filenames[] = $parsedArray['fileName'];
              }
            }
          }
        }
      }
    }

    if (isset($this->submittedFormData["account_number"])) {
      $selectedAccountNumber = $this->submittedFormData["account_number"];
      $selectedCompany = $this->grantsProfileService->getSelectedCompany();
      $grantsProfileDocument = $this->grantsProfileService->getGrantsProfile($selectedCompany);
      $profileContent = $grantsProfileDocument->getContent();

      $applicationDocument = FALSE;
      try {
        $applicationDocumentResults = $this->atvService->searchDocuments([
          'transaction_id' => $this->applicationNumber,
        ]);
        $applicationDocument = reset($applicationDocumentResults);
      } catch (AtvDocumentNotFoundException|AtvFailedToConnectException|GuzzleException $e) {
      }

      $accountConfirmationExists = FALSE;
      if ($applicationDocument) {
        $filename = md5($selectedAccountNumber);

        $aa = $applicationDocument->getAttachments();

        foreach ($aa as $attachment) {
          if (str_contains($attachment['filename'], $filename)) {
            $accountConfirmationExists = TRUE;
            break;
          }
          $found = array_filter($filenames, function ($fn) use ($filename) {
            return str_contains($fn, $filename);
          });
          if (!empty($found)) {
            $accountConfirmationExists = TRUE;
            break;
          }
        }

        if (!$accountConfirmationExists) {
          $found = array_filter($attachmentsArray, function ($fn) use ($filename) {
            if (!isset($fn['fileName'])) {
              return FALSE;
            }
            return str_contains($fn['fileName'], $filename);
          });
          if (!empty($found)) {
            $accountConfirmationExists = TRUE;
          }
        }

      }

      if (!$accountConfirmationExists) {
        $selectedAccount = NULL;
        foreach ($profileContent['bankAccounts'] as $account) {
          if ($account['bankAccount'] == $selectedAccountNumber) {
            $selectedAccount = $account;
          }
        }
        $selectedAccountConfirmation = FALSE;
        if ($selectedAccount['confirmationFile']) {
          $selectedAccountConfirmation = $grantsProfileDocument->getAttachmentForFilename($selectedAccount['confirmationFile']);
        }

        if ($selectedAccountConfirmation) {
          try {
            // Get file.
            $file = $this->atvService->getAttachment($selectedAccountConfirmation['href']);
            // Add file to attachments for uploading.
            $this->attachmentFileIds[] = $file->id();
          } catch (AtvDocumentNotFoundException|AtvFailedToConnectException|GuzzleException $e) {
            $this->loggerFactory->get('grants_handler')
              ->error($e->getMessage());
            $this->messenger()
              ->addError('Bank account confirmation file attachment failed.');
          }

          $attachmentsArray[] = [
            'description' => 'Confirmation for account ' . $selectedAccount["bankAccount"],
            'fileName' => $selectedAccount["confirmationFile"],
            'isNewAttachment' => TRUE,
            'fileType' => 101,
            'isDeliveredLater' => FALSE,
            'isIncludedInOtherFile' => FALSE,
            // @todo a better way to strip host from atv url.
          ];
        }
      }
    }

    return $attachmentsArray;
  }

  /**
   * Extract attachments from form data.
   *
   * @param array $field
   *   The field parsed.
   * @param string $fieldDescription
   *   The field description from form element title.
   * @param string $fileType
   *   Filetype id from element configuration.
   *
   * @return \stdClass[]
   *   Data for JSON.
   */
  private function getAttachmentByFieldValue(array $field, string $fieldDescription, string $fileType): array {

    $retval = [
      'description' => (isset($field['description']) && $field['description'] !== "") ? $field['description'] : $fieldDescription,
    ];
    $retval['fileType'] = (int) $fileType;
    // We have uploaded file. THIS time. Not previously.
    if (isset($field['attachment']) && $field['attachment'] !== NULL && !empty($field['attachment'])) {

      $file = File::load($field['attachment']);
      if ($file) {
        // Add file id for easier usage in future.
        $this->attachmentFileIds[] = $field['attachment'];

        $retval['fileName'] = $file->getFilename();
        $retval['isNewAttachment'] = TRUE;
        $retval['isDeliveredLater'] = FALSE;
        $retval['isIncludedInOtherFile'] = FALSE;
      }
    }
    else {
      // If other filetype and no attachment already set, we don't add them to
      // retval since we don't want to fill attachments with empty other files.
      if (($fileType === "0" || $fileType === '101') && empty($field["attachmentName"])) {
        return [];
      }
      // No upload, process accordingly.
      if ($field['fileStatus'] == 'new' || empty($field['fileStatus'])) {
        if (isset($field['isDeliveredLater'])) {
          $retval['isDeliveredLater'] = $field['isDeliveredLater'] === "1";
        }
        if (isset($field['isIncludedInOtherFile'])) {
          $retval['isIncludedInOtherFile'] = $field['isIncludedInOtherFile'] === "1";
        }
      }
      if ($field['fileStatus'] === 'uploaded') {
        if (isset($field['attachmentName'])) {
          $retval['fileName'] = $field["attachmentName"];
        }
        $retval['isDeliveredLater'] = FALSE;
        $retval['isIncludedInOtherFile'] = FALSE;
        $retval['isNewAttachment'] = FALSE;
      }
      if ($field['fileStatus'] == 'deliveredLater') {
        if ($field['attachmentName']) {
          $retval['fileName'] = $field["attachmentName"];
        }
        if (isset($field['isDeliveredLater'])) {
          $retval['isDeliveredLater'] = $field['isDeliveredLater'] === "1";
        }
        else {
          $retval['isDeliveredLater'] = '0';
        }

        if (isset($field['isIncludedInOtherFile'])) {
          $retval['isIncludedInOtherFile'] = $field['isIncludedInOtherFile'] === "1";
        }
        else {
          $retval['isIncludedInOtherFile'] = '0';
        }
      }

      if (isset($field["integrationID"]) && $field["integrationID"] !== "") {
        $retval['integrationID'] = $field["integrationID"];
      }

    }
    return $retval;
  }

  /**
   * Cleans up non-array values from array structure.
   *
   * This is due to some configuration error with messages/statuses/events
   * that I'm not able to find.
   *
   * @param array|null $value
   *   Array we need to flatten.
   *
   * @return array
   *   Fixed array.
   */
  public static function cleanUpArrayValues(?array $value): array {
    $retval = [];
    if (is_array($value)) {
      foreach ($value as $k => $v) {
        if (is_array($v)) {
          $retval[] = $v;
        }
      }
    }
    return $retval;
  }

}
