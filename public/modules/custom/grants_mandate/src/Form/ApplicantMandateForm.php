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
    // ->t('Unregistered community'),
    // 'private_person' => $this
    // ->t('Private person'),
    // ],
    // '#required' => TRUE,
    // ];
    $form['info'] = [
      '#markup' => '<p>'.$this->t('Choose the applicant role you want to use for the application').'</p>',
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['registered'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('hds-card__body')),
      '#prefix' => '<div class="hds-card hds-card--applicant-role">',
      '#suffix' => '</div>',
    );
    $form['actions']['registered']['info'] = [
      '#markup' => '
      <span aria-hidden="true" class="hds-icon hds-icon--group hds-icon--size-m"></span>
      <h2 class="hds-card__heading-m heading-m" role="heading" aria-level="2">'.$this->t('Registered community').'</h2>
      <div class="hds-card__text">
        '.$this->t('This is a short description of the applicant role.').'
      </div>',
    ];
    $form['actions']['registered']['submit'] = [
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
