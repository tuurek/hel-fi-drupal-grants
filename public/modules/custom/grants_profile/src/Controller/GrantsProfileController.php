<?php

namespace Drupal\grants_profile\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\helfi_atv\AtvService;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Returns responses for Grants Profile routes.
 */
class GrantsProfileController extends ControllerBase {


  /**
   * TODO: get webform submission from db, somehow
   *
   * @param $document_uuid
   * @return array
   */
  public function viewApplication($document_uuid)
  {
    /** @var AtvService $atvService */
    $atvService =  \Drupal::service('helfi_atv.atv_service');

    $document = $atvService->getDocument($document_uuid);
    $content = $atvService->parseContent($document['content']);

    $build['#application'] = $content;
    $build['#document'] = $document;
    $build['#theme'] = 'view_application';

    return $build;
  }


  /**
   * Builds the response.
   *
   * @return array
   *   Data to render
   */
  public function ownProfile(): array {

    $form = \Drupal::formBuilder()
      ->getForm('Drupal\grants_profile\Form\CompanySelectForm'); /* inside getForm() replace the namespace with your custom form */
    $build['#company_select_form'] = $form;

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    /** @var AtvService $atvService */
    $atvService =  \Drupal::service('helfi_atv.atv_service');

    $userApplications = $atvService->searchDocuments(['type' => 'mysterious form', 'business_id' => '1234567-8']);

    $build['#applications'] = $userApplications;

    $build['#theme'] = 'own_profile';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
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

}
