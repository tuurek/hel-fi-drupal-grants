<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Grants Profile form.
 */
class CompanySelectForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_profile_company_select';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    /** @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $userExternalData */
    $userExternalData = \Drupal::service('helfi_helsinki_profiili.userdata');
    $profileData = $userExternalData->getUserProfileData();

    if (isset($profileData["myProfile"])) {
      $profileData = $profileData["myProfile"];
    }

    /** @var \Drupal\helfi_yjdh\YjdhClient $yjdhClient */
    $yjdhClient = \Drupal::service('helfi_yjdh.client');

    // Make sure we have ID to search with. Remove when tunnistamo works.
    // @todo Remove hard coded hetu when tunnistamo works reliably.
    if (isset($profileData["verifiedPersonalInformation"]["nationalIdentificationNumber"])) {
      $associationRoles = $yjdhClient->roleSearchWithSsn($profileData["verifiedPersonalInformation"]["nationalIdentificationNumber"]);
    }
    else {
      $associationRoles = $yjdhClient->roleSearchWithSsn('210281-9988');
      $this->messenger()
        ->addStatus('No profiilidata, preset social security used');
    }

    $options = [];
    foreach ($associationRoles['Role'] as $association) {
      $options[$association['BusinessId']] = $association['AssociationNameInfo'][0]['AssociationName'];
    }

    $form['company_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Company'),
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $selectedCompany,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selectedCompany = $form_state->getValue('company_select');
    if (empty($selectedCompany)) {
      $form_state->setErrorByName('company_select', $this->t('You MUST select company'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $selectedCompany = $form_state->getValue('company_select');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $grantsProfileService->setSelectedCompany($selectedCompany);

    $this->messenger()->addStatus($this->t('Selected company has been set.'));

    $form_state->setRedirect('grants_profile.show');

  }

}
