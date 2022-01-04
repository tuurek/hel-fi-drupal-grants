<?php

namespace Drupal\grants_handler;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\helfi_atv\AtvService;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GrantsHandlerSubmissionStorage extends \Drupal\webform\WebformSubmissionStorage
{

  /** @var AtvService */
  protected $atvService;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface  $container,
    EntityTypeInterface $entity_type): WebformSubmissionStorage|\Drupal\Core\Entity\EntityHandlerInterface
  {
    /** @var WebformSubmissionStorage $instance */
    $instance = parent::createInstance($container, $entity_type);

    $instance->atvService = $container->get('helfi_atv.atv_service');


    return $instance;
  }

  /**
   * Save webform submission data from the 'webform_submission_data' table.
   *
   * @param array $webform_submissions
   *   An array of webform submissions.
   */
  protected function loadData(array &$webform_submissions)
  {
    parent::loadData($webform_submissions);

    /** @var WebformSubmission $submission */
    foreach ($webform_submissions as $submission) {
//      $data = $this->getSubmission($submission->get('uuid')->value);
      $data = $this->getSubmission('e5ed6430-4059-4284-859f-50137a1eee53');
      $webForm = $submission->getWebform();
//      $f = $webForm->getElementsDecodedAndFlattened();

      $appData = $this->mapApplicationToSubmission($data);

      $submission->setData($appData);
    }
  }

  protected function getSubmission($id)
  {

//    $document = $this->atvService->getDocument($id);
//    $replaced = str_replace("'", "\"", $document['content']);
//    $replaced = str_replace("False", "false", $replaced);

    $replaced = '{
  "compensation": {
    "applicationInfoArray": [
      {
        "ID": "applicationType",
        "label": "Hakemustyyppi",
        "value": "ECONOMICGRANTAPPLICATION",
        "valueType": "string"
      },
      {
        "ID": "applicationTypeID",
        "label": "Hakemustyypin numero",
        "value": "29",
        "valueType": "int"
      },
      {
        "ID": "formTimeStamp",
        "label": "Hakemuksen/sanoman lähetyshetki",
        "value": "2022-01-04T08:42:46.000Z",
        "valueType": "datetime"
      },
      {
        "ID": "applicationNumber",
        "label": "Hakemusnumero",
        "value": "DRUPAL-00000007",
        "valueType": "string"
      },
      {
        "ID": "status",
        "label": "Tila",
        "value": "Vastaanotettu",
        "valueType": "string"
      },
      {
        "ID": "actingYear",
        "label": "Hakemusvuosi",
        "value": "2022",
        "valueType": "int"
      }
    ],
    "currentAddressInfoArray": [
      {
        "ID": "contactPerson",
        "label": "Yhteyshenkilö",
        "value": "jfghjw",
        "valueType": "string"
      },
      {
        "ID": "phoneNumber",
        "label": "Puhelinnumero",
        "value": "kjh",
        "valueType": "string"
      },
      {
        "ID": "street",
        "label": "Katuosoite",
        "value": "lkjh",
        "valueType": "string"
      },
      {
        "ID": "city",
        "label": "Postitoimipaikka",
        "value": "öljkh",
        "valueType": "string"
      },
      {
        "ID": "postCode",
        "label": "Postinumero",
        "value": "ökjh",
        "valueType": "string"
      },
      {
        "ID": "country",
        "label": "Maa",
        "value": "kjh",
        "valueType": "string"
      }
    ],
    "applicantInfoArray": [
      {
        "ID": "applicantType",
        "label": "Hakijan tyyppi",
        "value": "2",
        "valueType": "string"
      },
      {
        "ID": "companyNumber",
        "label": "Rekisterinumero",
        "value": "4015026-5",
        "valueType": "string"
      },
      {
        "ID": "communityOfficialName",
        "label": "Yhteisön nimi",
        "value": "Oonan testiyhdistys syyskuu ry",
        "valueType": "string"
      },
      {
        "ID": "communityOfficialNameShort",
        "label": "Yhteisön lyhenne",
        "value": "jfhg",
        "valueType": "string"
      },
      {
        "ID": "registrationDate",
        "label": "Rekisteröimispäivä",
        "value": "17.09.2020",
        "valueType": "datetime"
      },
      {
        "ID": "foundingYear",
        "label": "Perustamisvuosi",
        "value": "2020",
        "valueType": "int"
      },
      {
        "ID": "home",
        "label": "Kotipaikka",
        "value": "HELSINKI",
        "valueType": "string"
      },
      {
        "ID": "homePage",
        "label": "www-sivut",
        "value": "www.yle.fi",
        "valueType": "string"
      },
      {
        "ID": "email",
        "label": "Sähköpostiosoite",
        "value": "email@domain.com",
        "valueType": "string"
      }
    ],
    "applicantOfficialsArray": [
    [
        {
          "ID": "email",
          "label": "Sähköposti",
          "value": "a@d.com",
          "valueType": "string"
        },
        {
          "ID": "role",
          "label": "Rooli",
          "value": "3",
          "valueType": "string"
        },
        {
          "ID": "name",
          "label": "Nimi",
          "value": "asdf",
          "valueType": "string"
        },
        {
          "ID": "phone",
          "label": "Puhelinnumero",
          "value": "asdfasdf",
          "valueType": "string"
        }
      ],

    [
        {
          "ID": "email",
          "label": "Sähköposti",
          "value": "asdf@d.com",
          "valueType": "string"
        },
        {
          "ID": "role",
          "label": "Rooli",
          "value": "2",
          "valueType": "string"
        },
        {
          "ID": "name",
          "label": "Nimi",
          "value": "poiuflaskdjf öaslkdfh ",
          "valueType": "string"
        },
        {
          "ID": "phone",
          "label": "Puhelinnumero",
          "value": "354654324354",
          "valueType": "string"
        }
      ]
      ],
    "bankAccountArray": [
      {
        "ID": "accountNumber",
        "label": "Tilinumero",
        "value": "3245-2345",
        "valueType": "string"
      }
    ],
    "compensationInfo": {
      "generalInfoArray": [
        {
          "ID": "totalAmount",
          "label": "Haettavat avustukset yhteensä",
          "value": "0111",
          "valueType": "float"
        },
        {
          "ID": "noCompensationPreviousYear",
          "label": "Olen saanut Helsingin kaupungilta avustusta samaan käyttötarkoitukseen edellisenä vuonna",
          "value": "true",
          "valueType": "string"
        },
        {
          "ID": "purpose",
          "label": "Haetun avustuksen käyttötarkoitus",
          "value": "asdasdfasdf",
          "valueType": "string"
        },
        {
          "ID": "explanation",
          "label": "Selvitys edellisen avustuksen käytöstä",
          "value": "asdfasdf asdfasdf asdf",
          "valueType": "string"
        }
      ],
      "compensationArray": [
        [
          {
            "ID": "subventionType",
            "label": "Avustuslaji",
            "value": "6",
            "valueType": "string"
          },
          {
            "ID": "amount",
            "label": "Euroa",
            "value": "111",
            "valueType": "float"
          }
        ]
      ]
    },
    "otherCompensationsInfo": {
      "otherCompensationsArray": [
        [
          {
            "ID": "issuer",
            "label": "Myöntäjä",
            "value": "2",
            "valueType": "string"
          },
          {
            "ID": "issuerName",
            "label": "Myöntäjän nimi",
            "value": "asdf asdfasdf",
            "valueType": "string"
          },
          {
            "ID": "year",
            "label": "Vuosi",
            "value": "2222",
            "valueType": "string"
          },
          {
            "ID": "amount",
            "label": "Euroa",
            "value": "2222",
            "valueType": "float"
          },
          {
            "ID": "purpose",
            "label": "Tarkoitus",
            "value": "dsfasdfasdfasdf",
            "valueType": "string"
          }
        ],
        [
          {
            "ID": "issuer",
            "label": "Myöntäjä",
            "value": "4",
            "valueType": "string"
          },
          {
            "ID": "issuerName",
            "label": "Myöntäjän nimi",
            "value": "asdfasdfasdf",
            "valueType": "string"
          },
          {
            "ID": "year",
            "label": "Vuosi",
            "value": "3333",
            "valueType": "string"
          },
          {
            "ID": "amount",
            "label": "Euroa",
            "value": "3333",
            "valueType": "float"
          },
          {
            "ID": "purpose",
            "label": "Tarkoitus",
            "value": "asdfasdf",
            "valueType": "string"
          }
        ]
      ],
      "otherCompensationsTotal": "022223333"
    },
    "benefitsInfoArray": [
      {
        "ID": "premises",
        "label": "Tilat, jotka kaupunki on antanut korvauksetta tai vuokrannut hakijan käyttöön (osoite, pinta-ala ja tiloista maksettava vuokra €/kk",
        "value": " asdfasdf adfasfasdf",
        "valueType": "string"
      },
      {
        "ID": "loans",
        "label": "Kaupungilta saadut lainat ja/tai takaukset",
        "value": "sdafads asdfasdfasdf",
        "valueType": "string"
      }
    ],
    "activitiesInfoArray": [
      {
        "ID": "businessPurpose",
        "label": "Toiminnan tarkoitus",
        "value": "Meidän toimintamme tarkoituksena on että ...",
        "valueType": "string"
      },
      {
        "ID": "communityPracticesBusiness",
        "label": "Yhteisö harjoittaa liiketoimintaa",
        "value": "false",
        "valueType": "bool"
      },
      {
        "ID": "membersApplicantPersonGlobal",
        "label": "Hakijayhteisö, henkilöjäseniä",
        "value": "333",
        "valueType": "int"
      },
      {
        "ID": "membersApplicantPersonLocal",
        "label": "Hakijayhteisö, helsinkiläisiä henkilöjäseniä",
        "value": "3333",
        "valueType": "int"
      },
      {
        "ID": "membersApplicantCommunityGlobal",
        "label": "Hakijayhteisö, yhteisöjäseniä",
        "value": "333",
        "valueType": "int"
      },
      {
        "ID": "membersApplicantCommunityLocal",
        "label": "Hakijayhteisö, helsinkiläisiä yhteisöjäseniä",
        "value": "33",
        "valueType": "int"
      },
      {
        "ID": "feePerson",
        "label": "Jäsenmaksun suuruus, Henkiöjäsen euroa",
        "value": "333",
        "valueType": "float"
      },
      {
        "ID": "feeCommunity",
        "label": "Jäsenmaksun suuruus, Yhteisöjäsen euroa",
        "value": "333",
        "valueType": "float"
      }
    ],
    "additionalInformation": "Pellentesque sed tellus quis sapien suscipit rhoncus. Duis vitae risus bibendum, vehicula massa ac, porttitor lorem.",
    "senderInfoArray": [
      {
        "ID": "firstname",
        "label": "Etunimi",
        "value": "Nordea",
        "valueType": "string"
      },
      {
        "ID": "lastname",
        "label": "Sukunimi",
        "value": "Demo",
        "valueType": "string"
      },
      {
        "ID": "personID",
        "label": "Henkilötunnus",
        "value": "210281-9988",
        "valueType": "string"
      },
      {
        "ID": "userID",
        "label": "Käyttäjätunnus",
        "value": "UHJvZmlsZU5vZGU6NzdhMjdhZmItMzQyNi00YTMyLTk0YjEtNzY5MWNiNjAxYmU5",
        "valueType": "string"
      },
      {
        "ID": "email",
        "label": "Sähköposti",
        "value": "aki.koskinen@hel.fi",
        "valueType": "string"
      }
    ]
  },
  "attachmentsInfo": {
    "attachmentsArray": [
      [
        {
          "ID": "description",
          "value": "Vahvistettu tilinpäätös (edelliseltä päättyneeltä tilikaudelta)",
          "valueType": "string"
        },
        {
          "ID": "isDeliveredLater",
          "value": "true",
          "valueType": "bool"
        },
        {
          "ID": "isIncludedInOtherFile",
          "value": "false",
          "valueType": "bool"
        }
      ],
      [
        {
          "ID": "description",
          "value": "Vahvistettu toimintakertomus (edelliseltä päättyneeltä tilikaudelta)",
          "valueType": "string"
        },
        {
          "ID": "isDeliveredLater",
          "value": "true",
          "valueType": "bool"
        },
        {
          "ID": "isIncludedInOtherFile",
          "value": "false",
          "valueType": "bool"
        }
      ],
      [
        {
          "ID": "description",
          "value": "Vahvistettu tilin- tai toiminnantarkastuskertomus (edelliseltä päättyneeltä tilikaudelta)",
          "valueType": "string"
        },
        {
          "ID": "fileName",
          "value": "sample.pdf",
          "valueType": "string"
        },
        {
          "ID": "isNewAttachment",
          "value": "true",
          "valueType": "bool"
        },
        {
          "ID": "fileType",
          "value": 0,
          "valueType": "int"
        },
        {
          "ID": "isDeliveredLater",
          "value": "false",
          "valueType": "bool"
        },
        {
          "ID": "isIncludedInOtherFile",
          "value": "false",
          "valueType": "bool"
        }
      ],
      [
        {
          "ID": "description",
          "value": "Vuosikokouksen pöytäkirja, jossa on vahvistettu edellisen päättyneen tilikauden tilinpäätös",
          "valueType": "string"
        },
        {
          "ID": "isDeliveredLater",
          "value": "true",
          "valueType": "bool"
        },
        {
          "ID": "isIncludedInOtherFile",
          "value": "false",
          "valueType": "bool"
        }
      ],
      [
        {
          "ID": "description",
          "value": "Toimintasuunnitelma (sille vuodelle jolle haet avustusta)",
          "valueType": "string"
        },
        {
          "ID": "isDeliveredLater",
          "value": "true",
          "valueType": "bool"
        },
        {
          "ID": "isIncludedInOtherFile",
          "value": "false",
          "valueType": "bool"
        }
      ],
      [
        {
          "ID": "description",
          "value": "Talousarvio (sille vuodelle jolle haet avustusta)",
          "valueType": "string"
        },
        {
          "ID": "isDeliveredLater",
          "value": "true",
          "valueType": "bool"
        },
        {
          "ID": "isIncludedInOtherFile",
          "value": "false",
          "valueType": "bool"
        }
      ],
      [
        {
          "ID": "description",
          "value": "Muu liite",
          "valueType": "string"
        }
      ]
    ]
  },
  "formUpdate": false
}';

    $decoded = Json::decode($replaced);

    return $decoded;
  }

  protected function mapApplicationToSubmission($applicationData)
  {

    $applicationKeyValues = [
      'account_number' => $applicationData["compensation"]["bankAccountArray"][0]["value"],
      'acting_year' => $applicationData["compensation"]["applicationInfoArray"][5]["value"],
      'additional_information' => $applicationData["compensation"]["additionalInformation"],
//      'applicant_type' => $applicationData["compensation"]["applicationInfoArray"][1]["value"],
      'applicant_type' => $applicationData["compensation"]["applicantInfoArray"][0]["value"],
      'benefits_loans' => $applicationData["compensation"]["benefitsInfoArray"][1]["value"],
      'benefits_premises' => $applicationData["compensation"]["benefitsInfoArray"][0]["value"],
      'community_official_name' => $applicationData["compensation"]["applicantInfoArray"][2]["value"],
      'community_official_name_short' => $applicationData["compensation"]["applicantInfoArray"][3]["value"],
      'community_status' => '',
      'community_status_special' => '',
      'company_number' => $applicationData["compensation"]["applicantInfoArray"][1]["value"],
      'company_select' => '',
      'compensation_boolean' => $applicationData["compensation"]["compensationInfo"]["generalInfoArray"][1]["value"],
      'compensation_explanation' => $applicationData["compensation"]["compensationInfo"]["generalInfoArray"][3]["value"],
      'compensation_purpose' => $applicationData["compensation"]["compensationInfo"]["generalInfoArray"][2]["value"],
      'contact_person' => $applicationData["compensation"]["currentAddressInfoArray"][0]["value"],
      'contact_person_city' => $applicationData["compensation"]["currentAddressInfoArray"][4]["value"],
      'contact_person_country' => $applicationData["compensation"]["currentAddressInfoArray"][5]["value"],
      'contact_person_phone_number' => $applicationData["compensation"]["currentAddressInfoArray"][1]["value"],
      'contact_person_post_code' => $applicationData["compensation"]["currentAddressInfoArray"][3]["value"],
      'contact_person_street' => $applicationData["compensation"]["currentAddressInfoArray"][2]["value"],
      'email' => $applicationData["compensation"]["applicantInfoArray"][8]["value"],
      'fee_community' => $applicationData["compensation"]["activitiesInfoArray"][7]["value"],
      'fee_person' => $applicationData["compensation"]["activitiesInfoArray"][6]["value"],
      'founding_year' => $applicationData["compensation"]["applicantInfoArray"][5]["value"],
      'home' => $applicationData["compensation"]["applicantInfoArray"][6]["value"],
      'homepage' => $applicationData["compensation"]["applicantInfoArray"][7]["value"],
      'members_applicant_community_global' => $applicationData["compensation"]["activitiesInfoArray"][2]["value"],
      'members_applicant_community_local' => $applicationData["compensation"]["activitiesInfoArray"][3]["value"],
      'members_applicant_person_global' => $applicationData["compensation"]["activitiesInfoArray"][4]["value"],
      'members_applicant_person_local' => $applicationData["compensation"]["activitiesInfoArray"][5]["value"],
      'olemme_hakeneet_avustuksia_muualta_kuin_helsingin_kaupungilta' => '',
      'registration_date' => $applicationData["compensation"]["applicantInfoArray"][4]["value"],
      'registration_date_text' => $applicationData["compensation"]["applicantInfoArray"][4]["value"],
      'subventions_type_6' => $applicationData["compensation"]["compensationInfo"]["compensationArray"][0][0]["value"],
      'subventions_type_6_sum' => $applicationData["compensation"]["compensationInfo"]["compensationArray"][0][1]["value"],

    ];

    foreach ($applicationData["compensation"]["applicantOfficialsArray"] as $applicationOfficial) {
      if (!empty($applicationOfficial[0]['value'])) {
        $officialArray = [
          'official_name' => $applicationOfficial[2]['value'],
          'official_role' => $applicationOfficial[1]['value'],
          'official_email' => $applicationOfficial[0]['value'],
          'official_phone' => $applicationOfficial[3]['value'],
        ];
        $applicationKeyValues['applicant_officials'][] = $officialArray;
      }
    }
    // TODO: haetut avustukset??
    foreach ($applicationData["compensation"]["otherCompensationsInfo"]["otherCompensationsArray"] as $otherCompensation) {
      if (!empty($otherCompensation[0]['value'])) {
        $officialArray = [
          'issuer' => $otherCompensation[0]['value'],
          'issuer_name' => $otherCompensation[1]['value'],
          'year' => $otherCompensation[2]['value'],
          'amount' => $otherCompensation[3]['value'],
          'purpose' => $otherCompensation[4]['value'],
        ];
        $applicationKeyValues['myonnetty_avustus'][] = $officialArray;
      }
    }

    if (empty($applicationData["compensation"]["otherCompensationsInfo"]["otherCompensationsArray"])) {
      $applicationKeyValues['olemme_saaneet_muita_avustuksia'] = 'Ei';
    } else {
      $applicationKeyValues['olemme_saaneet_muita_avustuksia'] = 'Kyllä';
    }

    if (!empty($applicationKeyValues['compensation_explanation'])) {

    }


    return $applicationKeyValues;

  }

}
