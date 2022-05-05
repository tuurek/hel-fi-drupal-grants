<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class ApplicantTypeForm extends FormBase {

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
   * Constructs a new AddressForm object.
   */
  public function __construct(GrantsProfileService $grantsProfileService, HelsinkiProfiiliUserData $helsinkiProfiiliUserData) {
    $this->grantsProfileService = $grantsProfileService;
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GrantsProfileForm|static {
    return new static(
      $container->get('grants_profile.service'),
      $container->get('helfi_helsinki_profiili.userdata'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_profile_applicant_type';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['applicant_type'] = [
      '#type' => 'radios',
      '#title' => $this
        ->t('Select applicant type'),
      '#options' => [
        'registered_community' => $this
          ->t('Registered community'),
        'unregistered_community' => $this
          ->t('UNregistered community'),
        'private_person' => $this
          ->t('Private person'),
      ],
      '#required' => TRUE,
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

    $selectedType = $form_state->getValue('applicant_type');

    $this->grantsProfileService->setApplicantType($selectedType);

    $this->messenger()->addStatus('Applicant type selected.');

    // $url = Url::fromUri('')
    //
    //    $form_state->setRedirectUrl()
  }

}
