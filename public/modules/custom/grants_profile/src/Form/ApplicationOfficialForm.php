<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\grants_profile\TypedData\Definition\ApplicationOfficialDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class ApplicationOfficialForm extends FormBase {

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
    return 'grants_profile_application_official';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $official_id = ''): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $selectedOfficial = $grantsProfileService->getOfficial($official_id);

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#default_value' => $selectedOfficial['name'],
    ];
    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#required' => TRUE,
      '#default_value' => $selectedOfficial['role'],
      '#options' => [
        1 => $this->t('Puheenjohtaja'),
        2 => $this->t('Taloudesta vastaava'),
        3 => $this->t('Sihteeri'),
        4 => $this->t('Toiminnanjohtaja'),
        5 => $this->t('Varapuheenjohtaja'),
        6 => $this->t('Muu'),
      ],
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $selectedOfficial['email'],
    ];
    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#default_value' => $selectedOfficial['phone'],
    ];

    $form['official_id'] = [
      '#type' => 'hidden',
      '#value' => $official_id,
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
    $applicationOfficialDefinition = ApplicationOfficialDefinition::create('grants_profile_application_official');
    // Create data object.
    $applicationOfficialData = $this->typedDataManager->create($applicationOfficialDefinition);

    // Get & set values from form.
    $tempValues = $form_state->getValues();
    $values = [
      'name' => $tempValues['name'],
      'role' => $tempValues['role'],
      'email' => $tempValues['email'],
      'phone' => $tempValues['phone'],
    ];
    try {
      // Set values.
      $applicationOfficialData->setValue($values);
      // Validate inserted data.
      $violations = $applicationOfficialData->validate();
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
        $form_state->setStorage(['applicationOfficialData' => $applicationOfficialData]);
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
    if (!isset($storage['applicationOfficialData'])) {
      $this->messenger()->addError($this->t('applicationOfficialData not found!'));
      return;
    }

    $applicationOfficialData = $storage['applicationOfficialData'];
    $applicationOfficialId = $form_state->getValue('official_id');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $grantsProfileService->saveOfficial($applicationOfficialId, $applicationOfficialData->toArray());
    $grantsProfileService->saveGrantsProfile();

    $this->messenger()->addStatus($this->t('Official has been saved.'));

    $form_state->setRedirect('grants_profile.show');
  }

}
