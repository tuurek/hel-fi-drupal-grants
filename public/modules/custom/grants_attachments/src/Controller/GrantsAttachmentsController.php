<?php

namespace Drupal\grants_attachments\Controller;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_handler\EventsService;
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
   * Create events.
   *
   * @var \Drupal\grants_handler\EventsService
   */
  protected EventsService $eventsService;

  /**
   * The controller constructor.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   * @param \Drupal\grants_handler\ApplicationHandler $applicationHandler
   *   Application handler.
   * @param \Drupal\Core\Http\RequestStack $requestStack
   *   Drupal requests.
   * @param \Drupal\grants_handler\EventsService $eventsService
   *   Use submission events productively.
   */
  public function __construct(
    AtvService $helfi_atv,
    ApplicationHandler $applicationHandler,
    RequestStack $requestStack,
    EventsService $eventsService
  ) {
    $this->helfiAtv = $helfi_atv;
    $this->applicationHandler = $applicationHandler;

    $this->request = $requestStack;
    $this->eventsService = $eventsService;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_atv.atv_service'),
      $container->get('grants_handler.application_handler'),
      $container->get('request_stack'),
      $container->get('grants_handler.events_service'),
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
        if (
          (isset($attachment["integrationID"]) &&
            $attachment["integrationID"] != NULL) &&
          $attachment["integrationID"] == $integrationId) {
          unset($submissionData['attachments'][$key]);
        }
      }

      // Build data -> should validate ok, since we're
      // only deleting attachments.
      $applicationData = $this->applicationHandler->webformToTypedData(
        $submissionData);

      // Update in ATV.
      $applicationUploadStatus = $this->applicationHandler->handleApplicationUpload(
        $applicationData,
        $submission_id
      );

      if ($applicationUploadStatus) {
        $this->messenger()->addStatus($this->t('Application updated.'));

        $eventId = $this->eventsService->logEvent(
          $submission_id,
          'APP_INFO_ATT_DELETED',
          t('Attachment deleted.'),
          $integrationId
        );
      }
    }
    catch (\Exception $e) {
      $this->getLogger('grants_attachments')->error($e->getMessage());
    }

    $destination = $this->request->getMainRequest()->get('destination');
    return new RedirectResponse($destination);
  }

}
