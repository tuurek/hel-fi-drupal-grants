<?php

namespace Drupal\grants_handler\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\grants_handler\ApplicationHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Handler form.
 */
class CopyApplicationForm extends FormBase {

  /**
   * Is debug on or off?
   *
   * @var bool
   */
  protected bool $debug;

  /**
   * Application handler class.
   *
   * @var \Drupal\grants_handler\ApplicationHandler
   */
  protected ApplicationHandler $applicationHandler;

  /**
   * Constructs a new AddressForm object.
   */
  public function __construct(
    ApplicationHandler $applicationHandler
  ) {
    $debug = getenv('debug');

    if ($debug == 'true') {
      $this->debug = TRUE;
    }
    else {
      $this->debug = FALSE;
    }

    $this->applicationHandler = $applicationHandler;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MessageForm|static {
    return new static(
      $container->get('grants_handler.application_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_handler_copy_application';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $submission_id = '') {

    $view_mode = 'application_copy';
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    try {
      $webform_submission = ApplicationHandler::submissionObjectFromApplicationNumber($submission_id);

      if ($webform_submission != NULL) {
        $webform = $webform_submission->getWebform();
        $submissionData = $webform_submission->getData();
        // Set webform submission template.
        $build = [
          '#theme' => 'grants_handler_copy_application',
          '#view_mode' => $view_mode,
          '#submission' => $webform_submission,
        ];

        $form_state->setStorage(['submission' => $webform_submission]);

      }

    }
    catch (\Exception $e) {
    }
    $form['copyFrom'] = [
      '#type' => 'markup',
      '#markup' => 'Tähän vois sitte laittaa hakemuksen perussettejä, tai vaikka koko hakemus näytille.',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Copy application'),
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

    $storage = $form_state->getStorage();
    /** @var \Drupal\webform\Entity\WebformSubmission $webform_submission */
    $webform_submission = $storage['submission'];
    $webform = $webform_submission->getWebForm();

    // Init new application with copied data.
    $newSubmission = $this->applicationHandler->initApplication($webform->id(), $webform_submission->getData());

    $newData = $newSubmission->getData();

    if ($newSubmission) {
      $this->messenger()
        ->addStatus(
          $this->t(
            'Grant application copied(<span id="saved-application-number">@number</span>)',
            [
              '@number' => $newData['application_number'],
            ]
          )
        );

      $form_state->setRedirect(
        'grants_handler.completion',
        ['submission_id' => $newData['application_number']],
        [
          'attributes' => [
            'data-drupal-selector' => 'application-saved-successfully-link',
          ],
        ]
      );
    }
    else {
      $this->messenger()->addError('Grant application copy failed.');
    }
  }

}
