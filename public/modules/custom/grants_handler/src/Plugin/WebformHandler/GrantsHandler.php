<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\grants_attachments\AttachmentRemover;
use Drupal\grants_attachments\AttachmentUploader;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    'vahvistettu_tilinpaatos',
    'vahvistettu_toimintakertomus',
    'vahvistettu_tilin_tai_toiminnantarkastuskertomus',
    'vuosikokouksen_poytakirja',
    'toimintasuunnitelma',
    'talousarvio',
    'muu_liite',
  ];

  /**
   * Holds application statuses in.
   *
   * @var string[]
   */
  private array $applicationStatuses = [
    'DRAFT',
    'FINALIZED',
    'SENT',
    'RECEIVED',
    'PENDING',
    'PROCESSING',
    'READY',
    'DONE',
    'REJECTED',
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
   * @var \Drupal\helfi_atv\AtvService
   */
  // Protected AtvService $atvService;.

  /**
   * Access ATV backend.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

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
    // $instance->atvService = $container->get('helfi_atv.atv_service');

    /** @var \Drupal\grants_metadata\AtvSchema atvSchema */
    $instance->atvSchema = $container->get('grants_metadata.atv_schema');
    $instance->atvSchema->setSchema(getenv('ATV_SCHEMA_PATH'));

    return $instance;
  }

  /**
   * Get file fields.
   *
   * @return string[]
   *   Attachment fields.
   */
  public static function getAttachmentFieldNames(): array {
    return self::$attachmentFieldNames;
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

    return 'GRANTS-' . $appParam . '-' . sprintf('%08d', $submission->id());
  }

  /**
   * Generate application number from submission id.
   *
   * @param string $applicationNumber
   *   String to try and parse submission id from. Ie GRANTS-DEV-00000098.
   *
   * @return \Drupal\webform\Entity\WebformSubmission|null
   *   Webform submission.
   */
  public static function submissionObjectFromApplicationNumber(string $applicationNumber): ?WebformSubmission {

    $exploded = explode('-', $applicationNumber);
    $number = end($exploded);
    $submissionId = ltrim($number, '0');
    return WebformSubmission::load((integer) $submissionId);
  }

  /**
   * Set up sender details from helsinkiprofiili data.
   */
  private function parseSenderDetails() {
    // Set sender information after save so no accidental saving of data.
    // @todo Think about how sender info should be parsed, maybe in own.
    $userProfileData = $this->userExternalData->getUserProfileData();

    // If no userprofile data, we need to hardcode these values.
    // @todo Remove hardcoded values when tunnistamo works.
    if ($userProfileData == NULL) {
      $this->submittedFormData['sender_firstname'] = 'NoTunnistamo';
      $this->submittedFormData['sender_lastname'] = 'NoTunnistamo';
      $this->submittedFormData['sender_person_id'] = 'NoTunnistamo';
      $this->submittedFormData['sender_user_id'] = 'NoTunnistamo';
      $this->submittedFormData['sender_email'] = 'NoTunnistamo';

    }
    else {
      $userData = $this->userExternalData->getUserData();
      $this->submittedFormData['sender_firstname'] = $userProfileData["myProfile"]["verifiedPersonalInformation"]["firstName"];
      $this->submittedFormData['sender_lastname'] = $userProfileData["myProfile"]["verifiedPersonalInformation"]["lastName"];
      $this->submittedFormData['sender_person_id'] = $userProfileData["myProfile"]["verifiedPersonalInformation"]["nationalIdentificationNumber"];
      $this->submittedFormData['sender_user_id'] = $userData["sub"];
      $this->submittedFormData['sender_email'] = $userProfileData["myProfile"]["primaryEmail"]["email"];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    parent::validateForm($form, $form_state, $webform_submission);

    // Get current page.
    $currentPage = $form["progress"]["#current_page"];

    // 1_hakijan_tiedot
    // 2_avustustiedot
    // 3_yhteison_tiedot
    // lisatiedot_ja_liitteet
    // webform_preview
    $this->submittedFormData = $webform_submission->getData();

    // Only validate set forms.
    if ($currentPage === 'lisatiedot_ja_liitteet' || $currentPage === 'webform_preview') {
      // Loop through fieldnames and validate fields.
      foreach (self::$attachmentFieldNames as $fieldName) {
        $fValues = $form_state->getValue($fieldName);
        if (isset($fValues['fileStatus']) && $fValues['fileStatus'] == 'new') {
          $this->validateAttachmentField(
            $fieldName,
            $form_state,
            $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$fieldName]["#title"]
          );
        }
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
      if ($fieldName !== 'muu_liite' && $value === NULL) {
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
  public function preSave(WebformSubmissionInterface $webform_submission) {

    $this->submittedFormData = $webform_submission->getData();
    $this->setTotals();
    $this->parseSenderDetails();

    // Set submission data to empty.
    // form will still contain submission details, IP time etc etc.
    $webform_submission->setData([]);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->applicationType = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');
    $this->applicationTypeID = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationTypeID');

    if ($webform_submission->isDefaultRevision()) {

      $this->applicationNumber = self::createApplicationNumber($webform_submission);
      $this->submittedFormData['form_timestamp'] = (string) $webform_submission->getCreatedTime();

      $this->submittedFormData['status'] = 'SUBMITTED';
      $this->submittedFormData['application_type_id'] = $this->applicationTypeID;
      $this->submittedFormData['application_type'] = $this->applicationType;
      $this->submittedFormData['application_number'] = $this->applicationNumber;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission) {

    $dataDefinition = YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus');

    $typeManager = $dataDefinition->getTypedDataManager();
    $applicationData = $typeManager->create($dataDefinition);

    $this->submittedFormData['attachments'] = $this->parseAttachments($form);

    $applicationData->setValue($this->submittedFormData);
    $violations = $applicationData->validate();

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

          if ($status === 201) {
            $attachmentResult = $this->attachmentUploader->uploadAttachments(
              $this->attachmentFileIds,
              $this->applicationNumber,
              $this->isDebug()
            );

            // TÄHÄN TSEKKAA RESULTTI.
            // @todo print message for every attachment
            $this->messenger()
              ->addStatus('Grant application saved and attachments saved, see application status from [omat_sivut]');

            $this->attachmentRemover->removeGrantAttachments(
              $this->attachmentFileIds,
              $attachmentResult,
              $this->applicationNumber,
              $this->isDebug(),
              $webform_submission->id()
            );
          }
        }
        catch (\Exception $e) {
          $this->messenger()->addError($e->getMessage());
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

    $attachmentsArray = [];
    foreach (self::$attachmentFieldNames as $attachmentFieldName) {
      $field = $this->submittedFormData[$attachmentFieldName];
      $descriptionValue = $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#title"];

      if (isset($form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#filetype"])) {
        $fileType = $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#filetype"];
      }
      else {
        $fileType = '0';
      }

      // Since we have to support multiple field elements, we need to
      // handle all as they were a multifield.
      $args = [];
      if (isset($field[0]) && is_array($field[0])) {
        $args = $field;
      }
      else {
        $args[] = $field;
      }

      // Lppt args & create attachement field.
      foreach ($args as $fieldElement) {
        if (is_array($fieldElement)) {
          $attachmentsArray[] = $this->getAttachmentByFieldValue(
            $fieldElement, $descriptionValue, $fileType);
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
      'description' => (isset($field['description']) && $field['description'] !== "") ? $fieldDescription . ': ' . $field['description'] : $fieldDescription,
    ];

    if (isset($field['attachment']) && $field['attachment'] !== NULL && !empty($field['attachment'])) {
      $file = File::load($field['attachment']);
      // Add file id for easier usage in future.
      $this->attachmentFileIds[] = $field['attachment'];

      $retval['fileName'] = $file->getFilename();
      $retval['isNewAttachment'] = TRUE;
      $retval['fileType'] = (int) $fileType;

    }
    if (isset($field['isDeliveredLater'])) {
      $retval['isDeliveredLater'] = $field['isDeliveredLater'] === "1";
    }
    if (isset($field['isIncludedInOtherFile'])) {
      $retval['isIncludedInOtherFile'] = $field['isIncludedInOtherFile'] === "1";
    }
    return $retval;

  }

}
