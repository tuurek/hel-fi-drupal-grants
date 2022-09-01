<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\Url;
use Drupal\grants_attachments\AttachmentHandler;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_handler\GrantsHandlerNavigationHelper;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Webform example handler.
 *
 * @WebformHandler(
 *   id = "grants_handler",
 *   label = @Translation("Grants Handler"),
 *   category = @Translation("helfi"),
 *   description = @Translation("Grants webform handler"),
 *   cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class GrantsHandler extends WebformHandlerBase {

  /**
   * Form data saved because the data in saved submission is not preserved.
   *
   * @var array
   *   Holds submitted data for processing in confirmForm.
   *
   * When we want to delete all submitted data before saving
   * submission to database. This way we can still use webform functionality
   * while not saving any sensitive data to local drupal.
   */
  private array $submittedFormData = [];

  /**
   * Application type.
   *
   * @var string
   */
  protected string $applicationType;

  /**
   * Applicant type.
   *
   * Private / registered / UNregistered.
   *
   * @var string
   */
  protected string $applicantType;

  /**
   * Application type ID.
   *
   * @var string
   */
  protected string $applicationTypeID;

  /**
   * Generated application number.
   *
   * @var string
   */
  protected string $applicationNumber;

  /**
   * Status for updated submission.
   *
   * Old one if no update.
   *
   * @var string
   */
  protected string $newStatus;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * User data from helsinkiprofiili & auth methods.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $userExternalData;

  /**
   * Access GRants profile.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected DateFormatter $dateFormatter;

  /**
   * All attachment-related things.
   *
   * @var \Drupal\grants_attachments\AttachmentHandler
   */
  protected AttachmentHandler $attachmentHandler;

  /**
   * Process application data from webform to ATV.
   *
   * @var \Drupal\grants_handler\ApplicationHandler
   */
  protected ApplicationHandler $applicationHandler;

  /**
   * Save form trigger for methods where form_state is not available.
   *
   * @var string
   */
  protected string $triggeringElement;

  /**
   * Save form for methods where form is not available.
   *
   * @var array
   */
  protected array $formTemp;

  /**
   * Help with stored errors.
   *
   * @var \Drupal\grants_handler\GrantsHandlerNavigationHelper
   */
  protected GrantsHandlerNavigationHelper $grantsFormNavigationHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->currentUser = $container->get('current_user');

    $instance->userExternalData = $container->get('helfi_helsinki_profiili.userdata');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $instance->grantsProfileService = \Drupal::service('grants_profile.service');

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $instance->dateFormatter = \Drupal::service('date.formatter');

    /** @var \Drupal\grants_attachments\AttachmentHandler */
    $instance->attachmentHandler = \Drupal::service('grants_attachments.attachment_handler');
    $instance->attachmentHandler->setDebug($instance->isDebug());

    /** @var \Drupal\grants_handler\ApplicationHandler */
    $instance->applicationHandler = \Drupal::service('grants_handler.application_handler');

    $instance->grantsFormNavigationHelper = \Drupal::service('grants_handler.navigation_helper');

    $instance->applicationHandler->setDebug($instance->isDebug());

    $instance->triggeringElement = '';
    $instance->applicationNumber = '';
    $instance->applicantType = '';
    $instance->applicationTypeID = '';
    $instance->applicationType = '';

    return $instance;
  }

  /**
   * Convert EUR format value to "double" .
   *
   * @param string $value
   *   Value to be converted.
   *
   * @return float
   *   Floated value.
   */
  private function grantsHandlerConvertToFloat(string $value): float {
    $value = str_replace(['€', ',', ' '], ['', '.', ''], $value);
    $value = (float) $value;
    return $value;
  }

  /**
   * Convert EUR format value to "double" .
   *
   * @param string|null $value
   *   Value to be converted.
   *
   * @return float
   *   Floated value.
   */
  public static function convertToFloat(?string $value = ''): float {
    $value = str_replace(['€', ',', ' '], ['', '.', ''], $value);
    $value = (float) $value;
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // Development.
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, every handler method invoked will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * Calculate & set total values from added elements in webform.
   */
  protected function setTotals() {

    if (isset($this->submittedFormData['myonnetty_avustus']) &&
      is_array($this->submittedFormData['myonnetty_avustus'])) {
      $tempTotal = 0;
      foreach ($this->submittedFormData['myonnetty_avustus'] as $key => $item) {
        $amount = $this->grantsHandlerConvertToFloat($item['amount']);
        $tempTotal += $amount;
      }
      $this->submittedFormData['myonnetty_avustus_total'] = $tempTotal;
    }

    if (isset($this->submittedFormData['haettu_avustus_tieto']) &&
      is_array($this->submittedFormData['haettu_avustus_tieto'])) {
      $tempTotal = 0;
      foreach ($this->submittedFormData['haettu_avustus_tieto'] as $item) {
        $amount = $this->grantsHandlerConvertToFloat($item['amount']);
        $tempTotal += $amount;
      }
      $this->submittedFormData['haettu_avustus_tieto_total'] = $tempTotal;
    }

    // @todo properly get amount
    $this->submittedFormData['compensation_total_amount'] = $tempTotal;
  }

  /**
   * {@inheritdoc}
   */
  public function access(WebformSubmissionInterface $webform_submission, $operation, AccountInterface $account = NULL) {
    // @todo Change the autogenerated stub
    return parent::access($webform_submission, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function accessElement(array &$element, $operation, AccountInterface $account = NULL) {
    // @todo Change the autogenerated stub
    return parent::accessElement($element, $operation, $account);
  }

  /**
   * Format form values to be consumed with typedata.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $webform_submission
   *   Submission object.
   *
   * @return mixed
   *   Massaged values.
   */
  protected function massageFormValuesFromWebform(WebformSubmission $webform_submission): mixed {
    $values = $webform_submission->getData();
    $this->setFromThirdPartySettings($webform_submission->getWebform());

    if (isset($this->applicationType) && $this->applicationType != '') {
      $values['application_type'] = $this->applicationType;
    }
    if (isset($this->applicationTypeID) && $this->applicationTypeID != '') {
      $values['application_type_id'] = $this->applicationTypeID;
    }

    if (isset($values['community_address']) && $values['community_address'] !== NULL) {
      $values += $values['community_address'];
      unset($values['community_address']);
      unset($values['community_address_select']);
    }

    if (isset($values['bank_account']) && $values['bank_account'] !== NULL) {
      $values['account_number'] = $values['bank_account']['account_number'];
      unset($values['bank_account']);
    }

    // If for some reason we don't have application number at this point.
    if (!isset($this->applicationNumber) || $this->applicationNumber == '') {
      // But if one is coming from form (hidden field)
      if (isset($this->submittedFormData['application_number']) && $this->submittedFormData['application_number'] != '') {
        // Use it.
        $this->applicationNumber = $this->submittedFormData['application_number'];
      }
      else {
        // But if we have saved webform earlier, we can get the application
        // number from submission serial.
        $s = $webform_submission->serial();
        if ($webform_submission->serial()) {
          $this->applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
          $this->submittedFormData['application_number'] = $this->applicationNumber;
          $values['application_number'] = $this->applicationNumber;
        }
        // Hopefully we never reach here, but there should be additional checks
        // for application number to exists.
        // and it's no biggie since we can always get it from the method above
        // as long as we have our submission object.
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {

    $currentUser = \Drupal::currentUser();
    $currentUserRoles = $currentUser->getRoles();

    // These both are required to be selected.
    // probably will change when we have proper company selection process.
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $grantsProfile = $this->grantsProfileService->getGrantsProfileContent($selectedCompany);

    $webform = Webform::load($values['webform_id']);

    $this->setFromThirdPartySettings($webform);

    // \Drupal::messenger()->addMessage
    // ('Message in GrantsHandler::preCreate()');
    $this->applicantType = $this->grantsProfileService->getApplicantType();
    if ((in_array('helsinkiprofiili', $currentUserRoles)) &&
      ($currentUser->id() != '1')) {

      $redirectApplicantType = FALSE;

      if ($this->applicantType === NULL) {
        $this->messenger()
          ->addError($this->t("You need to select company you're acting behalf of."));
        $redirectApplicantType = TRUE;
      }

      if ($selectedCompany == NULL) {
        $this->messenger()
          ->addError($this->t("You need to select company you're acting behalf of."));
        $redirectApplicantType = TRUE;
      }

      if ($redirectApplicantType === TRUE) {
        $url = Url::fromRoute('grants_mandate.mandateform', [
          'destination' => $values["uri"],
        ])
          ->setAbsolute()
          ->toString();
        $response = new RedirectResponse($url);
        $response->send();
      }

      $redirectToProfile = FALSE;

      if (empty($grantsProfile['officials'])) {
        $this->messenger()
          ->addError($this->t("You must have atleast one official for @businessId", ['@businessId' => $selectedCompany["identifier"]]), TRUE);
        $redirectToProfile = TRUE;
      }
      if (empty($grantsProfile['addresses'])) {
        $this->messenger()
          ->addError($this->t("You must have atleast one address for @businessId", ['@businessId' => $selectedCompany["identifier"]]), TRUE);
        $redirectToProfile = TRUE;
      }
      if (empty($grantsProfile['bankAccounts'])) {
        $this->messenger()
          ->addError($this->t("You must have atleast one bank account for @businessId", ['@businessId' => $selectedCompany["identifier"]]), TRUE);
        $redirectToProfile = TRUE;
      }

      if ($redirectToProfile === TRUE) {
        $url = Url::fromRoute('grants_profile.show', [
          'destination' => $values["uri"],
        ])
          ->setAbsolute()
          ->toString();
        $response = new RedirectResponse($url);
        $response->send();
      }

    }
    else {
      $this->messenger()
        ->addError($this->t("No prefill for admin"));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    // \Drupal::messenger()->addMessage
    // ('Message in GrantsHandler::alterForm()');
    $this->alterFormNavigation($form, $form_state, $webform_submission);

    $form['#webform_submission'] = $webform_submission;
    $webform = $webform_submission->getWebform();
    $form['#form_state'] = $form_state;

    $this->setFromThirdPartySettings($webform_submission->getWebform());

    // If submission has applicant type set, ie we're editing submission
    // use that, if not then get selected from profile.
    // we know that.
    $submissionData = $this->massageFormValuesFromWebform($webform_submission);
    if (isset($submissionData['applicant_type'])) {
      $applicantType = $submissionData['applicant_type'];
    }
    else {
      $applicantTypeString = $this->grantsProfileService->getApplicantType();
      $applicantType = '0';
      switch ($applicantTypeString) {
        case 'registered_community':
          $applicantType = '0';
          break;

        case 'unregistered_community':
          $applicantType = '1';
          break;

        case 'private_person':
          $applicantType = '2';
          break;
      }
    }

    $form["elements"]["1_hakijan_tiedot"]["yhteiso_jolle_haetaan_avustusta"]["applicant_type"] = [
      '#type' => 'hidden',
      '#value' => $applicantType,
    ];

    $thisYear = (integer) date('Y');
    $thisYearPlus1 = $thisYear + 1;
    $thisYearPlus2 = $thisYear + 2;

    $form["elements"]["2_avustustiedot"]["avustuksen_tiedot"]["acting_year"]["#options"] = [
      $thisYear => $thisYear,
      $thisYearPlus1 => $thisYearPlus1,
      $thisYearPlus2 => $thisYearPlus2,
    ];

    if ($this->applicationNumber) {
      $dataIntegrityStatus = $this->applicationHandler->validateDataIntegrity(
      NULL,
      $submissionData,
      $this->applicationNumber,
      $submissionData['metadata']['saveid'] ?? '');

      if ($dataIntegrityStatus != 'OK') {
        $form['#disabled'] = TRUE;
        $this->messenger()->addWarning($this->t('Data integrity mismatch. Please refresh form in a moment'));
      }
    }

    $all_errors = $this->grantsFormNavigationHelper->getAllErrors($webform_submission);

    // Foreach ($all_errors as $page => $errors) {
    //      /**.
    /** * @var string $fieldName */
    /** * @var  \Drupal\Core\StringTranslation\TranslatableMarkup $error */
    // */
    //      foreach ($errors as $fieldName => $error) {
    //        foreach ($form['elements'] as $key1 => $element) {
    //          foreach ($element as $key2 => $element2) {
    //            if (!str_starts_with($key2, '#')) {
    //              // If found on this level.
    //              if ($fieldName == $key2) {
    //                $e = 'asdf';
    //              }
    //              elseif (is_array($element2)) {
    //                foreach ($element2 as $key3 => $element3) {
    //                  if (!str_starts_with($key3, '#')) {
    //                    // If found on this level.
    //                    if ($fieldName == $key3) {
    //                      // $element3['errors'][] = $error;
    //                      //                      $form['elements'][$key1][$key2][$key3]['#errors'][] = $error;
    //                    }
    //                    elseif (is_array($element3)) {
    //                      foreach ($element3 as $key4 => $element4) {
    //                        if (!str_starts_with($key4, '#')) {
    //                          if ($fieldName == $key4) {
    //                            $e = 'asdf';
    //                          }
    //                          if (is_array($element4)) {
    //                            $d = 'asfd';
    //                          }
    //                        }
    //                      }
    //                    }
    //                  }
    //                }
    //              }
    //            }
    //          }
    //        }
    //        $d = 'asdf';
    //        // $element["toiminnasta_vastaavat_henkilot"]["community_officials"]
    //      }
    //    }.
    $form['#errors'] = $all_errors;
  }

  /**
   * Alter navigation elements on forms.
   *
   * @param array $form
   *   Form in question.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Forms state.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The submission.
   *
   * @throws \Exception
   */
  public function alterFormNavigation(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Log the current page.
    $current_page = $webform_submission->getCurrentPage();
    $webform = $webform_submission->getWebform();
    // Actions to perform if there are pages.
    if ($webform->hasWizardPages()) {
      $validations = [
        '::validateForm',
        '::draft',
      ];
      // Allow forward access to all but the confirmation page.
      foreach ($form_state->get('pages') as $page_key => $page) {
        // Allow user to access all but the confirmation page.
        if ($page_key != 'webform_confirmation') {
          $form['pages'][$page_key]['#access'] = TRUE;
          $form['pages'][$page_key]['#validate'] = $validations;
        }
      }
      // Set our loggers to the draft update if it is set.
      if (isset($form['actions']['draft'])) {
        // Add a logger to the next validators.
        $form['actions']['draft']['#validate'] = $validations;
      }
      // Set our loggers to the previous update if it is set.
      if (isset($form['actions']['wizard_prev'])) {
        // Add a logger to the next validators.
        $form['actions']['wizard_prev']['#validate'] = $validations;
      }
      // Add a custom validator to the final submit.
      $form['actions']['submit']['#validate'][] = 'grants_handler_submission_validation';
      // Log the page visit.
      $visited = $this->grantsFormNavigationHelper->hasVisitedPage($webform_submission, $current_page);
      // Log the page if it has not been visited before.
      if (!$visited) {

        $this->grantsFormNavigationHelper->logPageVisit($webform_submission, $current_page);
      }

      // If there's errors on the form (any page), disable form submit.
      $current_errors = $webform->getState('current_errors');
      if (is_array($current_errors) && !GrantsHandler::emptyRecursive($current_errors)) {
        $form["actions"]["submit"]['#disabled'] = TRUE;
      }
    }
  }

  /**
   * Get triggering element name from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Form state.
   *
   * @return mixed
   *   Triggering element name if there's one.
   */
  public function getTriggeringElementName(?FormStateInterface $form_state): mixed {

    if ($this->triggeringElement == '') {
      $triggeringElement = $form_state->getTriggeringElement();
      if (is_string($triggeringElement['#submit'][0])) {
        $this->triggeringElement = $triggeringElement['#submit'][0];
      }
    }

    return $this->triggeringElement;
  }

  /**
   * Method to figure out if formUpdate should be false/true?
   *
   * The thing is that the Avustus2 is not very smart about when it fetches
   * data from ATV. Initial import from ATV MUST have fromUpdate FALSE, and
   * any subsequent update will have to have it as TRUE. The application status
   * handling makes this possibly very complicated, hence separate method
   * figuring it out.
   *
   * @return bool
   *   Set form update value either TRUE / FALSE
   */
  private function getFormUpdate(): bool {

    $applicationNumber = !empty($this->applicationNumber) ? $this->applicationNumber : $this->submittedFormData["application_number"] ?? '';
    $newStatus = $this->submittedFormData["status"];
    $oldStatus = '';

    if ($applicationNumber != '') {
      // Get document from ATV.
      try {
        $document = $this->applicationHandler->getAtvDocument($applicationNumber);
        $oldStatus = $document->getStatus();
      }
      catch (TempStoreException | AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      }
    }
    // If new status is submitted, ie save to Avus2..
    if ($newStatus == ApplicationHandler::$applicationStatuses['SUBMITTED']) {
      // ..and if application is not yet in Avus2, form update needs to be FALSE
      // or we get error updating nonexistent application
      if ($oldStatus == ApplicationHandler::$applicationStatuses['DRAFT']) {
        return FALSE;
      }
      // also, if this is new application but put directly to submitted mode,
      // we need to have update also FALSE.
      elseif ($oldStatus == '') {
        return FALSE;
      }
      // In all other cases we can have update as TRUE since we want to
      // actually update data in Avus2 & ATV.
      else {
        return TRUE;
      }
    }

    // If new status is DRAFT, we don't really care about this value since
    // these are not uploaded to Avus2 just put it to false in case of some
    // other things need this.
    if ($newStatus == ApplicationHandler::$applicationStatuses['DRAFT']) {
      return FALSE;
    }

    // In other statuses and situations we can just return true bc we want to
    // actually update data.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    parent::validateForm($form, $form_state, $webform_submission);

    $current_page = $webform_submission->getCurrentPage();

    // These need to be set here to the handler object, bc we do the saving to
    // ATV in postSave and in that method these are not available.
    // and the triggering element is pivotal in figuring if we're
    // saving draft or not.
    $triggeringElement = $this->getTriggeringElementName($form_state);
    // Form values are needed for parsing attachment in postSave.
    $this->formTemp = $form;
    // Does these need to be done in validate??
    // maybe the submittedData is even not required?
    $this->submittedFormData = $this->massageFormValuesFromWebform($webform_submission);

    $this->setTotals();

    // Merge form sender data from handler.
    $this->submittedFormData = array_merge(
      $this->submittedFormData,
      $this->applicationHandler->parseSenderDetails());

    $this->submittedFormData['applicant_type'] = $form_state
      ->getValue('applicant_type');

    foreach ($this->submittedFormData["myonnetty_avustus"] as $key => $value) {
      $this->submittedFormData["myonnetty_avustus"][$key]['issuerName'] =
        $value['issuer_name'];
      unset($this->submittedFormData["myonnetty_avustus"][$key]['issuer_name']);
    }
    foreach ($this->submittedFormData["haettu_avustus_tieto"] as $key => $value) {
      $this->submittedFormData["haettu_avustus_tieto"][$key]['issuerName'] =
        $value['issuer_name'];
      unset($this->submittedFormData["haettu_avustus_tieto"][$key]['issuer_name']);
    }

    // Set form timestamp to current time.
    // apparently this is always set to latest submission.
    $dt = new \DateTime();
    $dt->setTimezone(new \DateTimeZone('Europe/Helsinki'));
    $this->submittedFormData['form_timestamp'] = $dt->format('Y-m-d\TH:i:s');

    // Get regdate from profile data and format it for Avustus2
    // This data is immutable for end user so safe to this way.
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $grantsProfile = $this->grantsProfileService->getGrantsProfileContent($selectedCompany);
    $regDate = new DrupalDateTime($grantsProfile["registrationDate"], 'Europe/Helsinki');
    $this->submittedFormData["registration_date"] = $regDate->format('Y-m-d\TH:i:s');

    // Set form update value based on new & old status + Avus2 logic.
    $this->submittedFormData["form_update"] = $this->getFormUpdate();

    $this->setFromThirdPartySettings($webform_submission->getWebform());

    // Figure out status for this application.
    $this->newStatus = $this->applicationHandler->getNewStatus(
      $triggeringElement,
      $form,
      $form_state,
      $this->submittedFormData,
      $webform_submission
    );
    // Set status for data.
    $this->submittedFormData['status'] = $this->newStatus;

    // Loop through fieldnames and validate fields.
    foreach (AttachmentHandler::getAttachmentFieldNames() as $fieldName) {
      AttachmentHandler::validateAttachmentField(
        $fieldName,
        $form_state,
        $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$fieldName]["#title"],
        $triggeringElement
      );
    }
    $current_errors = $this->logErrors($webform_submission, $form_state);

    // If ($triggeringElement == '::next') {
    // // parent::validateForm($form, $form_state, $webform_submission);.
    // }
    // if ($triggeringElement == '::gotoPage') {
    // }
    // if ($triggeringElement == '::submitForm') {
    // }.
    if ($triggeringElement == '::submit') {
      $d = 'asdf';
      if (self::emptyRecursive($current_errors)) {
        $applicationData = $this->applicationHandler->webformToTypedData(
          $this->submittedFormData,
          '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
          'grants_metadata_yleisavustushakemus'
        );
        $violations = $this->applicationHandler->validateApplication(
          $applicationData,
          $form,
          $form_state,
          $webform_submission
        );

        if ($violations->count() === 0) {
          // If we have no violations clear all errors.
          $form_state->clearErrors();
          // So we well proceed to confirmForm.
        }
        else {
          // If we HAVE errors, then refresh them from the.
          $current_errors = $this->logErrors($webform_submission, $form_state);
        }
      }
    }
  }

  /**
   * Is recursive array empty.
   *
   * @param array $value
   *   Array to check.
   *
   * @return bool
   *   Empty or not?
   */
  public static function emptyRecursive(array $value): bool {
    $empty = TRUE;
    array_walk_recursive($value, function ($item) use (&$empty) {
      $empty = $empty && empty($item);
    });
    return $empty;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $triggeringElement = $this->getTriggeringElementName($form_state);

    // Because of funky naming convention, we need to manually
    // set purpose field value.
    // This is populated from grants profile so it's just passing this on.
    if (isset($this->submittedFormData["community_purpose"])) {
      $this->submittedFormData["business_purpose"] = $this->submittedFormData["community_purpose"];
    }

    // If for some reason we don't have application number at this point.
    if (!isset($this->applicationNumber)) {
      // But if one is coming from form (hidden field)
      if (isset($this->submittedFormData['application_number'])) {
        // Use it.
        $this->applicationNumber = $this->submittedFormData['application_number'];
      }
      else {
        // But if we have saved webform earlier, we can get the application
        // number from submission serial.
        if ($webform_submission->id()) {
          $this->applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
        }
        // Hopefully we never reach here, but there should be additional checks
        // for application number to exists.
        // and it's no biggie since we can always get it from the method above
        // as long as we have our submission object.
      }
    }

    // These need to be set here to the handler object, bc we do the saving to
    // ATV in postSave and in that method these are not available.
    // and the triggering element is pivotal in figuring if we're
    // saving draft or not.
    $this->triggeringElement = $this->getTriggeringElementName($form_state);
    // Form values are needed for parsing attachment in postSave.
    $this->formTemp = $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {

    // don't save ip address.
    $webform_submission->remote_addr->value = '';

    if (empty($this->submittedFormData)) {
      // Submission data is not saved in storage controller,
      // so save data here for later usage.
      $this->submittedFormData = $this->massageFormValuesFromWebform($webform_submission);

    }

    // If for some reason applicant type is not present, make sure it get's
    // added otherwise validation fails.
    if (!isset($this->submittedFormData['applicant_type'])) {
      $this->submittedFormData['applicant_type'] = $this->grantsProfileService->getApplicantType();
    }

    if (isset($this->submittedFormData["community_purpose"])) {
      $this->submittedFormData["business_purpose"] = $this->submittedFormData["community_purpose"];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    // let's invalidate cache for this submission.
    $this->entityTypeManager->getViewBuilder($webform_submission->getWebform()
      ->getEntityTypeId())->resetCache([
        $webform_submission,
      ]);

    if (empty($this->submittedFormData)) {
      return;
    }

    if (!isset($this->submittedFormData['application_number']) || $this->submittedFormData['application_number'] == '') {
      if (!isset($this->applicationNumber) || $this->applicationNumber == '') {
        $this->applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
      }
      if (isset($this->applicationTypeID) || $this->applicationTypeID == '') {
        $this->submittedFormData['application_type_id'] = $this->applicationTypeID;
      }
      if (isset($this->applicationType) || $this->applicationType == '') {
        $this->submittedFormData['application_type'] = $this->applicationType;
      }
      if (isset($this->applicationNumber) || $this->applicationNumber == '') {
        $this->submittedFormData['application_number'] = $this->applicationNumber;
      }
    }
    else {
      $this->applicationNumber = $this->submittedFormData['application_number'];
    }

    // If triggering element is either draft save or proper one,
    // we want to parse attachments from form.
    if ($this->triggeringElement == '::submitForm') {
      $webForm = $webform_submission->getWebform();

      // submitForm is triggering element when saving as draft.
      // Parse attachments to data structure.
      $this->submittedFormData['attachments'] = $this->attachmentHandler->parseAttachments(
        $this->formTemp,
        $this->submittedFormData,
        $this->applicationNumber
      );

      try {
        $applicationData = $this->applicationHandler->webformToTypedData(
          $this->submittedFormData,
          '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
          'grants_metadata_yleisavustushakemus'
        );
      }
      catch (ReadOnlyException $e) {
        // @todo log errors here.
      }

      $applicationUploadStatus = $this->applicationHandler->handleApplicationUpload(
        $applicationData,
        $this->applicationNumber
      );

      if ($applicationUploadStatus) {
        $this->attachmentHandler->handleApplicationAttachments(
          $this->applicationNumber,
          $webform_submission
        );

        $this->messenger()
          ->addStatus(
            $this->t(
              'Grant application (<span id="saved-application-number">@number</span>) saved as DRAFT',
              [
                '@number' => $this->applicationNumber,
              ]
            ),
            TRUE
          );

        // Try to give integration time to do it's
        // thing before we try to go there.
        sleep(4);

        $redirectUrl = Url::fromRoute('grants_handler.view_application', [
          'submission_id' => $this->applicationNumber,
        ]);
      }
      else {
        $redirectUrl = Url::fromRoute(
          '<front>',
          [
            'attributes' => [
              'data-drupal-selector' => 'application-saving-failed-link',
            ],
          ]
        );

        $this->messenger()
          ->addStatus(
            $this->t(
              'Grant application (<span id="saved-application-number">@number</span>) saving failed. Please contact support from @link',
              [
                '@number' => $this->applicationNumber,
                '@link' => '<a href="/" >here</a>',
              ]
            ),
            TRUE
          );
      }
      $redirectResponse = new RedirectResponse($redirectUrl->toString());
      $this->applicationHandler->clearCache($this->applicationNumber);
      $redirectResponse->send();

      // Return $redirectResponse;.
    }
    if ($this->triggeringElement == '::submit') {
      // Submit is trigger when exiting from confirmation page.
      // Parse attachments to data structure.
      $this->submittedFormData['attachments'] = $this->attachmentHandler->parseAttachments(
        $this->formTemp,
        $this->submittedFormData,
        $this->applicationNumber
      );

      // Try to update status only if it's allowed.
      if (ApplicationHandler::canSubmissionBeSubmitted($webform_submission, NULL)) {
        $this->submittedFormData['status'] = ApplicationHandler::$applicationStatuses['SUBMITTED'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    try {

      $this->submittedFormData['status'] = $this->applicationHandler->getNewStatus(
        $this->triggeringElement,
        $form,
        $form_state,
        $this->submittedFormData,
        $webform_submission
      );

      $applicationData = $this->applicationHandler->webformToTypedData(
        $this->submittedFormData,
        '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
        'grants_metadata_yleisavustushakemus'
      );

      $applicationUploadStatus = $this->applicationHandler->handleApplicationUpload(
        $applicationData,
        $this->applicationNumber
      );

      if ($applicationUploadStatus) {
        $this->attachmentHandler->handleApplicationAttachments(
          $this->applicationNumber,
          $webform_submission
        );

        $viewApplicationUrl = Url::fromRoute('grants_handler.view_application', [
          'submission_id' => $this->applicationNumber,
        ]);

        $this->messenger()
          ->addStatus(
            $this->t(
              'Grant application (<span id="saved-application-number">@number</span>) saved. You can view your new application from @here.',
              [
                '@number' => $this->applicationNumber,
                '@here' => Link::fromTextAndUrl('here', $viewApplicationUrl)
                  ->toString(),
              ]
            )
          );

        $form_state->setRedirect(
                  'grants_handler.completion',
                  ['submission_id' => $this->applicationNumber],
                  [
                    'attributes' => [
                      'data-drupal-selector' => 'application-saved-successfully-link',
                    ],
                  ]
                );
        $redirectUrl = Url::fromRoute(
                  'grants_handler.completion',
                  ['submission_id' => $this->applicationNumber],
                  [
                    'attributes' => [
                      'data-drupal-selector' => 'application-saved-successfully-link',
                    ],
                  ]
                 );
        // $redirectResponse->send();
      }
      else {
        $this->messenger()
          ->addStatus(
            $this->t(
              'Grant application (<span id="saved-application-number">@number</span>) saving failed. Please contact support from @link',
              [
                '@number' => $this->applicationNumber,
                '@link' => 'Support link',
              ]
            )
          );
      }
      // $redirectResponse = new RedirectResponse($redirectUrl->toString());
      //  return $redirectResponse;
      $form_state->setRedirect(
                'grants_handler.completion',
                ['submission_id' => $this->applicationNumber],
                [
                  'attributes' => [
                    'data-drupal-selector' => 'application-saved-successfully-link',
                  ],
                ]
              );
    }
    catch (\Exception $e) {
      // @todo log errors properly
    }
  }

  /**
   * Helper to find out if we're debugging or not.
   *
   * @return bool
   *   If debug mode is on or not.
   */
  public function isDebug(): bool {
    return !empty($this->configuration['debug']);
  }

  /**
   * Display the invoked plugin method to end user.
   *
   * @param string $method_name
   *   The invoked method name.
   * @param string $context1
   *   Additional parameter passed to the invoked method name. *. *. *. *. *.
   *   *. *. *. *.
   */
  public function debug($method_name, $context1 = NULL) {
    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@id' => $this->getHandlerId(),
        '@class_name' => get_class($this),
        '@method_name' => $method_name,
        '@context1' => $context1,
      ];
      $this->messenger()
        ->addWarning($this->t('Invoked @id: @class_name:@method_name @context1', $t_args), TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
  }

  /**
   * Cleans up non-array values from array structure.
   *
   * This is due to some configuration error with messages/statuses/events
   * that I'm not able to find.
   *
   * @param array|null $value
   *   Array we need to flatten.
   *
   * @return array
   *   Fixed array
   */
  public static function cleanUpArrayValues(mixed $value): array {
    $retval = [];
    if (is_array($value)) {
      foreach ($value as $k => $v) {
        if (is_array($v)) {
          $retval[] = $v;
        }
      }
    }
    return $retval;
  }

  /**
   * Save logged errors to webform state.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Submission object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   All current errors.
   */
  public function logErrors(WebformSubmissionInterface $webform_submission, FormStateInterface $form_state): array {
    try {

      $fe = $form_state->getErrors();

      // Log current errors.
      $current_errors = $this->grantsFormNavigationHelper->logPageErrors($webform_submission, $form_state);

      // And add existing ones to form state to be processed in theme files.
      $webform = $webform_submission->getWebform();
      $webform->setState('current_errors', $current_errors);
    }
    catch (\Exception $e) {
      $current_errors = [];
      // @todo add logger
    }
    return $current_errors;
  }

  /**
   * Parse things from form 3rd party settings to this application.
   *
   * @param \Drupal\webform\Entity\Webform $webform
   *   Webform used.
   */
  protected function setFromThirdPartySettings(Webform $webform): void {
    // Make sure we have application type id set.
    if (!isset($this->applicationTypeID) || $this->applicationTypeID == '') {
      if (isset($this->submittedFormData['application_type_id'])) {
        $this->applicationTypeID = $this->submittedFormData['application_type_id'];
      }
      else {
        $this->applicationTypeID = $webform
          ->getThirdPartySetting('grants_metadata', 'applicationTypeID');
        $this->submittedFormData['application_type_id'] = $this->applicationTypeID;
      }
    }

    // Make sure we have our application type set.
    if (!isset($this->applicationType) || $this->applicationType == '') {
      if (isset($this->submittedFormData['application_type']) && $this->submittedFormData['application_type'] != '') {
        $this->applicationTypeID = $this->submittedFormData['application_type'];
      }
      else {
        $this->applicationType = $webform
          ->getThirdPartySetting('grants_metadata', 'applicationType');
        $this->submittedFormData['application_type'] = $this->applicationType;
      }
    }
  }

}
