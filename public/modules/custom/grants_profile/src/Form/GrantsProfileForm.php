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

    $grantsProfileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany, TRUE);

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
      '#default_value' => $grantsProfileContent['foundingYear'],
    ];
    $form['foundingYearWrapper']['foundingYear']['#attributes']['class'][] = 'webform--small';

    $form['companyNameShortWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Company short name'),
    ];
    $form['companyNameShortWrapper']['companyNameShort'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company short name'),
      '#default_value' => $grantsProfileContent['companyNameShort'],
    ];
    $form['companyNameShortWrapper']['companyNameShort']['#attributes']['class'][] = 'webform--large';

    $addressMarkup = '<p>' . $this->t("You can add several addresses to your company. The addresses given are available on applications. The address is used for postal deliveries, such as letters regarding the decisions.") . '</p>';
    if (is_array($grantsProfileContent["addresses"]) && count($grantsProfileContent["addresses"]) > 0) {
      $addressMarkup .= '<ul class="grants-profile--officials">';
      foreach ($grantsProfileContent["addresses"] as $key => $address) {
        $addressMarkup .= '
    <li class="grants-profile--officials-item">
        <div class="grants-profile--officials-item-wrapper">
          <div class="grants-profile--officials-item--name">
            ' . $address['street'] . ', ' . $address['postCode'] . ' ' . $address['city'] . '
          </div>
        </div>
        <div class="grants-profile--officials-edit-wrapper">
        <a class="hds-link hds-link--medium" href="/grants-profile/address/' . $key . '">
<span aria-hidden="true" class="hds-icon hds-icon--pen-line hds-icon--size-s"></span><span class="link-label">' . $this->t('Edit') . '</span></a>
       </div>

    </li>';
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
    $addressMarkup .= '<div class="form-item"><a class="hds-link hds-link--medium" href="/grants-profile/address/new">
<span aria-hidden="true" class="hds-icon hds-icon--plus-circle hds-icon--size-s"></span><span class="link-label">' . $this->t('New Address') . '</span></a></div>';
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
      $bankAccountMarkup .= '<ul class="grants-profile--officials">';
      foreach ($grantsProfileContent["bankAccounts"] as $key => $address) {
        $bankAccountMarkup .= '
    <li class="grants-profile--officials-item">
        <div class="grants-profile--officials-item-wrapper">
          <div class="grants-profile--officials-item--name">
            ' . $address['bankAccount'] . '
          </div>
        </div>
        <div class="grants-profile--officials-edit-wrapper">
        <a class="hds-link hds-link--medium" href="/grants-profile/bank-accounts/' . $key . '">
<span aria-hidden="true" class="hds-icon hds-icon--pen-line hds-icon--size-s"></span><span class="link-label">' . $this->t('Edit') . '</span></a>
       </div>
    </li>';
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
    $bankAccountMarkup .= '<div class="form-item"><a class="hds-link hds-link--medium" href="/grants-profile/bank-accounts/new">
<span aria-hidden="true" class="hds-icon hds-icon--plus-circle hds-icon--size-s"></span><span class="link-label">' . $this->t('New Bank account') . '</span></a></div>';

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
    $officialsMarkup .= '<ul class="grants-profile--officials">';
    foreach ($grantsProfileContent["officials"] as $key => $official) {
      $officialRole = $this->t('Other');
      switch ($official['role']) {
        case 1:
          $officialRole = $this->t('Chairperson');
          break;

        case 2:
          $officialRole = $this->t('Financial officer');
          break;

        case 3:
          $officialRole = $this->t('Secretary');
          break;

        case 4:
          $officialRole = $this->t('Operative manager');
          break;

        case 5:
          $officialRole = $this->t('Vice Chairperson');
          break;

        default:
          $officialRole = $this->t('Other');
          break;
      }
      $officialsMarkup .= '
    <li class="grants-profile--officials-item">
        <div class="grants-profile--officials-item-wrapper">
          <h3 class="grants-profile--officials-item--position">
            ' . $officialRole . '
          </h3>
          <div class="grants-profile--officials-item--name">
            ' . $official['name'] . '
          </div>
          <div class="grants-profile--officials-item--phone">
            ' . $official['phone'] . '
          </div>
          <div class="grants-profile--officials-item--email">
            ' . $official['email'] . '
          </div>
        </div>
        <div class="grants-profile--officials-edit-wrapper">
        <a class="hds-link hds-link--medium" href="/grants-profile/application-officials/' . $key . '">
<span aria-hidden="true" class="hds-icon hds-icon--pen-line hds-icon--size-s"></span><span class="link-label">' . $this->t('Edit') . '</span></a>
        <a class="hds-link hds-link--medium" href="/grants-profile/application-officials/' . $key . '">
<span aria-hidden="true" class="hds-icon hds-icon--cross hds-icon--size-s"></span><span class="link-label">' . $this->t('Delete') . '</span></a>
        </div>

    </li>';
    }
    $officialsMarkup .= '</ul>';
    $officialsMarkup .= '<div class="form-item"><a class="hds-link hds-link--medium" href="/grants-profile/application-officials/new">
<span aria-hidden="true" class="hds-icon hds-icon--plus-circle hds-icon--size-s"></span><span class="link-label">' . $this->t('New official') . '</span></a></div>';
    $officialsMarkup = '<div>' . $officialsMarkup . '</div>';
    $form['businessPurposeWrapper'] = [
      '#type' => 'webform_section',
      '#title' => $this->t('Business Purpose'),
    ];
    $form['businessPurposeWrapper']['businessPurpose'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description of business purpose'),
      '#default_value' => $grantsProfileContent['businessPurpose'],
      '#counter_type' => 'character',
      '#counter_maximum' => 500,
      '#counter_maximum_message' => '%d/500 merkkiä jäljellä',
      '#help' => t('Briefly describe the purpose for which the community is working and how the community is fulfilling its purpose. For example, you can use the text "Community purpose and forms of action" in the Community rules. Please do not describe the purpose of the grant here, it will be asked later when completing the grant application.')
    ];
    $form['businessPurposeWrapper']['businessPurpose']['#attributes']['class'][] = 'webform--large';

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

    // @todo Created profile needs to be set to cache.
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
