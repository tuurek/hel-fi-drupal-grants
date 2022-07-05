<?php

namespace Drupal\grants_handler\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\file\Entity\File;
use Drupal\grants_handler\MessageService;
use Drupal\webform\Entity\WebformSubmission;
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
    EntityTypeManager $entityTypeManager
  ) {
    $this->typedDataManager = $typed_data_manager;
    $this->messageService = $messageService;
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('entity_type.manager')
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

    $form['messageAttachment'] = [
      '#type' => 'managed_file',
      '#title' => t('Attachment'),
      '#multiple' => FALSE,
      '#uri_scheme' => 'private',
      '#file_extensions' => 'doc,docx,gif,jpg,jpeg,pdf,png,ppt,pptx,rtf,txt,xls,xlsx,zip',
      '#upload_validators' => [
        'file_validate_extensions' => ['doc docx gif jpg jpeg pdf png ppt pptx rtf txt xls xlsx zip'],
      ],
      '#upload_location' => 'private://grants_messages',
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
      'body' => $form_state->getValue('message'),
      'messageId' => $nextMessageId,
    ];

    $file = NULL;
    if (!empty($attachment)) {
      $file = File::load(reset($attachment));
      if ($file) {
        $data['attachments'] = [
          (object) [
            'fileName' => $file->getFilename(),
            'description' => $form_state->getValue('attachmentDescription'),
          ],
        ];
      }
    }

    if ($this->messageService->sendMessage($data, $submission, $nextMessageId)) {

      if ($file !== NULL) {
        /** @var \Drupal\grants_attachments\AttachmentUploader $attachmentUploader */
        $attachmentUploader = \Drupal::service('grants_attachments.attachment_uploader');
        $fileObjects = [$file->id() => $file->id()];
        $attachmentResult = $attachmentUploader->uploadAttachments(
          [$file->id() => $file->id()],
          $submissionData['application_number'],
          $nextMessageId
        );

        foreach ($attachmentResult as $attResult) {
          if ($attResult['upload'] === TRUE) {
            $this->messenger()
              ->addStatus(
                $this->t(
                  'Attachment (@filename) uploaded',
                  [
                    '@filename' => $attResult['filename'],
                  ]));
          }
          else {
            $this->messenger()
              ->addStatus(
                $this->t(
                  'Attachment (@filename) upload failed with message: @msg. Event has been logged.',
                  [
                    '@filename' => $attResult['filename'],
                    '@msg' => $attResult['msg'],
                  ]));
          }
        }
        /** @var \Drupal\grants_attachments\AttachmentRemover $attachmentRemover */
        $attachmentRemover = \Drupal::service('grants_attachments.attachment_remover');

        $attachmentRemover->removeGrantAttachments(
          $fileObjects,
          $attachmentResult,
          $submissionData['application_number'],
          TRUE,
          $submission->id()
        );

      }
      $this->messenger()
        ->addStatus($this->t('Your message has been sent. Please note that it may not show up on this page straight away.'));
      $this->messenger()
        ->addStatus($this->t('Your message: @message', ['@message' => $data['body']]));
    }
    else {
      $this->messenger()
        ->addStatus($this->t('Sending of your message failed.'));
    }
  }

}
