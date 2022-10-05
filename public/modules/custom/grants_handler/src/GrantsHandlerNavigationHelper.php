<?php

namespace Drupal\grants_handler;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Defines a helper class for the webform navigation module.
 */
class GrantsHandlerNavigationHelper {

  /**
   * Name of the table where log entries are stored.
   */
  protected const TABLE = 'grants_handler_log';

  /**
   * Name of the error operation.
   */
  public const ERROR_OPERATION = 'errors';

  /**
   * Name of the page visited operation.
   */
  protected const PAGE_VISITED_OPERATION = 'page visited';

  /**
   * Name of the navigation handler.
   */
  protected const HANDLER_ID = 'grants_handler_navigation';

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Access to profile data.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  /**
   * Access to private session store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $store;

  /**
   * AutosaveHelper constructor.
   */
  public function __construct(
    Connection $datababse,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    PrivateTempStoreFactory $tempStoreFactory
  ) {

    $this->database = $datababse;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $this->store = $tempStoreFactory->get('grants_formnavigation');
  }

  /**
   * Gets the current submission page.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   *
   * @return string
   *   The current submission page ID.
   */
  public function getCurrentPage(WebformSubmissionInterface $webform_submission) {
    $pages = $webform_submission->getWebform()
      ->getPages('edit', $webform_submission);
    return empty($webform_submission->getCurrentPage()) ? array_keys($pages)[0] : $webform_submission->getCurrentPage();
  }

  /**
   * Has visited page.
   *
   * With saved submissions, saves errors & page visits to db, but when
   * submission is not yet saved, saves info to user local storage. When
   * submission is saved, data is merged and saved properly to db. Even some
   * proper audit log could be built on top of this functionality.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param string $page
   *   The page we're checking.
   *
   * @return bool
   *   TRUE if the user has previously visited the page.
   */
  public function hasVisitedPage(WebformSubmissionInterface $webform_submission, $page): bool {
    // Get outta here if the submission hasn't been saved yet.
    if (empty($webform_submission->id()) || empty($page)) {
      return FALSE;
    }

    $data = $webform_submission->getData();

    // Set the page to the current page if it is empty.
    if (empty($page)) {
      $page = $this->getCurrentPage($webform_submission);
    }

    $query = $this->database->select(self::TABLE, 'l');
    $query->condition('webform_id', $webform_submission->getWebform()->id());

    if (isset($data['application_number'])) {
      $query->condition('application_number', $data['application_number']);
    }
    else {
      $query->condition('sid', $webform_submission->id());
    }

    $query->condition('operation', self::PAGE_VISITED_OPERATION);
    $query->condition('page', $page);
    $query->fields('l', [
      'lid',
      'sid',
      'data',
    ]);
    $submission_log = $query->execute()->fetch();
    return !empty($submission_log);
  }

  /**
   * Gets either all errors or errors for a specific page.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param string|null $page
   *   Set to page name if you only want the data for a particular page.
   *
   * @return array
   *   An array of errors.
   */
  public function getErrors(
    WebformSubmissionInterface $webform_submission,
    string $page = NULL) {

    if (empty($webform_submission->id())) {
      return [];
    }

    $data = $webform_submission->getData();

    $query = $this->database->select(self::TABLE, 'l');
    $query->condition('webform_id', $webform_submission->getWebform()->id());
    $query->condition('operation', self::ERROR_OPERATION);

    if (isset($data['application_number'])) {
      $query->condition('application_number', $data['application_number']);
    }
    else {
      $query->condition('sid', $webform_submission->id());
    }

    if (!empty($page)) {
      $query->condition('page', $page);
    }

    $query->fields('l', [
      'lid',
      'sid',
      'data',
    ]);
    $query->orderBy('l.lid', 'DESC');
    $submission_log = $query->execute()->fetch();

    if ($submission_log === FALSE) {
      return [];
    }

    $data = unserialize($submission_log->data);

    return $data[$page] ?? $data;
  }

  /**
   * Get errors for all pages any status.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Submission object.
   *
   * @return array
   *   All errors.
   */
  public function getAllErrors(WebformSubmissionInterface $webform_submission) {
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $webform_submission->getWebform();

    // If called without saved submission, let's not even try to get errors.
    if (!$webform_submission->id()) {
      return [];
    }

    // Get pages.
    $pages = $webform->getPages('edit', $webform_submission);

    $all_errors = [];
    foreach ($pages as $name => $page) {
      $err = $this->getErrors($webform_submission, $name);
      if (is_array($err)) {
        $all_errors[$name] = $err[$name] ?? $err;
      }
    }

    return $all_errors;
  }

  /**
   * Filter page visits from stored data.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Submission in question. Either saved or non saved one.
   *
   * @return array
   *   Stored page visits.
   */
  public function getPageVisits(WebformSubmissionInterface $webform_submission) {
    if ($webform_submission->id()) {
      $data = $webform_submission->getData();
      $query = $this->database->select(self::TABLE, 'l');
      if (isset($data['application_number'])) {
        $query->condition('application_number', $data['application_number']);
      }
      else {
        $query->condition('sid', $webform_submission->id());
      }
      $query->condition('webform_id', $webform_submission->getWebform()->id());

      $query->condition('operation', self::PAGE_VISITED_OPERATION);
      $query->fields('l', [
        'lid',
        'sid',
        'page',
        'data',
      ]);
      $query->orderBy('l.lid', 'DESC');
      // $query->range(0, 1);
      $submission_log = $query->execute()->fetch();

    }
    else {
      $submission_log = [];
    }

    return $submission_log;

  }

  /**
   * Logs the current submission page.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param string $page
   *   The page to log.
   *
   * @throws \Exception
   */
  public function logPageVisit(WebformSubmissionInterface $webform_submission, $page) {

    // Set the page to the current page if it is empty.
    if (empty($page)) {
      $page = $this->getCurrentPage($webform_submission);
    }
    $hasVisitedPage = $this->hasVisitedPage($webform_submission, $page);

    // If submission is not saved, just return with nothing.
    if (empty($webform_submission->id())) {
      // And return to stop execution.
      return;
    }

    $data = $webform_submission->getData();

    // Only log the page if they haven't already visited it.
    if (!$hasVisitedPage) {
      $userData = $this->helsinkiProfiiliUserData->getUserData();
      $fields = [
        'webform_id' => $webform_submission->getWebform()->id(),
        'sid' => $webform_submission->id(),
        'operation' => self::PAGE_VISITED_OPERATION,
        'handler_id' => self::HANDLER_ID,
        'application_number' => $data['application_number'] ?? '',
        'uid' => \Drupal::currentUser()->id(),
        'user_uuid' => $userData['sub'] ?? '',
        'data' => $page,
        'page' => $page,
        'timestamp' => (string) \Drupal::time()->getRequestTime(),
      ];

      $query = $this->database->insert(self::TABLE, $fields);
      $query->fields($fields)->execute();
    }
  }

  /**
   * Logs the current submission errors.
   *
   * And if no errors on current page, then remove item form database to mark.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form's form_state.
   *
   * @throws \Exception
   */
  public function logPageErrors(WebformSubmissionInterface $webform_submission, FormStateInterface $form_state) {
    // Get form errors for this page.
    $form_errors = $form_state->getErrors();
    $current_page = $webform_submission->getCurrentPage();
    if (empty($form_errors)) {
      $this->deleteSubmissionLogs($webform_submission, self::ERROR_OPERATION, $current_page);
    }
    else {
      $this->logErrors($webform_submission, $form_errors, $current_page);
    }
    return $form_errors;
  }

  /**
   * Logs errors.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param array $errors
   *   Array of errors to log.
   * @param string $page
   *   Page for which errors are logged.
   *
   * @throws \Exception
   */
  public function logErrors(WebformSubmissionInterface $webform_submission, array $errors, string $page) {

    $wfId = $webform_submission->id();
    // Get outta here if the submission hasn't been saved yet.
    if ($wfId == NULL) {
      return;
    }
    if (!empty($errors)) {

      if (empty($page)) {
        $page = $webform_submission->getCurrentPage();
      }

      $userData = $this->helsinkiProfiiliUserData->getUserData();
      $data = $webform_submission->getData();
      $fields = [
        'webform_id' => $webform_submission->getWebform()->id(),
        'sid' => $webform_submission->id(),
        'operation' => self::ERROR_OPERATION,
        'handler_id' => self::HANDLER_ID,
        'application_number' => $data['application_number'] ?? '',
        'uid' => \Drupal::currentUser()->id(),
        'user_uuid' => $userData['sub'] ?? '',
        'data' => serialize($errors),
        'page' => $page,
        'timestamp' => (string) \Drupal::time()->getRequestTime(),
      ];
      $this->database->insert(self::TABLE)->fields($fields)->execute();
    }
  }

  /**
   * Delete submission logs.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param string $operation
   *   Operation to be deleted. Either errors or page visits. If omitted, both
   *   will be deleted.
   * @param string $page
   *   Page we want to delete logs from.
   *
   * @return int
   *   Num of rows
   */
  public function deleteSubmissionLogs(
    WebformSubmissionInterface $webform_submission,
    string $operation = '',
    string $page = ''
  ): int {
    // Get outta here if the submission hasn't been saved yet.
    if (empty($webform_submission->id())) {
      return 0;
    }

    $data = $webform_submission->getData();

    $query = $this->database->delete(self::TABLE);
    $query->condition('webform_id', $webform_submission->getWebform()->id());
    $query->condition('application_number', $data['application_number']);

    // If given page, delete only that, otherwise delete all related to
    // this application.
    if ($operation !== '') {
      $query->condition('operation', $operation);
    }
    if ($page !== '') {
      $query->condition('page', $page);
    }
    return $query->execute();
  }

  /**
   * Gets a page an element is located at.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform entity.
   * @param string $element
   *   A webform element.
   *
   * @return mixed
   *   A page an element belongs to.
   */
  public function getElementPage(WebformInterface $webform, string $element): mixed {
    $element = $webform->getElement($element);
    return !empty($element) && array_key_exists('#webform_parents', $element) ? $element['#webform_parents'][0] : NULL;
  }

  /**
   * Validates all pages within a submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $triggeringElement
   *   Button clicked in form.
   * @param array $form
   *   Form data in array.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function validateAllPages(
    WebformSubmissionInterface $webform_submission,
    FormStateInterface $form_state,
    string $triggeringElement,
    array $form = []
  ) {
    // Get outta here if we are already validating the form.
    if ($form_state->get('validating') == TRUE) {
      return;
    }

    // Validate and log pages we have yet to visit.
    $webform = $webform_submission->getWebform();

    // Set flag to skip manual validation in validateForm.
    $webform->setState('validateAllPages', TRUE);

    // Loop pages.
    foreach ($webform->getPages() as $key => $page) {
      // Log and validate all the pages.
      if ($key != 'webform_confirmation' && empty($page['#states'])) {
        // Lets make sure we don't create a validation loop.
        $form_state->set('validating', TRUE);
        // Stash existing error messages.
        $error_messages = $this->messenger->messagesByType(MessengerInterface::TYPE_ERROR);
        $this->validateSinglePage($webform_submission, $key);
        // Delete all form related error messages so we don't repeat ourselves.
        $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
        // Restore existing error message.
        foreach ($error_messages as $error_message) {
          $this->messenger->addError($error_message);
        }
      }
    }

    $webform->setState('validateAllPages', FALSE);

    // Loop through fieldnames and validate fields.
    // foreach (AttachmentHandler::getAttachmentFieldNames() as $fieldName) {
    // AttachmentHandler::validateAttachmentField(
    // $fieldName,
    // $form_state,
    // $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$fieldName]["#title"],
    // $triggeringElement
    // );
    // }.
    // $errors = $form_state->getErrors();
    // $webform->setState('current_errors', $errors);
    // Reset the submission to it's original settings.
    $form_state->set('validating', FALSE);
    $perrors = $this->getPagedErrors($form_state, $webform_submission);
    $webform->setState('current_errors', $perrors);
  }

  /**
   * Validates a single page of a submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param string $page
   *   The machine name of the target page.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function validateSinglePage(WebformSubmissionInterface $webform_submission, string $page) {

    // Stash the current page.
    $current_page = $webform_submission->getCurrentPage();
    // Let's ensure we are on the page that needs to be validated.
    $webform_submission->setCurrentPage($page)->save();
    // Build a new form for this submission.
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $this->entityTypeManager->getFormObject('webform_submission', 'api');
    $form_object->setEntity($webform_submission);
    // Create an empty form state which will be populated when the submission
    // form is submitted.
    $new_form_state = new FormState();
    // Lets make sure we don't create a validation loop.
    $new_form_state->set('validating', TRUE);

    // Need to have #parents element, or file validation errors.
    $new_form_state->setTriggeringElement(['#parents' => ['manual_validation']]);

    // Submit the form.
    $this->formBuilder->submitForm($form_object, $new_form_state);
    $this->logPageVisit($webform_submission, $page);
    $this->logPageErrors($webform_submission, $new_form_state);
    // Return to the original page.
    $webform_submission->setCurrentPage($current_page);
  }

  /**
   * Get current page's validation errors parsed to paged error messages.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Submission object.
   *
   * @return array
   *   All errors paged.
   */
  public function getPagedErrors(FormStateInterface $form_state, WebformSubmissionInterface $webform_submission): array {
    // Get form errors for this page.
    $form_errors = $form_state->getErrors();
    $current_page = $webform_submission->getCurrentPage();

    $paged_errors = [];

    foreach ($form_errors as $element => $error) {
      $base_element = explode('][', $element)[0];
      // application_number.
      $page = $this->getElementPage($webform_submission->getWebform(), $base_element);
      // Place error on current page if the page is empty.
      if (!empty($page) && is_string($page)) {
        $paged_errors[$page][$element] = $error;
      }
      else {
        $paged_errors[$current_page][$element] = $error;
      }
    }

    return $paged_errors;
  }

}
