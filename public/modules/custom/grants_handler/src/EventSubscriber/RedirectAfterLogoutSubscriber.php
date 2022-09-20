<?php

namespace Drupal\grants_handler\EventSubscriber;

use Drupal\grants_handler\Event\UserLogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * RedirectAfterLogoutSubscriber event subscriber.
 *
 * @package Drupal\redirect_after_logout\EventSubscriber
 */
class RedirectAfterLogoutSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct() {}

  /**
   * Redirect user to front page after logout.
   *
   * @param \Drupal\grants_handler\Event\UserLogoutEvent $event
   *   Event.
   */
  public function checkRedirection(UserLogoutEvent $event) {
    $response = new RedirectResponse('/');
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[UserLogoutEvent::EVENT_NAME][] = ['checkRedirection'];
    return $events;
  }

}
