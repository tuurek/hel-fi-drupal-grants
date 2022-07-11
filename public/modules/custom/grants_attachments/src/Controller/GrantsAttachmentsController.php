<?php

namespace Drupal\grants_attachments\Controller;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for grants_attachments routes.
 */
class GrantsAttachmentsController extends ControllerBase {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The helfi_atv service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected $helfiAtv;

  /**
   * Process application data from webform to ATV.
   *
   * @var \Drupal\grants_handler\ApplicationHandler
   */
  protected ApplicationHandler $applicationHandler;

  /**
   * The controller constructor.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   * @param \Drupal\grants_handler\ApplicationHandler $applicationHandler
   *   Application handler.
   * @param \Drupal\Core\Http\RequestStack $requestStack
   *   Drupal requests.
   */
  public function __construct(
    AtvService $helfi_atv,
    ApplicationHandler $applicationHandler,
    RequestStack $requestStack
  ) {
    $this->helfiAtv = $helfi_atv;
    $this->applicationHandler = $applicationHandler;

    $this->request = $requestStack;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_atv.atv_service'),
      $container->get('grants_handler.application_handler'),
      $container->get('request_stack')
    );
  }

  /**
   * Delete attachment from given application.
   *
   * @param string $submission_id
   *   Submission.
   * @param string $integration_id
   *   Attachment integration id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteAttachment(string $submission_id, string $integration_id) {
    // Load submission & data.
    $submission = ApplicationHandler::submissionObjectFromApplicationNumber($submission_id);
    $submissionData = $submission->getData();
    // Rebuild integration id from url.
    $integrationId = str_replace('_', '/', $integration_id);

    if ($submissionData['status'] != ApplicationHandler::$applicationStatuses['DRAFT']) {
      throw new AccessException('Only application in DRAFT status allows attachments to be deleted.');
    }

    try {
      // Try to delete attachment directly.
      $attachmentDeleteResult = $this->helfiAtv->deleteAttachmentViaIntegrationId($integrationId);

      if ($attachmentDeleteResult) {
        $this->messenger()->addStatus($this->t('Document file attachment deleted.'));
      }
      else {
        $this->messenger()->addError('Attachment deletion failed.');
      }
    }
    catch (AtvDocumentNotFoundException $e) {
      $this->getLogger('grants_attachments')->error('Document attachment not found. IntegrationID' . $integrationId);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }

    // No matter what, we want to remove file from content.
    try {
      // Remove given attachment from application.
      $updatedAttachments = [];
      foreach ($submissionData['attachments'] as $key => $attachment) {
        if ($attachment["integrationID"] == $integrationId) {
          unset($submissionData['attachments'][$key]);
        }
        else {
          $updatedAttachments[] = $attachment;
        }
      }
      $submissionData['attachments'] = $updatedAttachments;

      // Build data -> should validate ok, since we're only deleting attachments.
      $applicationData = $this->applicationHandler->webformToTypedData(
        $submissionData,
        '\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition',
        'grants_metadata_yleisavustushakemus'
      );

      // Update in ATV.
      $applicationUploadStatus = $this->applicationHandler->handleApplicationUpload(
        $applicationData,
        $submission_id
      );

      if ($applicationUploadStatus) {
        $this->messenger()->addStatus($this->t('Appilcation updated.'));
      }

    }
    catch (\Exception $e) {
      $this->getLogger('grants_attachments')->error($e->getMessage());
    }

    $destination = $this->request->getMainRequest()->get('destination');
    return new RedirectResponse($destination);
  }

}
