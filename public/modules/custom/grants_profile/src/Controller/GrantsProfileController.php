<?php

namespace Drupal\grants_profile\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * Returns responses for Grants Profile routes.
 */
class GrantsProfileController extends ControllerBase {

  /**
   * View single application.
   *
   * @param string $document_uuid
   *   Uuid to be shown.
   *
   * @return array
   *   Build for the page.
   */
  public function viewApplication(string $document_uuid): array {

    $submissionObject = GrantsHandler::submissionObjectFromApplicationNumber($document_uuid);
    if ($submissionObject) {
      $data = $submissionObject->getData();
      $webForm = $submissionObject->getWebform();
      $submissionForm = $webForm->getSubmissionForm(['data' => $data]);
      if (!empty($data)) {
        // @todo Set up some way to show data. Is webformSubmission needed?
        // $build['#application'] = $submissionObject->getData();
        $build['#submission_form'] = $submissionForm;
      }
      else {
        \Drupal::messenger()
          ->addWarning('No data for submission: ' . $document_uuid);
      }
    }
    else {
      \Drupal::messenger()
        ->addWarning('No submission: ' . $document_uuid);
    }

    $build['#theme'] = 'view_application';

    return $build;
  }

  /**
   * Show company select form.
   *
   * @return array
   *   Build data.
   */
  public function selectCompany(): array {
    $form = \Drupal::formBuilder()
      ->getForm('Drupal\grants_profile\Form\CompanySelectForm');
    $build['#company_select_form'] = $form;

    $build['#theme'] = 'company_select';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;

  }

  /**
   * Builds the response.
   *
   * @return array|\Laminas\Diactoros\Response\RedirectResponse
   *   Data to render
   */
  public function ownProfile(): array|RedirectResponse {
    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    if ($selectedCompany == NULL) {
      $this->messenger()
        ->addError($this->t('No profile data available, select company'), TRUE);
      return new RedirectResponse('/select-company');
    }
    else {
      $profile = $grantsProfileService->getGrantsProfileContent($selectedCompany, TRUE);
      /** @var \Drupal\helfi_atv\AtvService $atvService */
      $atvService = \Drupal::service('helfi_atv.atv_service');

      try {
        // @todo Fix application search when ATV supports better methods.
        $applicationDocuments = $atvService->searchDocuments([
          'type' => 'mysterious form',
          // 'business_id' => $selectedCompany,
          'business_id' => '1234567-8',
        ]);
        $applications = [];
        /** @var \Drupal\helfi_atv\AtvDocument $document */
        foreach ($applicationDocuments as $document) {
          $transactionId = $document->getTransactionId();
          if (str_contains($transactionId, 'GRANTS-' . GrantsHandler::getAppEnv())) {
            $applications[] = (object) [
              'transaction_id' => $transactionId,
            ];
          }
        }
        $build['#applications'] = $applications;

      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      }

      $build['#profile'] = $profile;

    }

    $gpForm = \Drupal::formBuilder()
      ->getForm('Drupal\grants_profile\Form\GrantsProfileForm');
    $build['#grants_profile_form'] = $gpForm;

    $build['#theme'] = 'own_profile';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    $build['#title'] = $profile['companyName'];
    $build['#attached']['library'][] = 'grants_profile/tabs';
    return $build;
  }

  /**
   * Builds the response.
   */
  public function ownAddresses(): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);

    if (!empty($grantsProfileContent['addresses'])) {
      $build['#addresses'] = $grantsProfileContent['addresses'];
    }

    $build['#theme'] = 'own_addresses';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

  /**
   * Builds the response.
   */
  public function applicationOfficials(): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);
    $build['#officials'] =
      (isset($grantsProfile["content"]["officials"]) &&
        !empty($grantsProfile["content"]["officials"])) ?
        $grantsProfile["content"]["officials"] :
        [];

    $build['#theme'] = 'application_officials';
    return $build;
  }

  /**
   * Builds the response.
   */
  public function bankAccounts(): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);

    $build['#bank_accounts'] =
      (isset($grantsProfile["content"]["bank_accounts"]) &&
        !empty($grantsProfile["content"]["bank_accounts"])) ?
        $grantsProfile["content"]["bank_accounts"] :
        [];

    $build['#theme'] = 'bank_accounts';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

  /**
   * Get submission from ATV.
   *
   * @param string $id
   *   Document id.
   *
   * @return mixed
   *   Submission data.
   */
  protected function getSubmission($id) {

    // $document = $this->atvService->getDocument($id);
    // $replaced = str_replace("'", "\"", $document['content']);
    // $replaced = str_replace("False", "false", $replaced);
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

}
