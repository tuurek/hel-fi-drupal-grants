<?php

namespace Drupal\grants_mandate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\grants_mandate\GrantsMandateService;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class ApplicantMandateForm extends FormBase {

  /**
   * Access to profile data.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Access to helsinki profile data.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  /**
   * Use mandate service things.
   *
   * @var \Drupal\grants_mandate\GrantsMandateService
   */
  protected GrantsMandateService $grantsMandateService;

  /**
   * Constructs a new ModalAddressForm object.
   */
  public function __construct(
    GrantsProfileService $grantsProfileService,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    GrantsMandateService $grantsMandateService
  ) {
    $this->grantsProfileService = $grantsProfileService;
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;
    $this->grantsMandateService = $grantsMandateService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ApplicantMandateForm|static {
    return new static(
      $container->get('grants_profile.service'),
      $container->get('helfi_helsinki_profiili.userdata'),
      $container->get('grants_mandate.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_mandate_type';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // $form['applicant_type'] = [
    // '#type' => 'radios',
    // '#title' => $this
    // ->t('Select applicant type'),
    // '#options' => [
    // 'registered_community' => $this
    // ->t('Registered community'),
    // 'unregistered_community' => $this
    // ->t('UNregistered community'),
    // 'private_person' => $this
    // ->t('Private person'),
    // ],
    // '#required' => TRUE,
    // ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select role & authorize mandate'),
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

    // $selectedType = $form_state->getValue('applicant_type');
    $selectedType = 'registered_community';

    $this->grantsProfileService->setApplicantType($selectedType);

    if ($selectedType == 'registered_community' || $selectedType == 'private_person') {
      $mandateMode = '';

      if ($selectedType == 'registered_community') {
        $mandateMode = 'ypa';
      }

      if ($selectedType == 'private_person') {
        $mandateMode = 'hpa';
      }

      $redirectUrl = $this->grantsMandateService->getUserMandateRedirectUrl($mandateMode);
      $redirect = new TrustedRedirectResponse($redirectUrl);
      $form_state->setResponse($redirect);
    }
    else {
      // @todo message user if no mandate is needed??
    }

  }

}
