<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
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
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class GrantsHandler extends WebformHandlerBase {

  /**
   * Convert EUR format value to to "double" .
   */
  private function grantsHandlerConvertToFloat(string $value) {
    $value = str_replace(['€', ' '], ['', ''], $value);
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
  }

  /**
   * {@inheritdoc}
   */
  public function alterElements(array &$elements, WebformInterface $webform) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function overrideSettings(array &$settings, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
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
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $endpoint = getenv('AVUSTUS2_ENDPOINT');
    $username = getenv('AVUSTUS2_USERNAME');
    $password = getenv('AVUSTUS2_PASSWORD');
    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@endpoint' => $endpoint,
      ];
      $this->messenger()->addMessage($this->t('DEBUG: Endpoint:: @endpoint', $t_args));
    }
    $applicationType = $webform_submission->getWebform()->getThirdPartySetting('grants_metadata', 'applicationType');
    $applicationTypeID = $webform_submission->getWebform()->getThirdPartySetting('grants_metadata', 'applicationTypeID');

    $applicationNumber = "DRUPAL-" . sprintf('%08d', $webform_submission->id());

    $values = $webform_submission->getData();

    // Check.
    $status = "Vastaanotettu";

    $actingYear = "" . $values['acting_year'];

    $contactPerson = $values['contact_person'];
    $phoneNumber = $values['contact_person_phone_number'];
    $street = $values['contact_person_street'];
    $city = $values['contact_person_city'];
    $postCode = $values['contact_person_post_code'];
    $country = $values['contact_person_country'];

    $applicantType = "" . $values['applicant_type'];
    $companyNumber = $values['company_number'];
    $communityOfficialName = $values['community_official_name'];
    $communityOfficialNameShort = $values['community_official_name_short'];
    $registrationDate = $values['registration_date'];
    $foundingYear = $values['founding_year'];
    $home = $values['home'];
    $webpage = $values['webpage'];
    $email = $values['email'];

    $applicantOfficials = [];

    foreach ($values['myonnetty_avustus'] as $applicantOfficialsArray) {
      $applicantOfficials[] = [
        'name' => "" . $applicantOfficialsArray['official_name'],
        'role' => $applicantOfficialsArray['official_role'],
        'email' => $applicantOfficialsArray['official_email'],
        'phone' => $applicantOfficialsArray['official_phone'],
      ];
    }

    $accountNumber = $values['account_number'];

    $compensationTotalAmount = 0;

    $compensationPurpose = $values['compensation_purpose'];
    $compensationBoolean = ($values['compensation_boolean'] == "Olen saanut Helsingin kaupungilta avustusta samaan käyttötarkoitukseen edellisenä vuonna." ? 'true' : 'false');
    $compensationExplanation = $values['compensation_explanation'];

    $compensations = [];
    // Toiminta-avustus.
    if (array_key_exists('subventions_type_1', $values) && $values['subventions_type_1'] == 1) {
      $compensations[] = [
        'subventionType' => '1',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_1_sum']),
      ];
      $compensationTotalAmount += (float) $this->grantsHandlerConvertToFloat($values['subventions_type_1_sum']);
    }
    // Palkkausavustus.
    if (array_key_exists('subventions_type_2', $values) && $values['subventions_type_2'] == 1) {
      $compensations[] = [
        'subventionType' => '2',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_2_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_2_sum'];
    }
    // Projektiavustus.
    if (array_key_exists('subventions_type_4', $values) && $values['subventions_type_4'] == 1) {
      $compensations[] = [
        'subventionType' => '4',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_4_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_4_sum'];
    }
    // Vuokra-avustus.
    if (array_key_exists('subventions_type_5', $values) && $values['subventions_type_5'] == 1) {
      $compensations[] = [
        'subventionType' => '5',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_5_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_5_sum'];
    }
    // Yleisavustus.
    if (array_key_exists('subventions_type_6', $values) && $values['subventions_type_6'] == 1) {
      $compensations[] = [
        'subventionType' => '6',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_6_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_6_sum'];
    }
    // Työttömien koulutusavustus.
    if (array_key_exists('subventions_type_7', $values) && $values['subventions_type_7'] == 1) {
      $compensations[] = [
        'subventionType' => '7',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_7_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_7_sum'];
    }
    // Korot ja lyhennykset.
    if (array_key_exists('subventions_type_8', $values) && $values['subventions_type_8'] == 1) {
      $compensations[] = [
        'subventionType' => '8',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_8_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_8_sum'];
    }
    // Muu.
    if (array_key_exists('subventions_type_9', $values) && $values['subventions_type_9'] == 1) {
      $compensations[] = [
        'subventionType' => '9',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_9_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_9_sum'];
    }
    // Leiriavustus.
    if (array_key_exists('subventions_type_12', $values) && $values['subventions_type_12'] == 1) {
      $compensations[] = [
        'subventionType' => '12',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_12_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_12_sum'];
    }
    // Lisäavustus.
    if (array_key_exists('subventions_type_14', $values) && $values['subventions_type_14'] == 1) {
      $compensations[] = [
        'subventionType' => '14',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_14_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_14_sum'];
    }
    // Suunnistuskartta-avustus.
    if (array_key_exists('subventions_type_15', $values) && $values['subventions_type_15'] == 1) {
      $compensations[] = [
        'subventionType' => '15',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_15_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_15_sum'];
    }
    // Toiminnan kehittämisavustus.
    if (array_key_exists('subventions_type_17', $values) && $values['subventions_type_17'] == 1) {
      $compensations[] = [
        'subventionType' => '17',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_17_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_17_sum'];
    }
    // Kehittämisavustukset / Helsingin malli.
    if (array_key_exists('subventions_type_29', $values) && $values['subventions_type_29'] == 1) {
      $compensations[] = [
        'subventionType' => '29',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_29_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_29_sum'];
    }
    // Starttiavustus.
    if (array_key_exists('subventions_type_31', $values) && $values['subventions_type_31'] == 1) {
      $compensations[] = [
        'subventionType' => '31',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_31_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_31_sum'];
    }
    // Tilankäyttöavustus.
    if (array_key_exists('subventions_type_32', $values) && $values['subventions_type_32'] == 1) {
      $compensations[] = [
        'subventionType' => '32',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_32_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_32_sum'];
    }
    // Taiteen perusopetus.
    if (array_key_exists('subventions_type_34', $values) && $values['subventions_type_34'] == 1) {
      $compensations[] = [
        'subventionType' => '34',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_34_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_34_sum'];
    }
    // Varhaiskasvatus.
    if (array_key_exists('subventions_type_35', $values) && $values['subventions_type_35'] == 1) {
      $compensations[] = [
        'subventionType' => '35',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_35_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_35_sum'];
    }
    // Vapaa sivistystyö.
    if (array_key_exists('subventions_type_36', $values) && $values['subventions_type_36'] == 1) {
      $compensations[] = [
        'subventionType' => '36',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_36_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_36_sum'];
    }
    // Tapahtuma-avustus.
    if (array_key_exists('subventions_type_37', $values) && $values['subventions_type_37'] == 1) {
      $compensations[] = [
        'subventionType' => '37',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_37_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_37_sum'];
    }
    // Pienavustus.
    if (array_key_exists('subventions_type_38', $values) && $values['subventions_type_38'] == 1) {
      $compensations[] = [
        'subventionType' => '38',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_38_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_38_sum'];
    }
    // Kotouttamisavustus.
    if (array_key_exists('subventions_type_39', $values) && $values['subventions_type_39'] == 1) {
      $compensations[] = [
        'subventionType' => '39',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_39_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_39_sum'];
    }
    // Harrastushaku.
    if (array_key_exists('subventions_type_40', $values) && $values['subventions_type_40'] == 1) {
      $compensations[] = [
        'subventionType' => '40',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_40_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_40_sum'];
    }
    // Laitosavustus.
    if (array_key_exists('subventions_type_41', $values) && $values['subventions_type_41'] == 1) {
      $compensations[] = [
        'subventionType' => '41',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_41_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_41_sum'];
    }
    // Muiden liikuntaa edistävien yhteisöjen avustus.
    if (array_key_exists('subventions_type_42', $values) && $values['subventions_type_42'] == 1) {
      $compensations[] = [
        'subventionType' => '42',
        'amount' => $this->grantsHandlerConvertToFloat($values['subventions_type_42_sum']),
      ];
      $compensationTotalAmount += (float) $values['subventions_type_42_sum'];
    }

    $compensationTotalAmount = $compensationTotalAmount . "";
    $otherCompensations = [];

    $otherCompensationsTotal = 0;
    foreach ($values['myonnetty_avustus'] as $otherCompensationsArray) {
      $otherCompensations[] = [
        'issuer' => "" . $otherCompensationsArray['issuer'],
        'issuerName' => $otherCompensationsArray['issuer_name'],
        'year' => $otherCompensationsArray['year'],
        'amount' => $this->grantsHandlerConvertToFloat($otherCompensationsArray['amount']),
        'purpose' => $otherCompensationsArray['purpose'],
      ];
      $otherCompensationsTotal += (float) $otherCompensationsArray['amount'];
    }
    $otherCompensationsTotal = $otherCompensationsTotal;

    $benefitsPremises = $values['benefits_premises'];
    $benefitsLoans = $values['benefits_loans'];

    // Check.
    $businessPurpose = "Meidän toimintamme tarkoituksena on että ...";
    $communityPracticesBusiness = "false";

    $membersApplicantPersonGlobal = $values['members_applicant_person_global'];
    $membersApplicantPersonLocal = $values['members_applicant_person_local'];
    $membersApplicantCommunityLocal = $values['members_applicant_community_local'];
    $membersApplicantCommunityGlobal = $values['members_applicant_community_global'];

    $feePerson = $this->grantsHandlerConvertToFloat($values['fee_person']);
    $feeCommunity = $this->grantsHandlerConvertToFloat($values['fee_community']);

    $additionalInformation = $values['additional_information'];

    // Check.
    $senderInfoFirstname = "Tiina";
    $senderInfoLastname = "Testaaja";
    $senderInfoPersonID = "123456-7890";
    $senderInfoUserID = "Testatii";
    $senderInfoEmail = "tiina.testaaja@testiyhdistys.fi";

    // Check.
    $attachments = [
      [
        'description' => "Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakijoilta tai pankkiyhteystiedot muuttuneet) *",
        'filename' => "01_pankin_ilmoitus_tilinomistajast_.docx",
        'filetype' => "1",
      ], [
        'description' => "2 Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakijoilta tai pankkiyhteystiedot muuttuneet) *",
        'filename' => "02_pankin_ilmoitus_tilinomistajast_.docx",
        'filetype' => "2",
      ],
    ];

    $applicationInfoArray = [
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

    $currentAddressInfoArray = [
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

    $applicantInfoArray = [
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

    $applicantOfficialsArray = [];
    foreach ($applicantOfficials as $official) {
      $applicantOfficialsArray[] = [
        (object) [
          "ID" => "email",
          "label" => "Sähköposti",
          "value" => $official['email'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "role",
          "label" => "Rooli",
          "value" => $official['role'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "name",
          "label" => "Nimi",
          "value" => $official['name'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "phone",
          "label" => "Puhelinnumero",
          "value" => $official['phone'],
          "valueType" => "string",
        ],
      ];
    }
    $compensationArray = [];

    foreach ($compensations as $compensation) {
      $compensationArray[] = [
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

    $bankAccountArray = [
      (object) [
        "ID" => "accountNumber",
        "label" => "Tilinumero",
        "value" => $accountNumber,
        "valueType" => "string",
      ],
    ];

    $compensationInfo = (object) [
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
      "compensationArray" => $compensationArray,

    ];

    $otherCompesationsArray = [];
    foreach ($otherCompensations as $compensation) {
      $otherCompesationsArray[] = [
        (object) [
          "ID" => "issuer",
          "label" => "Myöntäjä",
          "value" => $compensation['issuer'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "issuerName",
          "label" => "Myöntäjän nimi",
          "value" => $compensation['issuerName'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "year",
          "label" => "Vuosi",
          "value" => $compensation['year'],
          "valueType" => "string",
        ],
        (object) [
          "ID" => "amount",
          "label" => "Euroa",
          "value" => $compensation['amount'],
          "valueType" => "float",
        ],
        (object) [
          "ID" => "purpose",
          "label" => "Tarkoitus",
          "value" => $compensation['purpose'],
          "valueType" => "string",
        ],
      ];
    }

    $otherCompensationsInfo = (object) [
      "otherCompensationsArray" =>
      $otherCompesationsArray,
      "otherCompensationsTotal" => $otherCompensationsTotal,
    ];

    $benefitsInfoArray = [
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

    $activitiesInfoArray = [
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

    $senderInfoArray = [
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

    $compensationObject = (object) [
      "applicationInfoArray" => $applicationInfoArray,
      "currentAddressInfoArray" => $currentAddressInfoArray,
      "applicantInfoArray" => $applicantInfoArray,
      "applicantOfficialsArray" => $applicantOfficialsArray,
      "bankAccountArray" => $bankAccountArray,
      "compensationInfo" => $compensationInfo,
      "otherCompensationsInfo" => $otherCompensationsInfo,
      "benefitsInfoArray" => $benefitsInfoArray,
      "activitiesInfoArray" => $activitiesInfoArray,
      "additionalInformation" => $additionalInformation,
      "senderInfoArray" => $senderInfoArray,
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
    $attachmentsInfoObject = [
      "attachmentsArray" => $attachmentsArray,
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
      $this->messenger()->addMessage($this->t('DEBUG: Sent JSON: @myJSON', $t_args));

    }
    else {
      $client = \Drupal::httpClient();
      $client->post($endpoint, [
        'auth' => [$username, $password, "Basic"],
        'body' => $myJSON,
      ]);

    }

    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postLoad(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(WebformSubmissionInterface $webform_submission) {
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
  public function preSave(WebformSubmissionInterface $webform_submission) {

    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function createHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function createElement($key, array $element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateElement($key, array $element, array $original_element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteElement($key, array $element) {
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
      $this->messenger()->addWarning($this->t('Invoked @id: @class_name:@method_name @context1', $t_args), TRUE);
    }
  }

}
