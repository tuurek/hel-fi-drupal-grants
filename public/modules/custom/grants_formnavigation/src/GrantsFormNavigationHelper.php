<?php

namespace Drupal\grants_formnavigation;

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
class GrantsFormNavigationHelper {

  /**
   * Name of the table where log entries are stored.
   */
  const TABLE = 'grants_formnavigation_log';

  /**
   * Name of the error operation.
   */
  const ERROR_OPERATION = 'errors';

  /**
   * Name of the page visited operation.
   */
  const PAGE_VISITED_OPERATION = 'page visited';

  /**
   * Name of the navigation handler.
   */
  const HANDLER_ID = 'webform_navigation';

  /**
   * The temp_store key.
   */
  const TEMP_STORE_KEY = 'grants_formnavigation_errors';

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
   * AutosaveHelper constructor. Small change.
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
  public function getCurrentPage(WebformSubmissionInterface $webform_submission): string {
    $pages = $webform_submission->getWebform()
      ->getPages('edit', $webform_submission);
    return empty($webform_submission->getCurrentPage()) ? array_keys($pages)[0] : $webform_submission->getCurrentPage();
  }

  /**
   * Has visited page.
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

    $userData = $this->helsinkiProfiiliUserData->getUserData();

    $query = $this->database->select(self::TABLE, 'l');
    $query->condition('webform_id', $webform_submission->getWebform()->id());
    $query->condition('sid', $webform_submission->id());
    $query->condition('user_uuid', $userData['sub']);
    $query->condition('operation', self::PAGE_VISITED_OPERATION);
    $query->condition('data', $page);
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
  public function getErrors(WebformSubmissionInterface $webform_submission, $page = NULL) {
    // Get outta here if the submission hasn't been saved yet.
    if (empty($webform_submission->id())) {
      return [];
    }
    $userData = $this->helsinkiProfiiliUserData->getUserData();

    $query = $this->database->select(self::TABLE, 'l');
    $query->condition('webform_id', $webform_submission->getWebform()->id());
    $query->condition('user_uuid', $userData['sub']);
    $query->condition('sid', $webform_submission->id());
    $query->condition('operation', self::ERROR_OPERATION);
    $query->fields('l', [
      'lid',
      'sid',
      'data',
    ]);
    $query->orderBy('l.lid', 'DESC');
    $query->range(0, 1);
    $submission_log = $query->execute()->fetch();
    $data = !empty($submission_log->data) ? unserialize($submission_log->data) : [];
    return (!empty($page) && !empty($data[$page])) ? $data[$page] : $data;
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
    // Get outta here if the submission hasn't been saved yet.
    if (empty($webform_submission->id())) {
      return;
    }
    // Set the page to the current page if it is empty.
    if (empty($page)) {
      $page = $this->getCurrentPage($webform_submission);
    }
    // Only log the page if they haven't already visited it.
    if (!$this->hasVisitedPage($webform_submission, $page)) {
      $userData = $this->helsinkiProfiiliUserData->getUserData();
      $fields = [
        'webform_id' => $webform_submission->getWebform()->id(),
        'sid' => $webform_submission->id(),
        'operation' => self::PAGE_VISITED_OPERATION,
        'handler_id' => self::HANDLER_ID,
        'uid' => \Drupal::currentUser()->id(),
        'user_uuid' => $userData['sub'] ?? '',
        'data' => $page,
        'timestamp' => (string) \Drupal::time()->getRequestTime(),
      ];

      $query = $this->database->insert(self::TABLE, $fields);
      $query->fields($fields)->execute();
    }
  }

  /**
   * Logs the stashed submission errors.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   *
   * @throws \Exception
   */
  public function logStashedPageErrors(WebformSubmissionInterface $webform_submission) {

    $errors = $this->store->get(self::TEMP_STORE_KEY);
    // Get outta here if there are not any stashed errors.
    if (empty($errors)) {
      return;
    }
    $prev_errors = $this->getErrors($webform_submission);
    $new_errors = array_merge($prev_errors, $errors);
    // Log the stashed errors.
    $this->logErrors($webform_submission, $new_errors);
    // Clear the stashed errors now that they are logged.
    $this->store->delete(self::TEMP_STORE_KEY);
  }

  /**
   * Logs the current submission errors.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form's form_state.
   *
   * @throws \Exception
   */
  public function logPageErrors(WebformSubmissionInterface $webform_submission, FormStateInterface $form_state) {
    $paged_errors = $this->getPagedErrors($form_state, $webform_submission);
    // Stash the errors and return if the submission hasn't been created yet.
    if (empty($webform_submission->id())) {
      $this->store->set(self::TEMP_STORE_KEY, $paged_errors);
      return;
    }
    $this->logErrors($webform_submission, $paged_errors);
  }

  /**
   * Logs errors.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission entity.
   * @param array $errors
   *   Array of errors to log.
   *
   * @throws \Exception
   */
  public function logErrors(WebformSubmissionInterface $webform_submission, array $errors) {
    // Get outta here if the submission hasn't been saved yet.
    if (empty($webform_submission->id())) {
      return;
    }
    if (!empty($errors)) {
      $userData = $this->helsinkiProfiiliUserData->getUserData();
      $fields = [
        'webform_id' => $webform_submission->getWebform()->id(),
        'sid' => $webform_submission->id(),
        'operation' => self::ERROR_OPERATION,
        'handler_id' => self::HANDLER_ID,
        'uid' => \Drupal::currentUser()->id(),
        'user_uuid' => $userData['sub'] ?? '',
        'data' => serialize($errors),
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
   */
  public function deleteSubmissionLogs(WebformSubmissionInterface $webform_submission) {
    // Get outta here if the submission hasn't been saved yet.
    if (empty($webform_submission->id())) {
      return;
    }
    $query = $this->database->delete(self::TABLE);
    $query->condition('webform_id', $webform_submission->getWebform()->id());
    $query->condition('sid', $webform_submission->id());
    $query->execute();
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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function validateAllPages(WebformSubmissionInterface $webform_submission, FormStateInterface $form_state) {
    // Get outta here if we are already validating the form.
    if ($form_state->get('validating') == TRUE) {
      return;
    }
    // Validate and log pages we have yet to visit.
    $webform = $webform_submission->getWebform();
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
    // Reset the submission to it's original settings.
    $form_state->set('validating', FALSE);
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
    // Submit the form.
    $this->formBuilder->submitForm($form_object, $new_form_state);
    $this->logPageVisit($webform_submission, $page);
    $this->logPageErrors($webform_submission, $new_form_state);
    // Return to the original page.
    $webform_submission->setCurrentPage($current_page);
  }

  /**
   * Get all errors for webform submission.
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
    $form_errors = $form_state->getErrors();
    $current_errors = $this->getErrors($webform_submission);
    $paged_errors = empty($current_errors) ? [] : $current_errors;
    $current_page = $webform_submission->getCurrentPage();
    // Reset the current page's errors with those set in the form state.
    $paged_errors[$current_page] = [];
    foreach ($form_errors as $element => $error) {
      $base_element = explode('][', $element)[0];
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
