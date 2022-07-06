<?php

namespace Drupal\grants_handler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_handler\EventException;
use Drupal\grants_handler\EventsService;
use Drupal\grants_handler\MessageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Grants Handler routes.
 */
class MessageController extends ControllerBase {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The grants_handler.events_service service.
   *
   * @var \Drupal\grants_handler\EventsService
   */
  protected EventsService $eventsService;

  /**
   * The grants_handler.message_service service.
   *
   * @var \Drupal\grants_handler\MessageService
   */
  protected MessageService $messageService;

  /**
   * The request service.
   *
   * @var \Drupal\Core\Http\RequestStack
   */
  protected RequestStack $request;

  /**
   * The controller constructor.
   *
   * @param \Drupal\grants_handler\EventsService $grants_handler_events_service
   *   The grants_handler.events_service service.
   * @param \Drupal\grants_handler\MessageService $grants_handler_message_service
   *   The grants_handler.message_service service.
   * @param \Drupal\Core\Http\RequestStack $requestStack
   *   Request stuff.
   */
  public function __construct(
    EventsService $grants_handler_events_service,
    MessageService $grants_handler_message_service,
    RequestStack $requestStack
  ) {
    $this->eventsService = $grants_handler_events_service;
    $this->messageService = $grants_handler_message_service;
    $this->request = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('grants_handler.events_service'),
      $container->get('grants_handler.message_service'),
      $container->get('request_stack')
    );
  }

  /**
   * Builds the response.
   */
  public function markMessageRead(string $application_number, string $message_id) {

    $destination = $this->request->getMainRequest()->get('destination');

    $submission = ApplicationHandler::submissionObjectFromApplicationNumber($application_number, NULL, FALSE);
    $submissionData = $submission->getData();
    $thisEvent = array_filter($submissionData['events'], function ($event) use ($message_id) {
      if ($event['eventTarget'] == $message_id && $event['eventType'] == EventsService::$eventTypes['MESSAGE_READ']) {
        return TRUE;
      }
      return FALSE;
    });

    if (empty($thisEvent)) {
      try {
        $this->eventsService->logEvent(
          $application_number,
          EventsService::$eventTypes['MESSAGE_READ'],
          $this->t('Message marked as read'),
          $message_id
        );
        $this->messenger()->addStatus($this->t('Message marked as read'));
      }
      catch (EventException $ee) {
        $this->getLogger('message_controller')->error($ee->getMessage());
        $this->messenger()->addError($this->t('Message marking as read failed.'));
      }
    }
    else {
      $this->messenger()->addStatus($this->t('Message already read.'));
    }

    return new RedirectResponse($destination);

  }

}
