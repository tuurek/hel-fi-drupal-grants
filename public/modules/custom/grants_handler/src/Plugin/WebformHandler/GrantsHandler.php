<?php

namespace Drupal\grants_handler\Plugin\WebformHandler;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\Url;
use Drupal\grants_attachments\AttachmentHandler;
use Drupal\grants_formnavigation\GrantsFormNavigationHelper;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_handler\GrantsHandlerNavigationHelper;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
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
   * Set up sender details from helsinkiprofiili data.
   */
  private function parseSenderDetails() {
    // Set sender information after save so no accidental saving of data.
    // @todo Think about how sender info should be parsed, maybe in own.
    $userProfileData = $this->userExternalData->getUserProfileData();
    $userData = $this->userExternalData->getUserData();

    if (isset($userProfileData["myProfile"])) {
      $data = $userProfileData["myProfile"];
    }
    else {
      $data = $userProfileData;
    }

    // If no userprofile data, we need to hardcode these values.
    // @todo Remove hardcoded values when tunnistamo works.
    if ($userProfileData == NULL || $userData == NULL) {
      $this->submittedFormData['sender_firstname'] = 'NoTunnistamo';
      $this->submittedFormData['sender_lastname'] = 'NoTunnistamo';
      $this->submittedFormData['sender_person_id'] = 'NoTunnistamo';
      $this->submittedFormData['sender_user_id'] = '280f75c5-6a20-4091-b22d-dfcdce7fef60';
      $this->submittedFormData['sender_email'] = 'NoTunnistamo';

    }
    else {
      $userData = $this->userExternalData->getUserData();
      $this->submittedFormData['sender_firstname'] = $data["verifiedPersonalInformation"]["firstName"];
      $this->submittedFormData['sender_lastname'] = $data["verifiedPersonalInformation"]["lastName"];
      $this->submittedFormData['sender_person_id'] = $data["verifiedPersonalInformation"]["nationalIdentificationNumber"];
      $this->submittedFormData['sender_user_id'] = $userData["sub"];
      $this->submittedFormData['sender_email'] = $data["primaryEmail"]["email"];
    }
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
        if ($webform_submission->serial()) {
          $this->applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
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

    // These both are required to be selected.
    // probably will change when we have proper company selection process.
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $applicantType = $this->grantsProfileService->getApplicantType();
    if ($applicantType === NULL) {

      \Drupal::messenger()
        ->addError(t('You need to select applicant type.'));

      $url = Url::fromRoute('grants_profile.applicant_type', [
        'destination' => $values["uri"],
      ])
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($url);
      $response->send();
    }
    else {
      $this->applicantType = $this->grantsProfileService->getApplicantType();
    }

    if ($selectedCompany == NULL) {
      \Drupal::messenger()
        ->addError(t("You need to select company you're acting behalf of."));

      $url = Url::fromRoute('grants_profile.show', [
        'destination' => $values["uri"],
      ])
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($url);
      $response->send();
    }

  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $this->alterFormNavigation($form, $form_state, $webform_submission);

    // If we have an existing submission, then load application number.
    //    if ($webform_submission->id()) {
    //      //      $this->applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
    //      $d = 'asdf';
    //    }

    $this->applicationType = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationType');
    $this->applicationTypeID = $webform_submission->getWebform()
      ->getThirdPartySetting('grants_metadata', 'applicationTypeID');

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
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *
   * @return void
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
      elseif ($current_page != 'webform_confirmation') {
        // Display any errors.
        $errors = $this->grantsFormNavigationHelper->getErrors($webform_submission);
        // Make sure we haven't already set errors.
        if (!empty($errors[$current_page])) {
          foreach ($errors[$current_page] as $error) {
            \Drupal::messenger()->addError($error);
          }
        }
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
      } catch (TempStoreException|AtvDocumentNotFoundException|AtvFailedToConnectException|GuzzleException $e) {
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
    array                      &$form,
    FormStateInterface         $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    parent::validateForm($form, $form_state, $webform_submission);

    // Loop through fieldnames and validate fields.
    foreach (AttachmentHandler::getAttachmentFieldNames() as $fieldName) {
      //      $fValues = $form_state->getValue($fieldName);
      AttachmentHandler::validateAttachmentField(
        $fieldName,
        $form_state,
        $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$fieldName]["#title"]
      );
    }

    try {
      $current_errors = $this->grantsFormNavigationHelper->logPageErrors($webform_submission, $form_state);

      $webform = $webform_submission->getWebform();
      $webform->setState('current_errors', $current_errors);
    } catch (\Exception $e) {
      // TODO: add logger
    }
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

    if ($triggeringElement == '::next') {
      //      parent::validateForm($form, $form_state, $webform_submission);

      $d = 'asdf';
    }

    if ($triggeringElement == '::gotoPage') {
      $d = 'asdf';
    }
    if ($triggeringElement == '::submitForm') {
      $d = 'asdf';
    }
    if ($triggeringElement == '::submit') {

      $d = 'asdf';
      if (self::emptyRecursive($current_errors)) {

        $applicationData = $this->applicationHandler->webformToTypedData(
          $this->submittedFormData,
          '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
          'grants_metadata_yleisavustushakemus'
        );
        $violations = $this->applicationHandler->validateApplication($applicationData);

        $d = 'c<';

        if ($violations->count() > 0) {
          foreach ($violations as $violation) {
            // Print errors by form item name.
            $form_state->setErrorByName(
              $violation->getPropertyPath(),
              $violation->getMessage());
          }
        }
      }
      else {

        $d = 'asdf';
      }
    }

  }

  /**
   * @param array $value
   *
   * @return bool
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
  function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $triggeringElement = $this->getTriggeringElementName($form_state);

    if ($triggeringElement == '::submitForm') {
      $this->submittedFormData = $this->massageFormValuesFromWebform($webform_submission);
    }

    // Because of funky naming convention, we need to manually
    // set purpose field value.
    // This is populated from grants profile so it's just passing this on.
    if (isset($this->submittedFormData["community_purpose"])) {
      $this->submittedFormData["business_purpose"] = $this->submittedFormData["community_purpose"];
    }

    $this->submittedFormData = $this->massageFormValuesFromWebform($webform_submission);
    $this->submittedFormData['applicant_type'] = $form_state->getValue('applicant_type');

    if ($triggeringElement == '::submit') {
      // Try to update status only if it's allowed.
      if (ApplicationHandler::canSubmissionBeSubmitted($webform_submission, NULL)) {
        $this->submittedFormData['status'] = ApplicationHandler::$applicationStatuses['SUBMITTED'];
      }
    }

    foreach ($this->submittedFormData["myonnetty_avustus"] as $key => $value) {
      $this->submittedFormData["myonnetty_avustus"][$key]['issuerName'] = $value['issuer_name'];
      unset($this->submittedFormData["myonnetty_avustus"][$key]['issuer_name']);
    }
    foreach ($this->submittedFormData["haettu_avustus_tieto"] as $key => $value) {
      $this->submittedFormData["haettu_avustus_tieto"][$key]['issuerName'] = $value['issuer_name'];
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

    // Make sure we have application type id set.
    if (!isset($this->applicationTypeID)) {
      if (isset($this->submittedFormData['application_type_id'])) {
        $this->applicationTypeID = $this->submittedFormData['application_type_id'];
      }
      else {
        $this->applicationTypeID = $webform_submission->getWebform()
          ->getThirdPartySetting('grants_metadata', 'applicationTypeID');
        $this->submittedFormData['application_type_id'] = $this->applicationTypeID;
      }
    }

    // Make sure we have our application type set.
    if (!isset($this->applicationType)) {
      if (isset($this->submittedFormData['application_type'])) {
        $this->applicationTypeID = $this->submittedFormData['application_type'];
      }
      else {
        $this->applicationType = $webform_submission->getWebform()
          ->getThirdPartySetting('grants_metadata', 'applicationType');
        $this->submittedFormData['application_type'] = $this->applicationType;
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

    if (!empty($this->submittedFormData)) {
      $this->setTotals();
      $this->parseSenderDetails();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    if (!isset($this->submittedFormData['application_number'])) {
      if (!isset($this->applicationNumber) || $this->applicationNumber == '') {
        $this->applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
      }
      $this->submittedFormData['application_type_id'] = $this->applicationTypeID;
      $this->submittedFormData['application_type'] = $this->applicationType;
      $this->submittedFormData['application_number'] = $this->applicationNumber;
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
        $this->applicationNumber);

      // If saved as draft, force status in Avus2 also to DRAFT.
      $this->submittedFormData['status'] = ApplicationHandler::$applicationStatuses['DRAFT'];

      try {
        $applicationData = $this->applicationHandler->webformToTypedData(
          $this->submittedFormData,
          '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
          'grants_metadata_yleisavustushakemus'
        );
      } catch (ReadOnlyException $e) {
        $d = 'adsf';
      }

      $applicationUploadStatus = $this->applicationHandler->handleApplicationUpload(
        $applicationData
      );

      if ($applicationUploadStatus) {

        $this->attachmentHandler->handleApplicationAttachments(
          $this->applicationNumber,
          $webform_submission
        );

        $this->messenger()
          ->addStatus(
            t(
              'Grant application (<span id="saved-application-number">@number</span>) saved as DRAFT',
              [
                '@number' => $this->applicationNumber,
              ]));

        $redirectUrl = Url::fromRoute('entity.webform.user.submission.edit', [
          'webform' => $webForm->id(),
          'webform_submission' => $webform_submission->id(),
        ]);

        return new RedirectResponse($redirectUrl->toString());

      }
      else {
        $url = Url::fromRoute(
          'front',
          [
            'attributes' => [
              'data-drupal-selector' => 'application-saving-failed-link',
            ],
          ]
        );

        $this->messenger()
          ->addStatus(
            t(
              'Grant application (<span id="saved-application-number">@number</span>) saving failed. Please contact support from @link',
              [
                '@number' => $this->applicationNumber,
                '@link' => Link::fromTextAndUrl('here', $url)->toString(),
              ]));
      }

    }
    if ($this->triggeringElement == '::submit') {

      // Submit is trigger when exiting from confirmation page.
      // Parse attachments to data structure.
      $this->submittedFormData['attachments'] = $this->attachmentHandler->parseAttachments(
        $this->formTemp,
        $this->submittedFormData,
        $this->applicationNumber);

      // Try to update status only if it's allowed.
      if (ApplicationHandler::canSubmissionBeSubmitted($webform_submission, NULL)) {
        $this->submittedFormData['status'] = ApplicationHandler::$applicationStatuses['SUBMITTED'];
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  function confirmForm(
    array                      &$form,
    FormStateInterface         $form_state,
    WebformSubmissionInterface $webform_submission) {

    try {

      // Try to update status only if it's allowed.
      if (ApplicationHandler::canSubmissionBeSubmitted($webform_submission, NULL)) {
        $this->submittedFormData['status'] = ApplicationHandler::$applicationStatuses['SUBMITTED'];
      }

      $applicationData = $this->applicationHandler->webformToTypedData(
        $this->submittedFormData,
        '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
        'grants_metadata_yleisavustushakemus'
      );

      $applicationUploadStatus = $this->applicationHandler->handleApplicationUpload(
        $applicationData
      );

      if ($applicationUploadStatus) {

        $this->attachmentHandler->handleApplicationAttachments(
          $this->applicationNumber,
          $webform_submission
        );

        $this->messenger()
          ->addStatus(
            t(
              'Grant application (<span id="saved-application-number">@number</span>) saved.',
              [
                '@number' => $this->applicationNumber,
              ]));

        $url = Url::fromRoute(
          'grants_handler.completion',
          ['submissionId' => $this->applicationNumber],
          [
            'attributes' => [
              'data-drupal-selector' => 'application-saved-successfully-link',
            ],
          ]
        );

        $form_state->setRedirectUrl($url);

      }
      else {
        $url = Url::fromRoute(
          'front',
          [
            'attributes' => [
              'data-drupal-selector' => 'application-saving-failed-link',
            ],
          ]
        );

        $this->messenger()
          ->addStatus(
            t(
              'Grant application (<span id="saved-application-number">@number</span>) saving failed. Please contact support from @link',
              [
                '@number' => $this->applicationNumber,
                '@link' => Link::fromTextAndUrl('here', $url)->toString(),
              ]));
      }

    } catch (\Exception $e) {
      $d = 'asdf';
    }

  }

  /**
   * Helper to find out if we're debugging or not.
   *
   * @return bool
   *   If debug mode is on or not. * * * * * * * * *
   */
  function isDebug(): bool {
    return !empty($this->configuration['debug']);
  }

  /**
   * Display the invoked plugin method to end user.
   *
   * @param string $method_name
   *   The invoked method name.
   * @param string $context1
   *   Additional parameter passed to the invoked method name. *. *. *. *. *.
   *   *. *. *. *
   */
  function debug($method_name, $context1 = NULL) {
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
   * {@inheritdoc} * * * * * * * * *
   */
  function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
  }

  /**
   * {@inheritdoc} * * * * * * * * *
   */
  function preprocessConfirmation(array &$variables) {
    $this->debug(__FUNCTION__);
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
  static public function cleanUpArrayValues(mixed $value): array {
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

}
