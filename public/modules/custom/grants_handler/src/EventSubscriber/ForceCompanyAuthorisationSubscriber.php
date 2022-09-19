<?php

namespace Drupal\grants_handler\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Grants Handler event subscriber.
 */
class ForceCompanyAuthorisationSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Grants profile access.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\grants_profile\GrantsProfileService $grantsProfileService
   *   The profile service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The profile service.
   */
  public function __construct(
    MessengerInterface $messenger,
    GrantsProfileService $grantsProfileService,
    AccountProxyInterface $currentUser
    ) {
    $this->messenger = $messenger;
    $this->grantsProfileService = $grantsProfileService;
    $this->currentUser = $currentUser;
  }

  /**
   * Check if user needs to be redirected to login page.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Event from request.
   *
   * @return bool
   *   If needs redirect or not.
   */
  public function needsRedirectToLogin(GetResponseEvent $event) {
    $uri = $event->getRequest()->getRequestUri();
    $currentUserRoles = $this->currentUser->getRoles();
    if (
      !in_array('helsinkiprofiili', $currentUserRoles) &&
      !str_contains($uri, '/asiointirooli-valtuutus') &&
      !str_contains($uri, '/user/login') &&
      !str_contains($uri, '/user/reset') &&
      !str_contains($uri, '/openid-connect/tunnistamo')
      ) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * If user needs to be redirected to mandate page.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Event from request.
   *
   * @return bool
   *   If needs redirect or not.
   */
  public function needsRedirectToMandate(GetResponseEvent $event) {
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $uri = $event->getRequest()->getRequestUri();
    $currentUserRoles = $this->currentUser->getRoles();
    if (
      in_array('helsinkiprofiili', $currentUserRoles) &&
      $selectedCompany == NULL &&
      !str_contains($uri, '/asiointirooli-valtuutus') &&
      !str_contains($uri, '/user/login') &&
      !str_contains($uri, '/user/reset') &&
      !str_contains($uri, '/openid-connect/tunnistamo')
      ) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   */
  public function onKernelRequest(GetResponseEvent $event) {

    // admin, no checks.
    if ($this->currentUser->id() == 1) {
      return;
    }

    // Anonymous or missing user role.
    if ($this->needsRedirectToLogin($event)) {
      $redirect = new TrustedRedirectResponse('/user/login');
      $event->setResponse(
        $redirect
      );
      $this->messenger->addError('You must login first.');
    }
    elseif ($this->needsRedirectToMandate($event)) {
      $redirectUrl = Url::fromRoute('grants_mandate.mandateform');
      $redirect = new TrustedRedirectResponse($redirectUrl->toString());
      $event->setResponse(
        $redirect
      );
      $this->messenger->addError('You must select company & authorise it.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
    ];
  }

}
