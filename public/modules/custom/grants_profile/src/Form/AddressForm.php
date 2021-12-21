<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Grants Profile form.
 */
class AddressForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_profile_address';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $address_id = '') {


    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('tempstore.private')->get('grants_profile');
    $selectedCompany = $tempstore->get('selected_company');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $selectedAddress = $grantsProfileService->getAddress($address_id);

    $form['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['street']
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['city']
    ];
    $form['post_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Post code'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['post_code']
    ];
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['country']
    ];
    $form['address_id'] = [
      '#type' => 'hidden',
      '#value' => $address_id
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

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    $address = [
      'street' => $values['street'],
      'city' => $values['city'],
      'post_code' => $values['post_code'],
      'country' => $values['country'],
    ];

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $grantsProfileService->saveAddress($values['address_id'], $address);
    $grantsProfileService->saveGrantsProfile();

    $this->messenger()->addStatus($this->t('Address has been saved.'));

    $form_state->setRedirect('grants_profile.company_addresses');
  }

}
