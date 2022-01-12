<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\grants_attachments\AttachmentRemover;
use Drupal\grants_attachments\AttachmentUploader;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
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
   * @var array|string[]
   *
   * @todo get field names from form where field type is attachment.
   */
  private array $attachmentFieldNames = [
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
   * @var array|string[]
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
  protected AtvService $atvService;

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

    return $instance;
  }

  /**
   * Convert EUR format value to "double" .
   */
  private function grantsHandlerConvertToFloat(string $value): string {
    $value = str_replace(['€', ',', ' '], ['', '.', ''], $value);
    $value = (float) $value;
    return "" . $value;
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
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {
    // @todo Is parent::validateForm needed in validateForm?
    parent::validateForm($form, $form_state, $webform_submission);

    $this->applicationType = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');
    $this->applicationTypeID = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationTypeID');
    $this->applicationNumber = "DRUPAL-" . sprintf('%08d', $webform_submission->id());

    // Get current page.
    $currentPage = $form["progress"]["#current_page"];
    // Only validate set forms.
    if ($currentPage === 'lisatiedot_ja_liitteet' || $currentPage === 'webform_preview') {
      // Loop through fieldnames and validate fields.
      foreach ($this->attachmentFieldNames as $fieldName) {
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
    $this->debug(__FUNCTION__);
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

    // Get data from form submission
    // and set it to class private variable.
    $this->submittedFormData = $webform_submission->getData();

    // Do not save form data if we have debug set up.
    if (!empty($this->configuration['debug'])) {
      // Set submission data to empty.
      // form will still contain submission details, IP time etc etc.
      $webform_submission->setData([]);
    }
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $webformId = $webform_submission->getWebform()->getOriginalId();

    /** @var \Drupal\grants_metadata\AtvSchema $atvSchema */
    $atvSchema = \Drupal::service('grants_metadata.atv_schema');

    $dataDefinition = YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus');

    $typeManager = $dataDefinition->getTypedDataManager();
    $data = $typeManager->create($dataDefinition);
    $data->setValue($this->submittedFormData);

    $appDocument = $atvSchema->typedDataToDocumentContent($data);

    $oldDocument = $this->atvService->getDocument('asdfasfasfasdf');
    $oldContent = $atvSchema->getAtvDocumentContent($oldDocument);

    $attachments = $this->parseAttachments($form);

    // Process only yleisavustushakemukset.
    if ($webformId === 'yleisavustushakemus') {

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
        $this->getLogger('grants_handler')->debug('DEBUG: Sent JSON: @myJSON', $t_args);
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

            // @todo print message for every attachment
            $this->messenger()
              ->addStatus('Grant application saved and attachments saved');

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

    $this->debug(__FUNCTION__);
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
    foreach ($this->attachmentFieldNames as $attachmentFieldName) {
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
          $attachmentsArray[] = $this->getAttachmentByField($fieldElement, $descriptionValue, $fileType);
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
  private function getAttachmentByField(array $field, string $fieldDescription, string $fileType): array {

    $retval = [];

    $retval[] = (object) [
      "ID" => "description",
      "value" => (isset($field['description']) && $field['description'] !== "") ? $fieldDescription . ': ' . $field['description'] : $fieldDescription,
      "valueType" => "string",
    ];

    if (isset($field['attachment']) && $field['attachment'] !== NULL) {
      $file = File::load($field['attachment']);
      // Add file id for easier usage in future.
      $this->attachmentFileIds[] = $field['attachment'];

      $retval[] = (object) [
        "ID" => "fileName",
        "value" => $file->getFilename(),
        "valueType" => "string",
      ];
      // @todo check isNewAttachement status
      $retval[] = (object) [
        "ID" => "isNewAttachment",
        "value" => 'true',
        "valueType" => "bool",
      ];
      // @todo check attachment fileType
      $retval[] = (object) [
        "ID" => "fileType",
        "value" => (int) $fileType,
        "valueType" => "int",
      ];
    }
    if (isset($field['isDeliveredLater'])) {
      $retval[] = (object) [
        "ID" => "isDeliveredLater",
        "value" => $field['isDeliveredLater'] === "1" ? 'true' : 'false',
        "valueType" => "bool",
      ];
    }
    if (isset($field['isIncludedInOtherFile'])) {
      $retval[] = (object) [
        "ID" => "isIncludedInOtherFile",
        "value" => $field['isIncludedInOtherFile'] === "1" ? 'true' : 'false',
        "valueType" => "bool",
      ];
    }
    return $retval;

  }

}
