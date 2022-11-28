<?php

namespace Drupal\grants_handler\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\file\Entity\File;
use Drupal\grants_attachments\AttachmentRemover;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_handler\MessageService;
use Drupal\helfi_atv\AtvService;
use Drupal\webform\Entity\WebformSubmission;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Handler form.
 */
class MessageForm extends FormBase {

  /**
   * Drupal\Core\TypedData\TypedDataManager definition.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected TypedDataManager $typedDataManager;

  /**
   * Communicate messages to integration.
   *
   * @var \Drupal\grants_handler\MessageService
   */
  protected MessageService $messageService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Handle application tasks.
   *
   * @var \Drupal\grants_handler\ApplicationHandler
   */
  protected ApplicationHandler $applicationHandler;

  /**
   * Access ATV.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Remove attachment files.
   *
   * @var \Drupal\grants_attachments\AttachmentRemover
   */
  protected AttachmentRemover $attachmentRemover;

  /**
   * Print / log debug things.
   *
   * @var bool
   */
  protected bool $debug;

  /**
   * Constructs a new AddressForm object.
   */
  public function __construct(
    TypedDataManager $typed_data_manager,
    MessageService $messageService,
    EntityTypeManager $entityTypeManager,
    ApplicationHandler $applicationHandler,
    AtvService $atvService,
    AttachmentRemover $attachmentRemover
  ) {
    $this->typedDataManager = $typed_data_manager;
    $this->messageService = $messageService;
    $this->entityTypeManager = $entityTypeManager;
    $this->applicationHandler = $applicationHandler;
    $this->atvService = $atvService;
    $this->attachmentRemover = $attachmentRemover;

    $debug = getenv('debug');

    if ($debug == 'true') {
      $this->debug = TRUE;
    }
    else {
      $this->debug = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MessageForm|static {
    return new static(
      $container->get('typed_data_manager'),
      $container->get('grants_handler.message_service'),
      $container->get('entity_type.manager'),
      $container->get('grants_handler.application_handler'),
      $container->get('helfi_atv.atv_service'),
      $container->get('grants_attachments.attachment_remover'),

    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_handler_message';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, WebformSubmission $webform_submission = NULL) {

    $form_state->setStorage(['webformSubmission' => $webform_submission]);

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];

    $sessionHash = sha1(\Drupal::service('session')->getId());
    $upload_location = 'private://grants_messages/' . $sessionHash;

    $form['messageAttachment'] = [
      '#type' => 'managed_file',
      '#title' => t('Attachment'),
      '#multiple' => FALSE,
      '#uri_scheme' => 'private',
      '#file_extensions' => 'doc,docx,gif,jpg,jpeg,pdf,png,ppt,pptx,rtf,txt,xls,xlsx,zip',
      '#upload_validators' => [
        'file_validate_extensions' => ['doc docx gif jpg jpeg pdf png ppt pptx rtf txt xls xlsx zip'],
      ],
      '#upload_location' => $upload_location,
      '#sanitize' => TRUE,
      '#description' => $this->t('Add attachment to your message'),
    ];
    $form['attachmentDescription'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attachment description'),
      '#required' => FALSE,
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
   * Ajax callback. Not used currently.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    if (!isset($storage['webformSubmission'])) {
      $this->messenger()->addError($this->t('webformSubmission not found!'));
      return;
    }

    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    $submission = $storage['webformSubmission'];
    $submissionData = $submission->getData();

    $nextMessageId = Uuid::uuid4()->toString();

    $attachment = $form_state->getValue('messageAttachment');
    $data = [
      'body' => Xss::filter($form_state->getValue('message')),
      'messageId' => $nextMessageId,
    ];

    if (!empty($attachment)) {
      $file = File::load(reset($attachment));
      if ($file) {
        try {
          // Get document from atv/cache.
          $atvDocument = $this->applicationHandler->getAtvDocument($submissionData['application_number']);
          // Upload attachment to document.
          $attachmentResponse = $this->atvService->uploadAttachment($atvDocument->getId(), $file->getFilename(), $file);

          $baseUrl = $this->atvService->getBaseUrl();
          $baseUrlApps = str_replace('agw', 'apps', $baseUrl);
          // Remove server url from integrationID.
          $integrationId = str_replace($baseUrl, '', $attachmentResponse['href']);
          $integrationId = str_replace($baseUrlApps, '', $integrationId);

          $data['attachments'] = [
            (object) [
              'fileName' => $file->getFilename(),
              'description' => $form_state->getValue('attachmentDescription'),
              'integrationID' => $integrationId,
            ],
          ];

          // Remove file attachment directly after upload.
          $this->attachmentRemover->removeGrantAttachments(
            [$file->id()],
            [$file->id() => ['upload' => TRUE]],
            $submissionData['application_number'],
            $this->debug,
            $submission->id()
          );
        }
        catch (\Exception $e) {
          $this->messenger->addError('Message attachment upload failed. Error has been logged.');
          $this->logger('message_form')
            ->error('Error uploading message attachment. @error', ['@error' => $e->getMessage()]);
        }
        catch (GuzzleException $e) {
          $this->messenger->addError('Message attachment upload failed. Error has been logged.');
          $this->logger('message_form')
            ->error('Error uploading message attachment. @error', ['@error' => $e->getMessage()]);
        }
      }
    }

    if ($this->messageService->sendMessage($data, $submission, $nextMessageId)) {
      $this->messenger()
        ->addStatus($this->t('Your message has been sent. Please note that it will take some time it appears on application.'));
      $this->messenger()
        ->addStatus($this->t('Your message: @message', ['@message' => $data['body']]));
    }
    else {
      $this->messenger()
        ->addStatus($this->t('Sending of your message failed.'));
    }
  }

}
