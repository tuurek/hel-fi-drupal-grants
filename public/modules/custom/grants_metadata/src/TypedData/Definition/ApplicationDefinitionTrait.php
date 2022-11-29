<?php

namespace Drupal\grants_metadata\TypedData\Definition;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Base class for data typing & mapping.
 */
trait ApplicationDefinitionTrait {

  /**
   * Base data definitions for all.
   */
  public function getBaseProperties(): array {
    $info['applicant_type'] = DataDefinition::create('string')
      // // ->setRequired(TRUE)
      ->setLabel('Hakijan tyyppi')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'applicantType',
      ])
      ->addConstraint('NotBlank');

    $info['company_number'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Rekisterinumero')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'companyNumber',
      ])
      ->addConstraint('NotBlank');

    $info['community_official_name'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Yhteisön nimi')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'communityOfficialName',
      ])
      ->addConstraint('NotBlank');

    $info['community_official_name_short'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Yhteisön lyhenne')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'communityOfficialNameShort',
      ])
      ->addConstraint('NotBlank');

    $info['registration_date'] = DataDefinition::create('datetime_iso8601')
      // ->setRequired(TRUE)
      ->setLabel('Rekisteröimispäivä')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'registrationDate',
      ])
      ->addConstraint('NotBlank');

    $info['founding_year'] = DataDefinition::create('integer')
      // ->setRequired(TRUE)
      ->setLabel('Perustamisvuosi')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'foundingYear',
      ])
      ->addConstraint('NotBlank');

    $info['home'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Kotipaikka')
      ->setSetting('jsonPath', ['compensation', 'applicantInfoArray', 'home'])
      ->addConstraint('NotBlank');

    $info['homepage'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('www-sivut')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'homePage',
      ])
      ->setSetting('defaultValue', "");

    $info['email'] = DataDefinition::create('email')
      // ->setRequired(TRUE)
      ->setLabel('Sähköpostiosoite')
      ->setSetting('jsonPath', [
        'compensation',
        'applicantInfoArray',
        'email',
      ])
      ->setSetting('typeOverride', [
        'dataType' => 'email',
        'jsonType' => 'string',
      ])
      ->addConstraint('NotBlank')
      ->addConstraint('Email');

    $info['community_officials'] = ListDataDefinition::create('grants_profile_application_official')
      // ->setRequired(TRUE)
      ->setSetting('jsonPath', ['compensation', 'applicantOfficialsArray'])
      ->setSetting('defaultValue', [])
      ->setLabel('applicantOfficialsArray');

    $info['contact_person'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('currentAddressInfoArray=>contactPerson')
      ->setSetting('jsonPath', [
        'compensation',
        'currentAddressInfoArray',
        'contactPerson',
      ])
      ->addConstraint('NotBlank');

    $info['contact_person_phone_number'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Contact person phone')
      ->setSetting('jsonPath', [
        'compensation',
        'currentAddressInfoArray',
        'phoneNumber',
      ])
      ->addConstraint('NotBlank');

    $info['community_street'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Community street')
      ->setSetting('jsonPath', [
        'compensation',
        'currentAddressInfoArray',
        'street',
      ])
      ->setSetting('formSettings', [
        'formElement' => 'community_address',
        'formError' => 'You must select address',
      ])
      ->addConstraint('NotBlank');

    $info['community_city'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Community city')
      ->setSetting('jsonPath', [
        'compensation',
        'currentAddressInfoArray',
        'city',
      ])
      ->setSetting('formErrorElement', [
        'formElement' => 'community_address',
        'formError' => 'You must select address',
      ])
      ->addConstraint('NotBlank');

    $info['community_post_code'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Community postal code')
      ->setSetting('jsonPath', [
        'compensation',
        'currentAddressInfoArray',
        'postCode',
      ])
      ->setSetting('formErrorElement', [
        'formElement' => 'community_address',
        'formError' => 'You must select address',
      ])
      ->addConstraint('NotBlank')
      ->addConstraint('ValidPostalCode');

    $info['community_country'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Community country')
      ->setSetting('jsonPath', [
        'compensation',
        'currentAddressInfoArray',
        'country',
      ])
      ->setSetting('formErrorElement', [
        'formElement' => 'community_address',
        'formError' => 'You must select address',
      ])
      ->addConstraint('NotBlank');

    $info['application_type'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Application type')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'applicationType',
      ]);
    // ->addConstraint('NotBlank')
    $info['application_type_id'] = DataDefinition::create('string')
      ->setLabel('Application type id')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'applicationTypeID',
      ]);
    // ->setRequired(TRUE)
    // ->addConstraint('NotBlank')
    // ->addConstraint('NotEmptyValue')
    $info['form_timestamp'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('formTimeStamp')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'formTimeStamp',
      ]);

    $info['form_timestamp_created'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('createdFormTimeStamp')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'createdFormTimeStamp',
      ]);

    $info['form_timestamp_submitted'] = DataDefinition::create('string')
      ->setRequired(FALSE)
      ->setLabel('submittedFormTimeStamp')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'submittedFormTimeStamp',
      ]);

    // ->addConstraint('NotBlank')
    $info['application_number'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('applicationNumber')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'applicationNumber',
      ]);
    // ->addConstraint('NotBlank')
    $info['status'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Status')
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'status',
      ]);
    // ->addConstraint('NotBlank')
    $info['acting_year'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Acting year')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'applicationInfoArray',
        'actingYear',
        // ->addConstraint('NotBlank')
      ]);

    $info['account_number'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('accountNumber')
      ->setSetting('jsonPath', [
        'compensation',
        'bankAccountArray',
        'accountNumber',
      ])
      ->addConstraint('NotBlank');

    $info['compensation_purpose'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('')
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'generalInfoArray',
        'purpose',
      ])
      ->addConstraint('NotBlank');

    $info['compensation_boolean'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('compensationPreviousYear')
      ->setSetting('defaultValue', FALSE)
      ->setSetting('typeOverride', [
        'dataType' => 'string',
        'jsonType' => 'bool',
      ])
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'generalInfoArray',
        'compensationPreviousYear',
      ])
      ->addConstraint('NotBlank');

    $info['compensation_total_amount'] = DataDefinition::create('float')
      // ->setRequired(TRUE)
      ->setLabel('compensationInfo=>purpose')
      ->setSetting('defaultValue', 0)
      ->setSetting('typeOverride', [
        'dataType' => 'string',
        'jsonType' => 'float',
      ])
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'generalInfoArray',
        'totalAmount',
      ])
      ->addConstraint('NotBlank');

    $info['compensation_explanation'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('compensationInfo=>explanation')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'compensationInfo',
        'generalInfoArray',
        'explanation',
        // ->addConstraint('NotBlank')
      ]);

    $info['myonnetty_avustus'] = ListDataDefinition::create('grants_metadata_other_compensation')
      // ->setRequired(TRUE)
      ->setLabel('Myönnetty avustus')
      ->setSetting('defaultValue', [])
      ->setSetting('jsonPath', [
        'compensation',
        'otherCompensationsInfo',
        'otherCompensationsArray',
      ])
      ->setSetting('requiredInJson', TRUE);

    $info['haettu_avustus_tieto'] = ListDataDefinition::create('grants_metadata_other_compensation')
      // ->setRequired(TRUE)
      ->setLabel('Haettu avustus')
      ->setSetting('defaultValue', [])
      ->setSetting('jsonPath', [
        'compensation',
        'otherCompensationsInfo',
        'otherAppliedCompensationsArray',
      ]);

    $info['myonnetty_avustus_total'] = DataDefinition::create('float')
      // ->setRequired(TRUE)
      ->setLabel('Myönnetty avustus total')
      ->setSetting('defaultValue', 0)
      ->setSetting('typeOverride', [
        'dataType' => 'string',
        'jsonType' => 'double',
      ])
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'convertToFloat',
      ])
      ->setSetting('jsonPath', [
        'compensation',
        'otherCompensationsInfo',
        'otherCompensationsInfoArray',
        'otherCompensationsTotal',
      ])
      ->addConstraint('NotBlank');

    $info['haettu_avustus_tieto_total'] = DataDefinition::create('float')
      // ->setRequired(TRUE)
      ->setLabel('Haettu avustus total')
      ->setSetting('defaultValue', 0)
      ->setSetting('typeOverride', [
        'dataType' => 'string',
        'jsonType' => 'double',
      ])
      ->setSetting('jsonPath', [
        'compensation',
        'otherCompensationsInfo',
        'otherCompensationsInfoArray',
        'otherAppliedCompensationsTotal',
      ])
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'convertToFloat',
      ])
      ->addConstraint('NotBlank');

    $info['benefits_loans'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Loans')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'benefitsInfoArray',
        'loans',
        // ->addConstraint('NotBlank')
      ]);

    $info['benefits_premises'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Premises')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'benefitsInfoArray',
        'premises',
        // ->addConstraint('NotBlank')
      ]);

    $info['fee_person'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('activitiesInfoArray=>feePerson')
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'feePerson',
      ])
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'convertToFloat',
      ])
      ->addConstraint('NotBlank');

    $info['fee_community'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('activitiesInfoArray=>feeCommunity')
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'feeCommunity',
      ])
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'convertToFloat',
      ])
      ->addConstraint('NotBlank');

    $info['members_applicant_person_local'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('activitiesInfoArray=>membersApplicantPersonLocal')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'membersApplicantPersonLocal',
        // ->addConstraint('NotBlank')
      ]);

    $info['members_applicant_person_global'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('activitiesInfoArray=>membersApplicantPersonGlobal')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'membersApplicantPersonGlobal',
        // ->addConstraint('NotBlank')
      ]);

    $info['members_applicant_community_local'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('activitiesInfoArray=>membersApplicantCommunityLocal')
      ->setSetting('defaultValue', "")
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'membersApplicantCommunityLocal',
        // ->addConstraint('NotBlank')
      ]);

    $info['members_applicant_community_global'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('activitiesInfoArray=>membersApplicantCommunityGlobal')
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'membersApplicantCommunityGlobal',
        // ->addConstraint('NotBlank')
      ]);

    $info['business_purpose'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('businessPurpose')
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'businessPurpose',
      ])
      ->setSetting('defaultValue', '');
    // ->addConstraint('NotBlank')
    $info['community_practices_business'] = DataDefinition::create('boolean')
      // ->setRequired(TRUE)
      ->setLabel('communityPracticesBusiness')
      ->setSetting('defaultValue', FALSE)
      ->setSetting('jsonPath', [
        'compensation',
        'activitiesInfoArray',
        'communityPracticesBusiness',
      ])
      ->setSetting('typeOverride', [
        'dataType' => 'string',
        'jsonType' => 'bool',
      ])
      ->setSetting('defaultValue', FALSE);

    // ->addConstraint('NotBlank')
    $info['additional_information'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('additionalInformation')
      ->setSetting('jsonPath', ['compensation', 'additionalInformation'])
      ->setSetting('defaultValue', "");
    // ->addConstraint('NotBlank')
    // Sender details.
    // @todo Maybe move sender info to custom definition?
    $info['sender_firstname'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('firstname')
      ->setSetting('jsonPath', [
        'compensation',
        'senderInfoArray',
        'firstname',
      ]);
    // ->addConstraint('NotBlank')
    $info['sender_lastname'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('lastname')
      ->setSetting('jsonPath', [
        'compensation',
        'senderInfoArray',
        'lastname',
      ]);
    // ->addConstraint('NotBlank')
    // @todo Validate person id?
    $info['sender_person_id'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('personID')
      ->setSetting('jsonPath', [
        'compensation',
        'senderInfoArray',
        'personID',
      ]);
    // ->addConstraint('NotBlank')
    $info['sender_user_id'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('userID')
      ->setSetting('jsonPath', ['compensation', 'senderInfoArray', 'userID']);
    // ->addConstraint('NotBlank')
    $info['sender_email'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Email')
      ->setSetting('jsonPath', ['compensation', 'senderInfoArray', 'email']);

    // Attachments.
    $info['attachments'] = ListDataDefinition::create('grants_metadata_attachment')
      // ->setRequired(TRUE)
      ->setLabel('Attachments')
      ->setSetting('jsonPath', ['attachmentsInfo', 'attachmentsArray']);

    $info['extra_info'] = DataDefinition::create('string')
      // ->setRequired(TRUE)
      ->setLabel('Extra Info')
      ->setSetting('jsonPath', [
        'attachmentsInfo',
        'generalInfoArray',
        'extraInfo',
      ]);

    $info['form_update'] = DataDefinition::create('boolean')
      ->setRequired(TRUE)
      ->setLabel('formUpdate')
      ->setSetting('jsonPath', ['formUpdate'])
      ->setSetting('typeOverride', [
        'dataType' => 'string',
        'jsonType' => 'bool',
      ])
      ->setSetting('defaultValue', FALSE);

    $info['status_updates'] = MapDataDefinition::create()
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'cleanUpArrayValues',
      ])
      ->setPropertyDefinition(
        'caseId',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['statusUpdates', 'caseId'])
      )
      ->setPropertyDefinition(
        'citizenCaseStatus',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['statusUpdates', 'citizenCaseStatus'])
      )
      ->setPropertyDefinition(
        'eventType',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['statusUpdates', 'eventType'])
      )
      ->setPropertyDefinition(
        'eventCode',
        DataDefinition::create('integer')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['statusUpdates', 'eventCode'])
      )
      ->setPropertyDefinition(
        'eventSource',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['statusUpdates', 'eventSource'])
      )
      ->setPropertyDefinition(
        'timeUpdated',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['statusUpdates', 'timeUpdated'])
      )
      ->setSetting('jsonPath', ['statusUpdates'])
      ->setRequired(FALSE);

    $info['events'] = MapDataDefinition::create()
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'cleanUpArrayValues',
      ])
      ->setPropertyDefinition(
        'caseId',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['events', 'caseId'])
      )
      ->setPropertyDefinition(
        'eventType',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['events', 'eventType'])
      )
      ->setPropertyDefinition(
        'eventCode',
        DataDefinition::create('integer')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['events', 'eventCode'])
      )
      ->setPropertyDefinition(
        'eventSource',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['events', 'eventSource'])
      )
      ->setPropertyDefinition(
        'timeUpdated',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['events', 'timeUpdated'])
      )
      ->setSetting('jsonPath', ['events'])
      ->setRequired(FALSE);

    $info['messages'] = MapDataDefinition::create()
      ->setSetting('valueCallback', [
        '\Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler',
        'cleanUpArrayValues',
      ])
      ->setPropertyDefinition(
        'caseId',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['messages', 'caseId'])
      )
      ->setPropertyDefinition(
        'messageId',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['messages', 'messageId'])
      )
      ->setPropertyDefinition(
        'body',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['messages', 'body'])
      )
      ->setPropertyDefinition(
        'sentBy',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['messages', 'sentBy'])
      )
      ->setPropertyDefinition(
        'sendDateTime',
        DataDefinition::create('string')
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['messages', 'sendDateTime'])
      )
      ->setPropertyDefinition(
        'attachments',
        MapDataDefinition::create()
          ->setPropertyDefinition('description',
            DataDefinition::create('string')
              ->setRequired(FALSE)
              ->setSetting('jsonPath', ['description'])
          )
          ->setPropertyDefinition('fileName',
            DataDefinition::create('string')
              ->setRequired(FALSE)
              ->setSetting('jsonPath', ['fileName'])
          )
          ->setRequired(FALSE)
          ->setSetting('jsonPath', ['messages', 'attachments'])
      )
      ->setSetting('jsonPath', ['messages'])
      ->setRequired(FALSE);

    return $info;
  }

}
