<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\grants_profile\TypedData\Definition\AddressDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class AddressForm extends FormBase {

  /**
   * Drupal\Core\TypedData\TypedDataManager definition.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected TypedDataManager $typedDataManager;

  /**
   * Constructs a new AddressForm object.
   */
  public function __construct(TypedDataManager $typed_data_manager) {
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): AddressForm|static {
    return new static(
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grants_profile_address';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $address_id = ''): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $selectedAddress = $grantsProfileService->getAddress($address_id, $address_id);

    $form['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['street'],
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['city'],
    ];
    $form['postCode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Post code'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['postCode'],
    ];
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['country'],
    ];
    $form['address_id'] = [
      '#type' => 'hidden',
      '#value' => $address_id,
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
    // Get definition.
    $addressDefinition = AddressDefinition::create('grants_profile_address');
    // Create data object.
    $addressData = $this->typedDataManager->create($addressDefinition);

    // Get & set values from form.
    $tempValues = $form_state->getValues();
    $values = [
      'street' => $tempValues['street'],
      'city' => $tempValues['city'],
      'postCode' => $tempValues['postCode'],
      'country' => $tempValues['country'],
    ];
    try {
      // Set values.
      $addressData->setValue($values);
      // Validate inserted data.
      $violations = $addressData->validate();
      // If there's violations in data.
      if ($violations->count() != 0) {
        foreach ($violations as $violation) {
          // Print errors by form item name.
          $form_state->setErrorByName(
            $violation->getPropertyPath(),
            $violation->getMessage());
        }
      }
      else {
        // Move addressData object to form_state storage.
        $form_state->setStorage(['addressData' => $addressData]);
      }
    }
    catch (ReadOnlyException $e) {
      $this->messenger()->addError('Data read only');
      $form_state->setError($form, 'Trying to write to readonly value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    if (!isset($storage['addressData'])) {
      $this->messenger()->addError($this->t('addressData not found!'));
      return;
    }

    $addressData = $storage['addressData'];
    $addressId = $form_state->getValue('address_id');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $grantsProfileService->saveAddress($addressId, $addressData->toArray());
    $grantsProfileService->saveGrantsProfile();

    $this->messenger()->addStatus($this->t('Address has been saved.'));

    $form_state->setRedirect('grants_profile.show');
  }

}
