<?php

namespace Drupal\grants_formnavigation\Plugin\WebformHandler;

use Drupal\grants_formnavigation\GrantsFormNavigationHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform navigation handler.
 *
 * @WebformHandler(
 *   id = "grants_formnavigation",
 *   label = @Translation("Grants Webform Navigation"),
 *   category = @Translation("Helfi"),
 *   description = @Translation("A webform submission handler for the webform
 *   navigation module."), cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class GrantsFormNavigationHandler extends WebformHandlerBase {

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The Webform Navigation Helper.
   *
   * @var \Drupal\grants_formnavigation\GrantsFormNavigationHelper
   */
  protected GrantsFormNavigationHelper $grantsFormNavigationHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->grantsFormNavigationHelper = $container->get('grants_formnavigation.helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
  }

  /**
   * {@inheritdoc}
   */
  public function alterElements(array &$elements, WebformInterface $webform) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function overrideSettings(array &$settings, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
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
      $form['actions']['submit']['#validate'][] = 'grants_formnavigation_submission_validation';
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
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
    $this->grantsFormNavigationHelper->logPageErrors($webform_submission, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postLoad(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
    // Clear the submission's logs when a submission is deleted.
    $this->grantsFormNavigationHelper->deleteSubmissionLogs($webform_submission);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->debug(__FUNCTION__, $update ? 'update' : 'insert');
    $webform = $webform_submission->getWebform();
    // Get navigation webform settings.
    $forward_navigation = $webform->getThirdPartySetting('grants_formnavigation', 'forward_navigation');
    // Log the initial page if this is an insert.
    if (!$update && $forward_navigation && $webform->hasWizardPages()) {
      $pages = $webform->getPages('add', $webform_submission);
      // Log the first page.
      $this->grantsFormNavigationHelper->logPageVisit($webform_submission, array_keys($pages)[0]);
      // Log any stashed errors.
      $this->grantsFormNavigationHelper->logStashedPageErrors($webform_submission);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function createHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function createElement($key, array $element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateElement($key, array $element, array $original_element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteElement($key, array $element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * Display the invoked plugin method to end user.
   *
   * @param string $method_name
   *   The invoked method name.
   * @param string $context1
   *   Additional parameter passed to the invoked method name.
   */
  protected function debug($method_name, $context1 = NULL) {
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

}
