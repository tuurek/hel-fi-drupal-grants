<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;

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
      'endpoint' => 'This is a custom endpoint.',
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // endpoint.
    $form['endpoint'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Endpoint settings'),
    ];
    $form['endpoint']['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rest API endpoint'),
      '#default_value' => $this->configuration['endpoint'],
      '#required' => TRUE,
    ];

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
    $this->configuration['endpoint'] = $form_state->getValue('endpoint');
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
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {

    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    $endpoint = getEnv('AVUSTUS2_ENDPOINT');
    $username = getEnv('AVUSTUS2_USERNAME');
    $password = getEnv('AVUSTUS2_PASSWORD');
    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@endpoint' => $endpoint,
      ];
      $this->messenger()->addMessage($this->t('DEBUG: Endpoint:: @endpoint', $t_args));
    }
    $applicationType = $form_state->getFormObject()->getEntity()->getWebform()->getThirdPartySetting('grant_metadata', 'applicationType');
    $applicationTypeID = $form_state->getFormObject()->getEntity()->getWebform()->getThirdPartySetting('grant_metadata', 'applicationTypeID');

    $applicationNumber = "DRUPAL-" . sprintf('%08d', $webform_submission->id());

    // Check
    $status = "Vastaanotettu";

    $actingYear = $form_state->getValue('acting_year');

    // Check
    $contactPerson = "(placeholder) Teemu Testaushenkilö";
    $street = "(placeholder) Annankatu 18 Ö 905";
    $city = "(placeholder) Helsinki";
    $postCode = "(placeholder) 00120";
    $country = "(placeholder) Suomi";

    // Check
    $applicantType = "(placeholder) 2";
    $companyNumber = "(placeholder) 5647641-1";
    $communityOfficialName = "(placeholder) TietoTesti Kh yleis 001 10062021";
    $communityOfficialNameShort = "(placeholder) TT ry";
    $registrationDate = "(placeholder) 2021-01-01T00:00:00.000Z";
    $foundingYear = "(placeholder) 2021";
    $home = "(placeholder) Helsinki";
    $webpage = "(placeholder) www.ttry.fi";
    $email = "(placeholder) tsto@ttry.fi";

    // Check
    $applicantOfficials = [
      ['name' => '(placeholder) nimi', 'role' => '1', 'email' => '(placeholder) tsto@ttry.fi', 'phone' => '1234'],
      ['name' => '(placeholder) nimi2', 'role' => '2', 'email' => '(placeholder) tsto2@ttry.fi', 'phone' => '1234']
    ];

    // Check
    $accountNumber = "FI9640231442000454";

    // Check
    $compensationTotalAmount = 0;

    $compensationPurpose = $form_state->getValue('compensation_purpose');
    $compensationExplanation = $form_state->getValue('compensation_explanation');

    $compensations = [];
    if ($form_state->getValue('subvention_type_1') == 1) {
      $compensations[] = ['subventionType' => '1', 'amount' => (float) $form_state->getValue('subvention_type_1_sum')];
      $compensationTotalAmount += (float) $form_state->getValue('subvention_type_1_sum');
    }
    if ($form_state->getValue('subvention_type_2') == 1) {
      $compensations[] = ['subventionType' => '2', 'amount' => (float) $form_state->getValue('subvention_type_2_sum')];
      $compensationTotalAmount += (float) $form_state->getValue('subvention_type_2_sum');
    }
    if ($form_state->getValue('subvention_type_3') == 1) {
      $compensations[] = ['subventionType' => '3', 'amount' => (float) $form_state->getValue('subvention_type_3_sum')];
      $compensationTotalAmount += (float) $form_state->getValue('subvention_type_3_sum');
    }
    if ($form_state->getValue('subvention_type_4') == 1) {
      $compensations[] = ['subventionType' => '4', 'amount' => (float) $form_state->getValue('subvention_type_4_sum')];
      $compensationTotalAmount += (float) $form_state->getValue('subvention_type_4_sum');
    }
    if ($form_state->getValue('subvention_type_5') == 1) {
      $compensations[] = ['subventionType' => '5', 'amount' => (float) $form_state->getValue('subvention_type_5_sum')];
      $compensationTotalAmount += (float) $form_state->getValue('subvention_type_5_sum');
    }
    if ($form_state->getValue('subvention_type_6') == 1) {
      $compensations[] = ['subventionType' => '6', 'amount' => (float) $form_state->getValue('subvention_type_6_sum')];
      $compensationTotalAmount += (float) $form_state->getValue('subvention_type_6_sum');
    }

    $otherCompensations = [];

    $otherCompensationsTotal = 0;
    foreach ($form_state->getValue('myonnetty_avustus') as $otherCompensationsArray) {
      $otherCompensations[] = [
        'issuer' => (int) $otherCompensationsArray['issuer'],
        'issuerName' => $otherCompensationsArray['issuer_name'],
        'year' => $otherCompensationsArray['year'],
        'amount' => (float) $otherCompensationsArray['amount'],
        'purpose' => $otherCompensationsArray['purpose'],
      ];
    }

    $benefitsPremises = $form_state->getValue('benefits_premises');
    $benefitsLoans = $form_state->getValue('benefits_loans');

    // Check
    $businessPurpose = "(placeholder) Meidän toimintamme tarkoituksena on että ...";
    $communityPracticesBusiness = "false";

    $membersApplicantPersonGlobal = $form_state->getValue('members_applicant_person_global');
    $membersApplicantPersonLocal = $form_state->getValue('members_applicant_person_local');
    $membersApplicantCommunityLocal = $form_state->getValue('members_applicant_community_local');
    $membersApplicantCommunityGlobal = $form_state->getValue('members_applicant_community_global');

    $membersSubdivisionPersonGlobal = 0;
    $membersSubdivisionCommunityGlobal = 0;
    $membersSubdivisionPersonLocal = 0;
    $membersSubdivisionCommunityLocal = 0;

    $membersSubcommunityPersonGlobal = 0;
    $membersSubcommunityCommunityGlobal = 0;
    $membersSubcommunityPersonLocal = 0;
    $membersSubcommunityCommunityLocal = 0;
    $feePerson = (int) $form_state->getValue('fee_person');
    $feeCommunity = (int) $form_state->getValue('fee_community');

    $additionalInformation = $form_state->getValue('additional_information');

    // Check
    $senderInfoFirstname = "Tiina";
    $senderInfoLastname = "Testaaja";
    $senderInfoPersonID = "123456-7890";
    $senderInfoUserID = "Testatii";
    $senderInfoEmail = "tiina.testaaja@testiyhdistys.fi";

    // Check
    $attachments = [
      [
        'description' => "Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakijoilta tai pankkiyhteystiedot muuttuneet) *",
        'filename' => "01_pankin_ilmoitus_tilinomistajast_.docx",
        'filetype' => 1
      ], [
        'description' => "2 Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakijoilta tai pankkiyhteystiedot muuttuneet) *",
        'filename' => "02_pankin_ilmoitus_tilinomistajast_.docx",
        'filetype' => 2
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
      ]
    ];

    $currentAddressInfoArray = [
      (object) [
        "ID" => "contactPerson",
        "label" => "Yhteyshenkilö",
        "value" => $contactPerson,
        "valueType" => "string"
      ],
      (object) [
        "ID" => "street",
        "label" => "Katuosoite",
        "value" => $street,
        "valueType" => "string"
      ],
      (object) [
        "ID" => "city",
        "label" => "Postitoimipaikka",
        "value" => $city,
        "valueType" => "string"
      ],
      (object) [
        "ID" => "postCode",
        "label" => "Postinumero",
        "value" => $postCode,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "country",
        "label" => "Maa",
        "value" => $country,
        "valueType" => "string"
      ],
    ];

    $applicantInfoArray = [
      (object) [
        "ID" => "applicantType",
        "label" => "Hakijan tyyppi",
        "value" => $applicantType,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "companyNumber",
        "label" => "Rekisterinumero",
        "value" => $companyNumber,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "communityOfficialName",
        "label" => "Yhteisön nimi",
        "value" => $communityOfficialName,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "communityOfficialNameShort",
        "label" => "Yhteisön lyhenne",
        "value" => $communityOfficialNameShort,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "registrationDate",
        "label" => "Rekisteröimispäivä",
        "value" => $registrationDate,
        "valueType" => "datetime"
	    ],
      (object) [
        "ID" => "foundingYear",
        "label" => "Perustamisvuosi",
        "value" => $foundingYear,
        "valueType" => "int"
	    ],
      (object) [
        "ID" => "home",
        "label" => "Kotipaikka",
        "value" => $home,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "homePage",
        "label" => "www-sivut",
        "value" => $webpage,
        "valueType" => "string"
	    ],
      (object) [
        "ID" => "email",
        "label" => "Sähköpostiosoite",
        "value" => $email,
        "valueType" => "string"
      ]
    ];


    $applicantOfficialsArray = [];
    foreach ($applicantOfficials as $official) {
      $applicantOfficialsArray[] = [
      (object) [
          "ID" => "email",
          "label" => "Sähköposti",
          "value" => $official['email'],
          "valueType" => "string"
        ],
        (object) [
          "ID" => "role",
          "label" => "Rooli",
          "value" => $official['role'],
          "valueType" => "string"
        ],
        (object) [
          "ID" => "name",
          "label" => "Nimi",
          "value" => $official['name'],
          "valueType" => "string"
        ],
        (object) [
          "ID" => "phone",
          "label" => "Puhelinnumero",
          "value" => $official['phone'],
          "valueType" => "string"
        ]
      ];

      $compensationArray = [];

      foreach ($compensations as $compensation) {
        $compensationArray[] = [
          (object) [
						"ID" => "subventionType",
						"label" => "Avustuslaji",
						"value" => $compensation['subventionType'],
						"valueType" => "string"
          ],
          (object) [
              "ID" => "amount",
              "label" => "Euroa",
              "value" => $compensation['amount'],
              "valueType" => "float"
          ]
          ];
      };

    	$bankAccountArray = [
        (object) [
				"ID" => "accountNumber",
				"label" => "Tilinumero",
				"value" => $accountNumber,
				"valueType" => "string"
        ]
        ];

    $compensationInfo = (object) [
			"generalInfoArray" => [
        (object) [
					"ID" => "totalAmount",
					"label" => "Haettavat avustukset yhteensä",
					"value" => $compensationTotalAmount,
					"valueType" => "float"
        ],
        (object) [
					"ID" => "purpose",
					"label" => "Haetun avustuksen käyttötarkoitus",
					"value" => $compensationPurpose,
					"valueType" => "string"
        ],
        (object) [
					"ID" => "explanation",
					"label" => "Selvitys edellisen avustuksen käytöstä",
					"value" => $compensationExplanation,
					"valueType" => "string"
        ]
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
						"valueType" => "string"
        ],
        (object) [
						"ID" => "issuerName",
						"label" => "Myöntäjän nimi",
						"value" => $compensation['issuerName'],
						"valueType" => "string"
        ],
        (object) [
						"ID" => "year",
						"label" => "Vuosi",
						"value" => $compensation['year'],
						"valueType" => "string"
        ],
        (object) [
						"ID" => "amount",
						"label" => "Euroa",
						"value" => $compensation['amount'],
						"valueType" => "float"
        ],
        (object) [
						"ID" => "purpose",
						"label" => "Tarkoitus",
						"value" => $compensation['purpose'],
						"valueType" => "string"
				  ]
				];
    }

		$otherCompensationsInfo = (object) [
			"otherCompensationsArray" =>
        $otherCompesationsArray,
			"otherCompensationsTotal" => $otherCompensationsTotal
    ];


		$benefitsInfoArray = [
      (object) [
				"ID" => "premises",
				"label" => "Tilat, jotka kaupunki on antanut korvauksetta tai vuokrannut hakijan käyttöön (osoite, pinta-ala ja tiloista maksettava vuokra €/kk",
				"value" => $benefitsPremises,
				"valueType" => "string"
        ],
        (object) [
				"ID" => "loans",
				"label" => "Kaupungilta saadut lainat ja/tai takaukset",
				"value" => $benefitsLoans,
				"valueType" => "string"
        ]
        ];


      $activitiesInfoArray = [
        (object) [
				"ID" => "businessPurpose",
				"label" => "Toiminnan tarkoitus",
				"value" => $businessPurpose,
				"valueType" => "string"
        ],
        (object) [
				"ID" => "communityPracticesBusiness",
				"label" => "Yhteisö harjoittaa liiketoimintaa",
				"value" => $communityPracticesBusiness,
				"valueType" => "bool"
        ],
        (object) [
				"ID" => "membersApplicantPersonGlobal",
				"label" => "Hakijayhteisö, henkilöjäseniä",
				"value" => $membersApplicantPersonGlobal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersApplicantPersonLocal",
				"label" => "Hakijayhteisö, helsinkiläisiä henkilöjäseniä",
				"value" => $membersApplicantPersonLocal,
				"valueType" => "int"
        ],
        (object) [
          "ID" => "membersApplicantCommunityGlobal",
          "label" => "Hakijayhteisö, yhteisöjäseniä",
          "value" => $membersApplicantCommunityGlobal,
          "valueType" => "int"
        ],
        (object) [
          "ID" => "membersApplicantCommunityLocal",
          "label" => "Hakijayhteisö, helsinkiläisiä yhteisöjäseniä",
          "value" => $membersApplicantCommunityLocal,
          "valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubdivisionPersonGlobal",
				"label" => "Alayhdistykset, henkilöjäseniä",
				"value" => $membersSubdivisionPersonGlobal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubdivisionCommunityGlobal",
				"label" => "Alayhdistykset, yhteisöjäseniä",
				"value" => $membersSubdivisionCommunityGlobal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubdivisionPersonLocal",
				"label" => "Alayhdistykset, helsinkiläisiä henkilöjäseniä",
				"value" => $membersSubdivisionPersonLocal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubdivisionCommunityLocal",
				"label" => "Alayhdistykset, helsinkiläisiä yhteisöjäseniä",
				"value" => $membersSubdivisionCommunityLocal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubcommunityPersonGlobal",
				"label" => "Alaosastot, henkilöjäseniä",
				"value" => $membersSubcommunityPersonGlobal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubcommunityCommunityGlobal",
				"label" => "Alaosastot, yhteisöjäseniä",
				"value" => $membersSubcommunityCommunityGlobal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubcommunityPersonLocal",
				"label" => "Alaosastot, helsinkiläisiä henkilöjäseniä",
				"value" => $membersSubcommunityPersonLocal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "membersSubcommunityCommunityLocal",
				"label" => "Alaosastot, helsinkiläisiä yhteisöjäseniä",
				"value" => $membersSubcommunityCommunityLocal,
				"valueType" => "int"
        ],
        (object) [
				"ID" => "feePerson",
				"label" => "Jäsenmaksun suuruus, Henkiöjäsen euroa",
				"value" => $feePerson,
				"valueType" => "float"
        ],
        (object) [
				"ID" => "feeCommunity",
				"label" => "Jäsenmaksun suuruus, Yhteisöjäsen euroa",
				"value" => $feeCommunity,
				"valueType" => "float"
        ]
        ];

    	$senderInfoArray = [
        (object) [
				"ID" => "firstname",
				"label" => "Etunimi",
				"value" => $senderInfoFirstname,
				"valueType" => "string"
        ],
        (object) [
				"ID" => "lastname",
				"label" => "Sukunimi",
				"value" => $senderInfoLastname,
				"valueType" => "string"
        ],
        (object) [
				"ID" => "personID",
				"label" => "Henkilötunnus",
				"value" => $senderInfoPersonID,
				"valueType" => "string"
        ],
        (object) [
				"ID" => "userID",
				"label" => "Käyttäjätunnus",
				"value" => $senderInfoUserID,
				"valueType" => "string"
        ],
        (object) [
				"ID" => "email",
				"label" => "Sähköposti",
				"value" => $senderInfoEmail,
				"valueType" => "string"
        ]
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
        "valueType" => "string"
      ],
      (object) [
        "ID" => "fileName",
        "value" => $attachment['filename'],
        "valueType" => "string"
      ],
      (object) [
        "ID" => "fileType",
        "value" => $attachment['filetype'],
        "valueType" => "int"
      ]
    ];
  }
    $attachmentsInfoObject = [
      "attachmentsArray" => $attachmentsArray
    ];
    $submitObject = (object) ['compensation' => $compensationObject, 'attachmentsInfo' => $attachmentsInfoObject];
    $submitObject->attachmentsInfo = $attachmentsInfoObject;
    $submitObject->formUpdate = FALSE;
    $myJSON = json_encode($submitObject, JSON_UNESCAPED_UNICODE);
      echo $myJSON;
      die();
    $client = \Drupal::httpClient();
    $request = $client->post($endpoint, [
      'auth' => [$username, $password, "Basic"],
      'body' => $myJSON,
    ]);

    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@response' => $request->getBody()->getContents(),
      ];
      $this->messenger()->addMessage($this->t('DEBUG: Response from the endpoint: @response', $t_args));
    }

    $this->debug(__FUNCTION__);
  }
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
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->debug(__FUNCTION__, $update ? 'update' : 'insert');
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
