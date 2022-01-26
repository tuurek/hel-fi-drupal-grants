<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\grants_profile\TypedData\Definition\BankAccountDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class BankAccountForm extends FormBase {


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
  public function getFormId() {
    return 'grants_profile_bank_account';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $bank_account_id = NULL): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $selectedBankAccount = $grantsProfileService->getBankAccount($bank_account_id);

    $form['bankAccount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank account'),
      '#required' => TRUE,
      '#default_value' => $selectedBankAccount['bankAccount'],
    ];

    $form['bankAccount_id'] = [
      '#type' => 'hidden',
      '#value' => $bank_account_id,
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
    $bankAccountDefinition = BankAccountDefinition::create('grants_profile_bank_account');
    // Create data object.
    $bankAccountData = $this->typedDataManager->create($bankAccountDefinition);

    // Get & set values from form.
    $tempValues = $form_state->getValues();
    $values = [
      'bankAccount' => $tempValues['bankAccount'],
    ];
    try {
      // Set values.
      $bankAccountData->setValue($values);
      // Validate inserted data.
      $violations = $bankAccountData->validate();
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
        $form_state->setStorage(['bankAccountData' => $bankAccountData]);
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
    if (!isset($storage['bankAccountData'])) {
      $this->messenger()->addError($this->t('bankAccountData not found!'));
      return;
    }

    $bankAccountData = $storage['bankAccountData'];
    $bankAccountId = $form_state->getValue('bankAccount_id');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $grantsProfileService->saveBankAccount($bankAccountId, $bankAccountData->toArray());
    $grantsProfileService->saveGrantsProfileAtv();

    $this->messenger()->addStatus($this->t('Bank account has been saved.'));

    $form_state->setRedirect('grants_profile.show');
  }

}
