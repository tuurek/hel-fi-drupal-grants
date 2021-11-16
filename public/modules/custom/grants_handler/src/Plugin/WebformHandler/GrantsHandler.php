<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
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
   * Convert EUR format value to "double" .
   */
  private function grantsHandlerConvertToFloat(string $value) {
    $value = str_replace(['€', ',', ' '], ['', '.', ''], $value);
    $value = (float) $value;
    return "" . $value;
  }

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
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
    $this->debug(__FUNCTION__);
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

    // Set submission data to empty.
    // form will still contain submission details, IP time etc etc.
    $webform_submission->setData([]);

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

      $attachmentsInfoObject = [
        "attachmentsArray" => $this->parseAttachements($this->submittedFormData),
      ];
      $submitObject = (object) [
        'compensation' => $compensationObject,
        'attachmentsInfo' => $attachmentsInfoObject,
      ];
      $submitObject->attachmentsInfo = $attachmentsInfoObject;
      $submitObject->formUpdate = FALSE;
      $myJSON = json_encode($submitObject, JSON_UNESCAPED_UNICODE);

      if (!empty($this->configuration['debug'])) {
        $t_args = [
          '@myJSON' => $myJSON,
        ];
        $this->messenger()
          ->addMessage($this->t('DEBUG: Sent JSON: @myJSON', $t_args));

      }
      else {
        /*
        $client = \Drupal::httpClient();
        $client->post($endpoint, [
        'auth' => [$username, $password, "Basic"],
        'body' => $myJSON,
        ]);
         */
      }
    }

    $this->_data_saved_succesfully = TRUE;

    $this->debug(__FUNCTION__);
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
  private function parseAttachements(): array {

    // Check.
    // @todo make attachements to come from submitted form.
    $attachments = [
      [
        'description' => "Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakijoilta tai pankkiyhteystiedot muuttuneet) *",
        'filename' => "01_pankin_ilmoitus_tilinomistajast_.docx",
        'filetype' => "1",
      ],
      [
        'description' => "2 Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakijoilta tai pankkiyhteystiedot muuttuneet) *",
        'filename' => "02_pankin_ilmoitus_tilinomistajast_.docx",
        'filetype' => "2",
      ],
    ];

    $attachmentsArray = [];
    foreach ($attachments as $attachment) {
      $attachmentsArray[] = [
        (object) [
          "ID" => "description",
          "value" => $attachment['description'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "fileName",
          "value" => $attachment['filename'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "fileType",
          "value" => $attachment['filetype'],
          "valueType" => "int",
        ],
      ];
    }

    return $attachmentsArray;
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

    // Check.
    $senderInfoFirstname = "Tiina";
    $senderInfoLastname = "Testaaja";
    $senderInfoPersonID = "123456-7890";
    $senderInfoUserID = "Testatii";
    $senderInfoEmail = "tiina.testaaja@testiyhdistys.fi";

    return [
      (object) [
        "ID" => "firstname",
        "label" => "Etunimi",
        "value" => $senderInfoFirstname,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "lastname",
        "label" => "Sukunimi",
        "value" => $senderInfoLastname,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "personID",
        "label" => "Henkilötunnus",
        "value" => $senderInfoPersonID,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "userID",
        "label" => "Käyttäjätunnus",
        "value" => $senderInfoUserID,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "email",
        "label" => "Sähköposti",
        "value" => $senderInfoEmail,
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
    $registrationDate = $this->submittedFormData['registration_date'];
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

    $applicationType = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');
    $applicationTypeID = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationTypeID');
    $applicationNumber = "DRUPAL-" . sprintf('%08d', $webform_submission->id());

    // Check.
    // @todo Check status.
    $status = "Vastaanotettu";

    $actingYear = "" . $this->submittedFormData['acting_year'];

    return [
      (object) [
        "ID" => "applicationType",
        "label" => "Hakemustyyppi",
        "value" => $applicationType,
        "valueType" => "string",
      ],
      (object) [
        "ID" => "applicationTypeID",
        "label" => "Hakemustyypin numero",
        "value" => $applicationTypeID,
        "valueType" => "int",
      ],
      (object) [
        "ID" => "formTimeStamp",
        "label" => "Hakemuksen/sanoman lähetyshetki",
        "value" => gmdate("Y-m-d\TH:i:s.v\Z", $webform_submission->getCreatedTime()),
        "valueType" => "datetime",
      ],
      (object) [
        "ID" => "applicationNumber",
        "label" => "Hakemusnumero",
        "value" => $applicationNumber,
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
    $compensationTotalAmount = 0;

    // Toiminta-avustus.
    if (array_key_exists('subventions_type_1', $this->submittedFormData) && $this->submittedFormData['subventions_type_1'] == 1) {
      $compensations[] = [
        'subventionType' => '1',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_1_sum']),
      ];
      $compensationTotalAmount += (float) $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_1_sum']);
    }
    // Palkkausavustus.
    if (array_key_exists('subventions_type_2', $this->submittedFormData) && $this->submittedFormData['subventions_type_2'] == 1) {
      $compensations[] = [
        'subventionType' => '2',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_2_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_2_sum'];
    }
    // Projektiavustus.
    if (array_key_exists('subventions_type_4', $this->submittedFormData) && $this->submittedFormData['subventions_type_4'] == 1) {
      $compensations[] = [
        'subventionType' => '4',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_4_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_4_sum'];
    }
    // Vuokra-avustus.
    if (array_key_exists('subventions_type_5', $this->submittedFormData) && $this->submittedFormData['subventions_type_5'] == 1) {
      $compensations[] = [
        'subventionType' => '5',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_5_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_5_sum'];
    }
    // Yleisavustus.
    if (array_key_exists('subventions_type_6', $this->submittedFormData) && $this->submittedFormData['subventions_type_6'] == 1) {
      $compensations[] = [
        'subventionType' => '6',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_6_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_6_sum'];
    }
    // Työttömien koulutusavustus.
    if (array_key_exists('subventions_type_7', $this->submittedFormData) && $this->submittedFormData['subventions_type_7'] == 1) {
      $compensations[] = [
        'subventionType' => '7',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_7_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_7_sum'];
    }
    // Korot ja lyhennykset.
    if (array_key_exists('subventions_type_8', $this->submittedFormData) && $this->submittedFormData['subventions_type_8'] == 1) {
      $compensations[] = [
        'subventionType' => '8',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_8_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_8_sum'];
    }
    // Muu.
    if (array_key_exists('subventions_type_9', $this->submittedFormData) && $this->submittedFormData['subventions_type_9'] == 1) {
      $compensations[] = [
        'subventionType' => '9',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_9_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_9_sum'];
    }
    // Leiriavustus.
    if (array_key_exists('subventions_type_12', $this->submittedFormData) && $this->submittedFormData['subventions_type_12'] == 1) {
      $compensations[] = [
        'subventionType' => '12',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_12_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_12_sum'];
    }
    // Lisäavustus.
    if (array_key_exists('subventions_type_14', $this->submittedFormData) && $this->submittedFormData['subventions_type_14'] == 1) {
      $compensations[] = [
        'subventionType' => '14',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_14_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_14_sum'];
    }
    // Suunnistuskartta-avustus.
    if (array_key_exists('subventions_type_15', $this->submittedFormData) && $this->submittedFormData['subventions_type_15'] == 1) {
      $compensations[] = [
        'subventionType' => '15',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_15_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_15_sum'];
    }
    // Toiminnan kehittämisavustus.
    if (array_key_exists('subventions_type_17', $this->submittedFormData) && $this->submittedFormData['subventions_type_17'] == 1) {
      $compensations[] = [
        'subventionType' => '17',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_17_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_17_sum'];
    }
    // Kehittämisavustukset / Helsingin malli.
    if (array_key_exists('subventions_type_29', $this->submittedFormData) && $this->submittedFormData['subventions_type_29'] == 1) {
      $compensations[] = [
        'subventionType' => '29',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_29_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_29_sum'];
    }
    // Starttiavustus.
    if (array_key_exists('subventions_type_31', $this->submittedFormData) && $this->submittedFormData['subventions_type_31'] == 1) {
      $compensations[] = [
        'subventionType' => '31',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_31_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_31_sum'];
    }
    // Tilankäyttöavustus.
    if (array_key_exists('subventions_type_32', $this->submittedFormData) && $this->submittedFormData['subventions_type_32'] == 1) {
      $compensations[] = [
        'subventionType' => '32',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_32_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_32_sum'];
    }
    // Taiteen perusopetus.
    if (array_key_exists('subventions_type_34', $this->submittedFormData) && $this->submittedFormData['subventions_type_34'] == 1) {
      $compensations[] = [
        'subventionType' => '34',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_34_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_34_sum'];
    }
    // Varhaiskasvatus.
    if (array_key_exists('subventions_type_35', $this->submittedFormData) && $this->submittedFormData['subventions_type_35'] == 1) {
      $compensations[] = [
        'subventionType' => '35',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_35_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_35_sum'];
    }
    // Vapaa sivistystyö.
    if (array_key_exists('subventions_type_36', $this->submittedFormData) && $this->submittedFormData['subventions_type_36'] == 1) {
      $compensations[] = [
        'subventionType' => '36',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_36_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_36_sum'];
    }
    // Tapahtuma-avustus.
    if (array_key_exists('subventions_type_37', $this->submittedFormData) && $this->submittedFormData['subventions_type_37'] == 1) {
      $compensations[] = [
        'subventionType' => '37',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_37_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_37_sum'];
    }
    // Pienavustus.
    if (array_key_exists('subventions_type_38', $this->submittedFormData) && $this->submittedFormData['subventions_type_38'] == 1) {
      $compensations[] = [
        'subventionType' => '38',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_38_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_38_sum'];
    }
    // Kotouttamisavustus.
    if (array_key_exists('subventions_type_39', $this->submittedFormData) && $this->submittedFormData['subventions_type_39'] == 1) {
      $compensations[] = [
        'subventionType' => '39',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_39_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_39_sum'];
    }
    // Harrastushaku.
    if (array_key_exists('subventions_type_40', $this->submittedFormData) && $this->submittedFormData['subventions_type_40'] == 1) {
      $compensations[] = [
        'subventionType' => '40',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_40_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_40_sum'];
    }
    // Laitosavustus.
    if (array_key_exists('subventions_type_41', $this->submittedFormData) && $this->submittedFormData['subventions_type_41'] == 1) {
      $compensations[] = [
        'subventionType' => '41',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_41_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_41_sum'];
    }
    // Muiden liikuntaa edistävien yhteisöjen avustus.
    if (array_key_exists('subventions_type_42', $this->submittedFormData) && $this->submittedFormData['subventions_type_42'] == 1) {
      $compensations[] = [
        'subventionType' => '42',
        'amount' => $this->grantsHandlerConvertToFloat($this->submittedFormData['subventions_type_42_sum']),
      ];
      $compensationTotalAmount += (float) $this->submittedFormData['subventions_type_42_sum'];
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

      $otherCompensationsTotal += (float) $otherCompensationsData['amount'];
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
