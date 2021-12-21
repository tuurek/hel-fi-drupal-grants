<?php

namespace Drupal\grants_profile\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Grants Profile routes.
 */
class GrantsProfileController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function ownProfile(): array {

    $form = \Drupal::formBuilder()->getForm('Drupal\grants_profile\Form\CompanySelectForm'); /* inside getForm() replace the namespace with your custom form */
    $build['#company_select_form'] = $form;

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

    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('tempstore.private')->get('grants_profile');
    $selectedCompany = $tempstore->get('selected_company');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $grantsProfileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);

    if(!empty($grantsProfileContent['addresses'])){
      $build['#addresses'] = $grantsProfileContent['addresses'];
    }

    $build['#theme'] = 'own_addresses';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
