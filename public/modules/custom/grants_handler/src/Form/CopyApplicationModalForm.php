<?php

namespace Drupal\grants_handler\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\grants_handler\ApplicationHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class CopyApplicationModalForm extends FormBase {

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
   * Renderer for submission details.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Constructs a new ModalAddressForm object.
   */
  public function __construct(ApplicationHandler $applicationHandler, Renderer $renderer) {
    $debug = getenv('DEBUG');

    if ($debug == 'true') {
      $this->debug = TRUE;
    }
    else {
      $this->debug = FALSE;
    }

    $this->applicationHandler = $applicationHandler;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CopyApplicationModalForm|static {

    // Create a new form object and inject its services.
    $form = new static(
      $container->get('grants_handler.application_handler'),
      $container->get('renderer'),
    );
    $form->setRequestStack($container->get('request_stack'));
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grants_profile_copy_application_modal_form';
  }

  /**
   * Helper method so we can have consistent dialog options.
   *
   * @return string[]
   *   An array of jQuery UI elements to pass on to our dialog form.
   */
  public static function getDataDialogOptions(): array {
    return [
      'width' => '25%',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $submission_id = '', string $nojs = ''): array {

    // Add the core AJAX library.
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#theme'] = 'application_copy_modal_form';

    try {
      $webform_submission = ApplicationHandler::submissionObjectFromApplicationNumber($submission_id);

      if ($webform_submission != NULL) {
        // Set webform submission template.
        $build = [
          '#theme' => 'submission_for_modal_form',
          '#submission' => $webform_submission,
          '#submission_id' => $submission_id,
        ];

        $form_state->setStorage(['submission' => $webform_submission]);
        $form['modal_markup'] = [
          '#markup' => $this->renderer->render($build),
        ];
      }

    }
    catch (\Exception $e) {
    }

    // Add a link to show this form in a modal dialog if we're not already in
    // one.
    if ($nojs == 'nojs') {
      $form['use_ajax_container'] = [
        '#type' => 'details',
        '#open' => TRUE,
      ];
      $form['use_ajax_container']['use_ajax'] = [
        '#type' => 'link',
        '#title' => $this->t('See this form as a modal.'),
        '#url' => Url::fromRoute('grants_handler.copy_application_modal', ['nojs' => 'ajax']),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(static::getDataDialogOptions()),
          // Add this id so that we can test this form.
          'id' => 'copy-application-modal-form-link',
        ],
      ];
    }

    // This element is responsible for displaying form errors in the AJAX
    // dialog.
    if ($nojs == 'ajax') {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -999,
      ];
    }

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Use application as base'),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
    ];

    // Set the form to not use AJAX if we're on a nojs path. When this form is
    // within the modal dialog, Drupal will make sure we're using an AJAX path
    // instead of a nojs one.
    if ($nojs == 'nojs') {
      unset($form['actions']['submit']['#ajax']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    /** @var \Drupal\webform\Entity\WebformSubmission $webform_submission */
    $webform_submission = $storage['submission'];
    $webform = $webform_submission->getWebForm();
    $thirdPartySettings = $webform->getThirdPartySettings('grants_metadata');

    // If copying is disabled in 3rd party settings, do not allow forward.
    if ($thirdPartySettings["disableCopying"] == 1) {
      $form_state->setErrorByName('modal_markup', 'Copying is disabled for this form.');
    }
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
    try {
      $newSubmission = $this->applicationHandler->initApplication($webform->id(), $webform_submission->getData());
      $newData = $newSubmission->getData();
    }
    catch (\Exception $e) {
      $newSubmission = FALSE;
      $newData = [];
    }

    if ($newSubmission) {
      $this->messenger()
        ->addStatus(
          $this->t(
            'Grant application copied, new id: <span id="saved-application-number">@number</span>',
            [
              '@number' => $newData['application_number'],
            ]
          )
        );

      $storage['newSubmission'] = $newSubmission;
      $form_state->setStorage($storage);

      $form_state->setRedirect(
        'grants_handler.edit_application',
        [
          'webform_submission' => $newSubmission->id(),
          'webform' => $webform->id(),
        ]
      );
    }
    else {
      $this->messenger()->addError('Grant application copy failed');
    }
  }

  /**
   * Implements the submit handler for the modal dialog AJAX call.
   *
   * @param array $form
   *   Render array representing from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Array of AJAX commands to execute on submit of the modal form.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    // We begin building a new ajax reponse.
    $response = new AjaxResponse();

    // If the user submitted the form and there are errors, show them the
    // input dialog again with error messages. Since the title element is
    // required, the empty string wont't validate and there will be an error.
    if ($form_state->getErrors()) {
      // If there are errors, we can show the form again with the errors in
      // the status_messages section.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new OpenModalDialogCommand($this->t('Errors'), $form, static::getDataDialogOptions()));
    }
    else {
      // No errors, we load things from form state.
      $storage = $form_state->getStorage();
      /** @var \Drupal\webform\Entity\WebformSubmission $webform_submission */
      $webform_submission = $storage['newSubmission'];
      $webform = $webform_submission->getWebForm();

      // Create url redirect for this new submission.
      $url = Url::fromRoute('grants_handler.edit_application',
        [
          'webform_submission' => $webform_submission->id(),
          'webform' => $webform->id(),
        ]);
      $response->addCommand(new CloseModalDialogCommand());
      $command = new RedirectCommand($url->toString());
      $response->addCommand($command);
    }

    // Finally return our response.
    return $response;
  }

}
