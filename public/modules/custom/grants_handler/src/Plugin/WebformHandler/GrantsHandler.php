<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\grants_attachments\AttachmentRemover;
use Drupal\grants_attachments\AttachmentUploader;
use Drupal\grants_profile\GrantsProfileService;
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
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
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
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfile;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    /** @var \Drupal\Core\DependencyInjection\Container $container */
    $instance->attachmentUploader = $container->get('grants_attachments.attachment_uploader');
    $instance->attachmentRemover = $container->get('grants_attachments.attachment_remover');

    $instance->currentUser = $container->get('current_user');

    $instance->userExternalData = $container->get('helfi_helsinki_profiili.userdata');

    $instance->grantsProfile = $container->get('grants_profile.service');

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
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // @todo Is parent::validateForm needed in validateForm?
    parent::validateForm($form, $form_state, $webform_submission);

    // Get current page.
    $currentPage = $form["progress"]["#current_page"];
    // Only validate set forms.
    if ($currentPage === 'lisatiedot_ja_liitteet' || $currentPage === 'webform_preview') {
      // Loop through fieldnames and validate fields.
      foreach ($this->attachmentFieldNames as $fieldName) {
        $this->validateAttachmentField(
          $fieldName,
          $form_state,
          $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$fieldName]["#title"]
        );
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
        if (is_int($value['attachment'])) {
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
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $webformId = $webform_submission->getWebform()->getOriginalId();

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

      $parsedCompensations = $this->parseCompensations();

      $bankAccountArray = [
        (object) [
          "ID" => "accountNumber",
          "label" => "Tilinumero",
          "value" => $this->submittedFormData['account_number'],
          "valueType" => "string",
        ],
      ];

      // Build object for json.
      $compensationObject = (object) [
        "applicationInfoArray" => $this->parseApplicationInfo($webform_submission),
        "currentAddressInfoArray" => $this->parseCurrentAddressInfo(),
        "applicantInfoArray" => $this->parseApplicantInfo(),
        "applicantOfficialsArray" => $this->parseApplicationOfficials(),
        "bankAccountArray" => $bankAccountArray,
        "compensationInfo" => $parsedCompensations['compensationInfo'],
        "otherCompensationsInfo" => $parsedCompensations['otherCompensations'],
        "benefitsInfoArray" => $this->parseBenefitsInfo(),
        "activitiesInfoArray" => $this->parseActivitiesInfo(),
        "additionalInformation" => $this->submittedFormData['additional_information'],
        "senderInfoArray" => $this->parseSenderInfo(),
      ];
      // attachments' details.
      $attachmentsInfoObject = [
        "attachmentsArray" => $this->parseAttachments($form),
      ];
      $submitObject = (object) [
        'compensation' => $compensationObject,
        'attachmentsInfo' => $attachmentsInfoObject,
      ];
      $submitObject->attachmentsInfo = $attachmentsInfoObject;
      $submitObject->formUpdate = FALSE;
      $myJSON = json_encode($submitObject, JSON_UNESCAPED_UNICODE);

      // If debug, print out json.
      if ($this->isDebug()) {
        $t_args = [
          '@myJSON' => $myJSON,
        ];
        $this->messenger()
          ->addMessage($this->t('DEBUG: Sent JSON: @myJSON', $t_args));
      }
      // If backend mode is dev, then don't post things to backend.
      if (getenv('BACKEND_MODE') === 'dev') {
        $this->messenger()
          ->addWarning($this->t('Backend DEV mode on, no posting to backend is done.'));
      }
      else {
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
        $attachmentsArray[] = $this->getAttachmentByField($fieldElement, $descriptionValue);
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
   *
   * @return \stdClass[]
   *   Data for JSON.
   */
  private function getAttachmentByField(array $field, string $fieldDescription): array {

    $retval = [];

    $retval[] = (object) [
      "ID" => "description",
      "value" => ($field['description'] !== "") ? $fieldDescription . ': ' . $field['description'] : $fieldDescription,
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
        // "value" => $file->getMimeType(),
        "value" => 1,
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

  /**
   * Parse benefits details from POST.
   *
   * @return object[]
   *   Parsed objects for JSON request.
   */
  private function parseBenefitsInfo(): array {
    $benefitsPremises = $this->submittedFormData['benefits_premises'];
    $benefitsLoans = $this->submittedFormData['benefits_loans'];

    return [
      (object) [
        "ID" => "premises",
        "label" => "Tilat, jotka kaupunki on antanut korvauksetta tai vuokrannut hakijan käyttöön (osoite, pinta-ala ja tiloista maksettava vuokra €/kk",
        "value" => $benefitsPremises,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "loans",
        "label" => "Kaupungilta saadut lainat ja/tai takaukset",
        "value" => $benefitsLoans,
        "valueType" => "string",
      ],
    ];
  }

  /**
   * Parse sender details from POST.
   *
   * @return object[]
   *   Array of sender details for JSON.
   */
  private function parseSenderInfo(): array {

    $userData = $this->userExternalData->getUserProfileData();

    return [
      (object) [
        "ID" => "firstname",
        "label" => "Etunimi",
        "value" => $userData["myProfile"]["verifiedPersonalInformation"]["firstName"],
        "valueType" => "string",
      ],
      (object) [
        "ID" => "lastname",
        "label" => "Sukunimi",
        "value" => $userData["myProfile"]["verifiedPersonalInformation"]["lastName"],
        "valueType" => "string",
      ],
      (object) [
        "ID" => "personID",
        "label" => "Henkilötunnus",
        "value" => $userData["myProfile"]["verifiedPersonalInformation"]["nationalIdentificationNumber"],
        "valueType" => "string",
      ],
      (object) [
        "ID" => "userID",
        "label" => "Käyttäjätunnus",
        "value" => $userData["myProfile"]["id"],
        "valueType" => "string",
      ],
      (object) [
        "ID" => "email",
        "label" => "Sähköposti",
        "value" => $userData["myProfile"]["primaryEmail"]["email"],
        "valueType" => "string",
      ],
    ];
  }

  /**
   * Parse activities from POST.
   *
   * @return object[]
   *   Activities objects for JSON.
   */
  private function parseActivitiesInfo(): array {
    // Check.
    // @todo check business purpose.
    $businessPurpose = "Meidän toimintamme tarkoituksena on että ...";
    $communityPracticesBusiness = "false";

    $membersApplicantPersonGlobal = $this->submittedFormData['members_applicant_person_global'];
    $membersApplicantPersonLocal = $this->submittedFormData['members_applicant_person_local'];
    $membersApplicantCommunityLocal = $this->submittedFormData['members_applicant_community_local'];
    $membersApplicantCommunityGlobal = $this->submittedFormData['members_applicant_community_global'];

    $feePerson = $this->grantsHandlerConvertToFloat($this->submittedFormData['fee_person']);
    $feeCommunity = $this->grantsHandlerConvertToFloat($this->submittedFormData['fee_community']);

    return [
      (object) [
        "ID" => "businessPurpose",
        "label" => "Toiminnan tarkoitus",
        "value" => $businessPurpose,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "communityPracticesBusiness",
        "label" => "Yhteisö harjoittaa liiketoimintaa",
        "value" => $communityPracticesBusiness,
        "valueType" => "bool",
      ],
      (object) [
        "ID" => "membersApplicantPersonGlobal",
        "label" => "Hakijayhteisö, henkilöjäseniä",
        "value" => $membersApplicantPersonGlobal,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "membersApplicantPersonLocal",
        "label" => "Hakijayhteisö, helsinkiläisiä henkilöjäseniä",
        "value" => $membersApplicantPersonLocal,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "membersApplicantCommunityGlobal",
        "label" => "Hakijayhteisö, yhteisöjäseniä",
        "value" => $membersApplicantCommunityGlobal,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "membersApplicantCommunityLocal",
        "label" => "Hakijayhteisö, helsinkiläisiä yhteisöjäseniä",
        "value" => $membersApplicantCommunityLocal,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "feePerson",
        "label" => "Jäsenmaksun suuruus, Henkiöjäsen euroa",
        "value" => $feePerson,
        "valueType" => "float",
      ],
      (object) [
        "ID" => "feeCommunity",
        "label" => "Jäsenmaksun suuruus, Yhteisöjäsen euroa",
        "value" => $feeCommunity,
        "valueType" => "float",
      ],
    ];
  }

  /**
   * Parse address details from POST.
   *
   * @return object[]
   *   Address details objects for JSON.
   */
  private function parseCurrentAddressInfo(): array {

    $contactPerson = $this->submittedFormData['contact_person'];
    $phoneNumber = $this->submittedFormData['contact_person_phone_number'];
    $street = $this->submittedFormData['contact_person_street'];
    $city = $this->submittedFormData['contact_person_city'];
    $postCode = $this->submittedFormData['contact_person_post_code'];
    $country = $this->submittedFormData['contact_person_country'];

    return [
      (object) [
        "ID" => "contactPerson",
        "label" => "Yhteyshenkilö",
        "value" => $contactPerson,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "phoneNumber",
        "label" => "Puhelinnumero",
        "value" => $phoneNumber,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "street",
        "label" => "Katuosoite",
        "value" => $street,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "city",
        "label" => "Postitoimipaikka",
        "value" => $city,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "postCode",
        "label" => "Postinumero",
        "value" => $postCode,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "country",
        "label" => "Maa",
        "value" => $country,
        "valueType" => "string",
      ],
    ];
  }

  /**
   * PArse applicant details from POST.
   *
   * @return object[]
   *   Applicant objects for JSON.
   */
  private function parseApplicantInfo(): array {

    $applicantType = "" . $this->submittedFormData['applicant_type'];
    $companyNumber = $this->submittedFormData['company_number'];
    $communityOfficialName = $this->submittedFormData['community_official_name'];
    $communityOfficialNameShort = $this->submittedFormData['community_official_name_short'];
    $registrationDate = $this->submittedFormData['registration_date_text'];
    $foundingYear = $this->submittedFormData['founding_year'];
    $home = $this->submittedFormData['home'];
    $webpage = $this->submittedFormData['homepage'];
    $email = $this->submittedFormData['email'];

    return [
      (object) [
        "ID" => "applicantType",
        "label" => "Hakijan tyyppi",
        "value" => $applicantType,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "companyNumber",
        "label" => "Rekisterinumero",
        "value" => $companyNumber,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "communityOfficialName",
        "label" => "Yhteisön nimi",
        "value" => $communityOfficialName,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "communityOfficialNameShort",
        "label" => "Yhteisön lyhenne",
        "value" => $communityOfficialNameShort,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "registrationDate",
        "label" => "Rekisteröimispäivä",
        "value" => $registrationDate,
        "valueType" => "datetime",
      ],
      (object) [
        "ID" => "foundingYear",
        "label" => "Perustamisvuosi",
        "value" => $foundingYear,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "home",
        "label" => "Kotipaikka",
        "value" => $home,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "homePage",
        "label" => "www-sivut",
        "value" => $webpage,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "email",
        "label" => "Sähköpostiosoite",
        "value" => $email,
        "valueType" => "string",
      ],
    ];
  }

  /**
   * Parse basic application info from form values.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $webform_submission
   *   Submission object from Webform.
   *
   * @return object[]
   *   Application details for JSON.
   */
  private function parseApplicationInfo(
    WebformSubmission $webform_submission): array {

    $this->applicationType = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');
    $this->applicationTypeID = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationTypeID');
    $this->applicationNumber = "DRUPAL-" . sprintf('%08d', $webform_submission->id());

    // Check.
    // @todo Check status.
    $status = "Vastaanotettu";

    $actingYear = "" . $this->submittedFormData['acting_year'];

    return [
      (object) [
        "ID" => "applicationType",
        "label" => "Hakemustyyppi",
        "value" => $this->applicationType,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "applicationTypeID",
        "label" => "Hakemustyypin numero",
        "value" => $this->applicationTypeID,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "formTimeStamp",
        "label" => "Hakemuksen/sanoman lähetyshetki",
        "value" => gmdate("Y-m-d\TH:i:s.v\Z"),
        "valueType" => "datetime",
      ],
      (object) [
        "ID" => "applicationNumber",
        "label" => "Hakemusnumero",
        "value" => $this->applicationNumber,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "status",
        "label" => "Tila",
        "value" => $status,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "actingYear",
        "label" => "Hakemusvuosi",
        "value" => $actingYear,
        "valueType" => "int",
      ],
    ];
  }

  /**
   * Parse compensation data from form values.
   *
   * @return array[]
   *   Compensation details for JSON.
   */
  private function parseCompensations(): array {
    $compensations = [];
    $compensationTotalAmount = 0.0;

    // Toiminta-avustus.
    if (array_key_exists('subventions_type_1', $this->submittedFormData) && $this->submittedFormData['subventions_type_1'] == 1) {
      $compensations[] = [
        'subventionType' => '1',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_1_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_1_sum']);
    }
    // Palkkausavustus.
    if (array_key_exists('subventions_type_2', $this->submittedFormData) && $this->submittedFormData['subventions_type_2'] == 1) {
      $compensations[] = [
        'subventionType' => '2',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_2_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_2_sum']);
    }
    // Projektiavustus.
    if (array_key_exists('subventions_type_4', $this->submittedFormData) && $this->submittedFormData['subventions_type_4'] == 1) {
      $compensations[] = [
        'subventionType' => '4',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_4_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_4_sum']);
    }
    // Vuokra-avustus.
    if (array_key_exists('subventions_type_5', $this->submittedFormData) && $this->submittedFormData['subventions_type_5'] == 1) {
      $compensations[] = [
        'subventionType' => '5',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_5_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_5_sum']);
    }
    // Yleisavustus.
    if (array_key_exists('subventions_type_6', $this->submittedFormData) && $this->submittedFormData['subventions_type_6'] == 1) {
      $compensations[] = [
        'subventionType' => '6',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_6_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_6_sum']);
    }
    // Työttömien koulutusavustus.
    if (array_key_exists('subventions_type_7', $this->submittedFormData) && $this->submittedFormData['subventions_type_7'] == 1) {
      $compensations[] = [
        'subventionType' => '7',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_7_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_7_sum']);
    }
    // Korot ja lyhennykset.
    if (array_key_exists('subventions_type_8', $this->submittedFormData) && $this->submittedFormData['subventions_type_8'] == 1) {
      $compensations[] = [
        'subventionType' => '8',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_8_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_8_sum']);
    }
    // Muu.
    if (array_key_exists('subventions_type_9', $this->submittedFormData) && $this->submittedFormData['subventions_type_9'] == 1) {
      $compensations[] = [
        'subventionType' => '9',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_9_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_9_sum']);
    }
    // Leiriavustus.
    if (array_key_exists('subventions_type_12', $this->submittedFormData) && $this->submittedFormData['subventions_type_12'] == 1) {
      $compensations[] = [
        'subventionType' => '12',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_12_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_12_sum']);
    }
    // Lisäavustus.
    if (array_key_exists('subventions_type_14', $this->submittedFormData) && $this->submittedFormData['subventions_type_14'] == 1) {
      $compensations[] = [
        'subventionType' => '14',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_14_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_14_sum']);
    }
    // Suunnistuskartta-avustus.
    if (array_key_exists('subventions_type_15', $this->submittedFormData) && $this->submittedFormData['subventions_type_15'] == 1) {
      $compensations[] = [
        'subventionType' => '15',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_15_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_15_sum']);
    }
    // Toiminnan kehittämisavustus.
    if (array_key_exists('subventions_type_17', $this->submittedFormData) && $this->submittedFormData['subventions_type_17'] == 1) {
      $compensations[] = [
        'subventionType' => '17',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_17_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_17_sum']);
    }
    // Kehittämisavustukset / Helsingin malli.
    if (array_key_exists('subventions_type_29', $this->submittedFormData) && $this->submittedFormData['subventions_type_29'] == 1) {
      $compensations[] = [
        'subventionType' => '29',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_29_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_29_sum']);
    }
    // Starttiavustus.
    if (array_key_exists('subventions_type_31', $this->submittedFormData) && $this->submittedFormData['subventions_type_31'] == 1) {
      $compensations[] = [
        'subventionType' => '31',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_31_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_31_sum']);
    }
    // Tilankäyttöavustus.
    if (array_key_exists('subventions_type_32', $this->submittedFormData) && $this->submittedFormData['subventions_type_32'] == 1) {
      $compensations[] = [
        'subventionType' => '32',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_32_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_32_sum']);
    }
    // Taiteen perusopetus.
    if (array_key_exists('subventions_type_34', $this->submittedFormData) && $this->submittedFormData['subventions_type_34'] == 1) {
      $compensations[] = [
        'subventionType' => '34',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_34_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_34_sum']);
    }
    // Varhaiskasvatus.
    if (array_key_exists('subventions_type_35', $this->submittedFormData) && $this->submittedFormData['subventions_type_35'] == 1) {
      $compensations[] = [
        'subventionType' => '35',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_35_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_35_sum']);
    }
    // Vapaa sivistystyö.
    if (array_key_exists('subventions_type_36', $this->submittedFormData) && $this->submittedFormData['subventions_type_36'] == 1) {
      $compensations[] = [
        'subventionType' => '36',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_36_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_36_sum']);
    }
    // Tapahtuma-avustus.
    if (array_key_exists('subventions_type_37', $this->submittedFormData) && $this->submittedFormData['subventions_type_37'] == 1) {
      $compensations[] = [
        'subventionType' => '37',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_37_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_37_sum']);
    }
    // Pienavustus.
    if (array_key_exists('subventions_type_38', $this->submittedFormData) && $this->submittedFormData['subventions_type_38'] == 1) {
      $compensations[] = [
        'subventionType' => '38',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_38_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_38_sum']);
    }
    // Kotouttamisavustus.
    if (array_key_exists('subventions_type_39', $this->submittedFormData) && $this->submittedFormData['subventions_type_39'] == 1) {
      $compensations[] = [
        'subventionType' => '39',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_39_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_39_sum']);
    }
    // Harrastushaku.
    if (array_key_exists('subventions_type_40', $this->submittedFormData) && $this->submittedFormData['subventions_type_40'] == 1) {
      $compensations[] = [
        'subventionType' => '40',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_40_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_40_sum']);
    }
    // Laitosavustus.
    if (array_key_exists('subventions_type_41', $this->submittedFormData) && $this->submittedFormData['subventions_type_41'] == 1) {
      $compensations[] = [
        'subventionType' => '41',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_41_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_42_sum']);
    }
    // Muiden liikuntaa edistävien yhteisöjen avustus.
    if (array_key_exists('subventions_type_42', $this->submittedFormData) && $this->submittedFormData['subventions_type_42'] == 1) {
      $compensations[] = [
        'subventionType' => '42',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_42_sum']),
      ];
      $compensationTotalAmount .= $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_42_sum']);
    }

    $otherCompensations = [];

    $otherCompensationsTotal = 0;
    foreach ($this->submittedFormData['myonnetty_avustus'] as $otherCompensationsData) {
      $otherCompensations[] = [
        (object) [
          "ID" => "issuer",
          "label" => "Myöntäjä",
          "value" => $otherCompensationsData['issuer'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "issuerName",
          "label" => "Myöntäjän nimi",
          "value" => $otherCompensationsData['issuer_name'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "year",
          "label" => "Vuosi",
          "value" => $otherCompensationsData['year'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "amount",
          "label" => "Euroa",
          "value" => $this->grantsHandlerConvertToFloat($otherCompensationsData['amount']),
          "valueType" => "float",
        ],
        (object) [
          "ID" => "purpose",
          "label" => "Tarkoitus",
          "value" => $otherCompensationsData['purpose'],
          "valueType" => "string",
        ],
      ];

      $otherCompensationsTotal .= $this->grantsHandlerConvertToFloat($otherCompensationsData['amount']);
    }

    $compensatiosArray = [];
    foreach ($compensations as $compensation) {
      $compensatiosArray[] = [
        (object) [
          "ID" => "subventionType",
          "label" => "Avustuslaji",
          "value" => $compensation['subventionType'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "amount",
          "label" => "Euroa",
          "value" => $compensation['amount'],
          "valueType" => "float",
        ],
      ];
    };

    $compensationPurpose = $this->submittedFormData['compensation_purpose'];
    $compensationBoolean = ($this->submittedFormData['compensation_boolean'] == "Olen saanut Helsingin kaupungilta avustusta samaan käyttötarkoitukseen edellisenä vuonna." ? 'true' : 'false');
    $compensationExplanation = $this->submittedFormData['compensation_explanation'];

    $compensationInfoData = (object) [
      "generalInfoArray" => [
        (object) [
          "ID" => "totalAmount",
          "label" => "Haettavat avustukset yhteensä",
          "value" => $compensationTotalAmount,
          "valueType" => "float",
        ],
        (object) [
          "ID" => "noCompensationPreviousYear",
          "label" => "En ole saanut Helsingin kaupungilta avustusta samaan käyttötarkoitukseen edellisenä vuonna",
          "value" => $compensationBoolean,
          "valueType" => "string",
        ],

        (object) [
          "ID" => "purpose",
          "label" => "Haetun avustuksen käyttötarkoitus",
          "value" => $compensationPurpose,
          "valueType" => "string",
        ],
        (object) [
          "ID" => "explanation",
          "label" => "Selvitys edellisen avustuksen käytöstä",
          "value" => $compensationExplanation,
          "valueType" => "string",
        ],
      ],
      "compensationArray" => $compensatiosArray,
    ];

    $otherCompensationsInfoData = (object) [
      "otherCompensationsArray" =>
      $otherCompensations,
      "otherCompensationsTotal" => $otherCompensationsTotal . "",
    ];

    return [
      'compensations' => $compensations,
      'compensationArray' => $compensatiosArray,
      'compensationInfo' => $compensationInfoData,
      'otherCompensations' => $otherCompensationsInfoData,
      'compensationTotalAmount' => $compensationTotalAmount . "",
      'otherCompensationsTotal' => $otherCompensationsTotal . "",
    ];

  }

  /**
   * Parse application officials' details from form.
   *
   * @return array[]
   *   Application officials' objects for JSON.
   */
  private function parseApplicationOfficials(): array {
    $applicantOfficialsData = [];
    foreach ($this->submittedFormData['applicant_officials'] as $official) {
      $applicantOfficialsData[] = [
        (object) [
          "ID" => "email",
          "label" => "Sähköposti",
          "value" => $official['official_email'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "role",
          "label" => "Rooli",
          "value" => $official['official_role'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "name",
          "label" => "Nimi",
          "value" => $official['official_name'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "phone",
          "label" => "Puhelinnumero",
          "value" => $official['official_phone'],
          "valueType" => "string",
        ],
      ];
    }

    return $applicantOfficialsData;
  }

}
