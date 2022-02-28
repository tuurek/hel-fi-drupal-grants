<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\grants_profile\TypedData\Definition\GrantsProfileDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class GrantsProfileForm extends FormBase {

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
  public static function create(ContainerInterface $container): GrantsProfileForm|static {
    return new static(
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_profile_grants_profile';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);

    if (empty($grantsProfileContent)) {
      $this->messenger()->addError($this->t('Error fetching profile data'));
      $this->logger('grants_profile')->error('Profile fetch failed.');
      return $form;
    }

    // Set profile content for other fields than this form.
    $form_state->setStorage(['grantsProfileContent' => $grantsProfileContent]);
    $form['foundingYearWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Founding year'),
    ];
    $form['foundingYearWrapper']['foundingYear'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Founding year'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['foundingYear'],
    ];
    $form['companyNameShortWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company short name'),
    ];
    $form['companyNameShortWrapper']['companyNameShort'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company short name'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['companyNameShort'],
    ];
    $form['companyHomePageWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company www address'),
    ];
    $form['companyHomePageWrapper']['companyHomePage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company www address'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['companyHomePage'],
    ];
    $form['companyEmailWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company email'),
    ];
    $form['companyEmailWrapper']['companyEmail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company email'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['companyEmail'],
    ];
    $form['businessPurposeWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Business Purpose'),
    ];
    $form['businessPurposeWrapper']['businessPurpose'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description of business purpose'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['businessPurpose'],
    ];
    $addressMarkup = '<p>' . $this->t("You can add several addresses to your company. The addresses given are available on applications. The address is used for postal deliveries, such as letters regarding the decisions.") . '</p>';
    if (is_array($grantsProfileContent["addresses"]) && count($grantsProfileContent["addresses"]) > 0) {
      $addressMarkup .= '<ul>';
      foreach ($grantsProfileContent["addresses"] as $key => $address) {
        $addressMarkup .= '<li><a href="/grants-profile/address/' . $key . '">' . $address['street'] . '</a></li>';
      }
      $addressMarkup .= '</ul>';
    }
    else {
      $addressMarkup .= '
    <section aria-label="Notification" class="hds-notification hds-notification--alert">
      <div class="hds-notification__content">
        <div class="hds-notification__label" role="heading" aria-level="2">
          <span class="hds-icon hds-icon--alert-circle-fill" aria-hidden="true"></span>
          <span>' . $this->t('Add at least one address') . '</span>
        </div>
      </div>
    </section>';
    }
    $addressMarkup .= '<div><a class="hds-button hds-button--secondary" href="/grants-profile/address/new"><span aria-hidden="true" class="hds-icon hds-icon--plus-circle"></span>
<span class="hds-button__label">' . $this->t('New Address') . '</span></a></div>';
    $addressMarkup = '<div>' . $addressMarkup . '</div>';

    $form['addressWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company Addresses'),
    ];
    $form['addressWrapper']['address_markup'] = [
      '#type' => 'markup',
      '#markup' => $addressMarkup,
    ];

    $bankAccountMarkup = '<p>' . $this->t('You can add several bank accounts to your company. The bank account must be a Finnish IBAN account number.') . '</p>';
    $bankAccountMarkup .= '<p>' . $this->t("The information you give are usable when making grants applications. If a grant is given to an application, it is paid to the account number you've given on the application") . '</p>';

    if (is_array($grantsProfileContent["bankAccounts"]) && count($grantsProfileContent["bankAccounts"]) > 0) {
      $bankAccountMarkup .= '<ul>';
      foreach ($grantsProfileContent["bankAccounts"] as $key => $address) {
        $bankAccountMarkup .= '<li><a href="/grants-profile/bank-accounts/' . $key . '">' . $address['bankAccount'] . '</a></li>';
      }
      $bankAccountMarkup .= '</ul>';
    }
    else {
      $bankAccountMarkup .= '
    <section aria-label="Notification" class="hds-notification hds-notification--alert">
      <div class="hds-notification__content">
        <div class="hds-notification__label" role="heading" aria-level="2">
          <span class="hds-icon hds-icon--alert-circle-fill" aria-hidden="true"></span>
          <span>' . $this->t('Add at least one account number') . '</span>
        </div>
      </div>
    </section>';
    }
    $bankAccountMarkup .= '<div><a class="hds-button hds-button--secondary" href="/grants-profile/bank-accounts/new"><span aria-hidden="true" class="hds-icon hds-icon--plus-circle"></span>
<span class="hds-button__label">' . $this->t('New Bank account') . '</span></a></div>';
    $bankAccountMarkup = '<div>' . $bankAccountMarkup . '</div>';
    $form['bankAccountWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company Bank Accounts'),
    ];
    $form['bankAccountWrapper']['bankAccount_markup'] = [
      '#type' => 'markup',
      '#markup' => $bankAccountMarkup,
    ];
    $officialsMarkup = '<p>' . $this->t('Report the names and contact information of officials, such as the chairperson, secretary, etc.') . '</p>';
    $officialsMarkup .= '<p>' . $this->t("The information you give are usable during grants applciations.") . '</p>';
    $officialsMarkup .= '<ul>';
    foreach ($grantsProfileContent["officials"] as $key => $address) {
      $officialsMarkup .= '<li><a href="/grants-profile/application-officials/' . $key . '">' . $address['name'] . '</a></li>';
    }
    $officialsMarkup .= '</ul>';
    $officialsMarkup .= '<div><a class="hds-button hds-button--secondary" href="/grants-profile/application-officials/new">
<span aria-hidden="true" class="hds-icon hds-icon--plus-circle"></span><span class="hds-button__label">' . $this->t('New official') . '</span></a></div>';
    $officialsMarkup = '<div>' . $officialsMarkup . '</div>';

    $form['officialsWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company officials'),
    ];
    $form['officialsWrapper']['officials_markup'] = [
      '#type' => 'markup',
      '#markup' => $officialsMarkup,
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

    $storage = $form_state->getStorage();
    if (!isset($storage['grantsProfileContent'])) {
      $this->messenger()->addError($this->t('grantsProfileContent not found!'));
      return;
    }

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    // $grantsProfileService = \Drupal::service('grants_profile.service');
    // $selectedCompany = $grantsProfileService->getSelectedCompany();
    $values = $form_state->getValues();

    $grantsProfileContent = $storage['grantsProfileContent'];

    foreach ($grantsProfileContent as $key => $value) {
      if (array_key_exists($key, $values)) {
        $grantsProfileContent[$key] = $values[$key];
      }
    }

    // @todo täytyy laittaa storageen tuo profile
    $grantsProfileDefinition = GrantsProfileDefinition::create('grants_profile_profile');
    // Create data object.
    $grantsProfileData = $this->typedDataManager->create($grantsProfileDefinition);
    $grantsProfileData->setValue($grantsProfileContent);
    // Validate inserted data.
    $violations = $grantsProfileData->validate();
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
      $form_state->setStorage(['grantsProfileData' => $grantsProfileData]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    if (!isset($storage['grantsProfileData'])) {
      $this->messenger()->addError($this->t('grantsProfileData not found!'));
      return;
    }

    $grantsProfileData = $storage['grantsProfileData'];

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $profileDataArray = $grantsProfileData->toArray();

    $grantsProfileService->saveGrantsProfile($profileDataArray);

    $success = $grantsProfileService->saveGrantsProfileAtv();

    if ($success == TRUE) {
      $this->messenger()
        ->addStatus($this->t('Grantsprofile for company number %s saved and can be used in grant applications', ['%s' => $selectedCompany]));
    }

    $form_state->setRedirect('grants_profile.show');
  }

}
