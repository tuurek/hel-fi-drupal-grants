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
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('tempstore.private')->get('grants_profile');
    $selectedCompany = $tempstore->get('selected_company');

    /** @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $userExternalData */
    $userExternalData = \Drupal::service('helfi_helsinki_profiili.userdata');

    /** @var \Drupal\helfi_yjdh\YjdhClient $yjdhClient */
    $yjdhClient = \Drupal::service('helfi_yjdh.client');
    $associationRoles = $yjdhClient->roleSearchWithSsn($userExternalData->getSsn());

    $options = [];
    foreach ($associationRoles['Role'] as $association) {
      $options[$association['BusinessId']] = $association['AssociationNameInfo'][0]['AssociationName'];
    }

    $form['company_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Company'),
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $selectedCompany
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

    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('tempstore.private')->get('grants_profile');
    $tempstore->set('selected_company', $selectedCompany);

    $this->messenger()->addStatus($this->t('Selected company has been set.'));
  }

}
