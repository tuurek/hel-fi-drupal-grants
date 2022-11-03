<?php

namespace Drupal\grants_handler\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
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
   * Check if user login is required.
   *
   * We do not want to redirect to mandate page if so.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Event from request.
   *   str_replace('/' . $lang, '', $requestUri)
   *
   * @return bool
   *   If needs redirect or not.
   */
  public function needsRedirectToLogin(GetResponseEvent $event) {
    $requestUri = $event->getRequest()->getRequestUri();
    $urlObject = Url::fromUserInput($requestUri);
    if (
      $urlObject->access(User::getAnonymousUser()) === FALSE &&
      $urlObject->access(\Drupal::currentUser()) === FALSE
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * If user needs to be redirected to mandate page.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Event from request.
   *
   * @return bool
   *   If needs redirect or not.
   */
  public function needsRedirectToMandate(GetResponseEvent $event) {

    $currentUserRoles = $this->currentUser->getRoles();

    // We need to redirect to mandate page if user is authenticated
    // & has helsinkiprofiili role.
    if ($this->currentUser->isAuthenticated() &&
      in_array('helsinkiprofiili', $currentUserRoles)) {
      $selectedCompany = $this->grantsProfileService->getSelectedCompany();
      // If no selected company.
      if ($selectedCompany == NULL) {
        $urlObject = Url::fromUserInput($event->getRequest()->getRequestUri());
        $routeName = $urlObject->getRouteName();

        $nodeType = '';
        if ($routeName == 'entity.node.canonical') {
          $node = Node::load($urlObject->getRouteParameters()['node']);
          $nodeType = $node->getType();
        }
        // & we are on form page.
        if ($nodeType == 'form_page') {
          return TRUE;
        }
        // If not on form_page, we want to allow mandate routes.
        if (str_contains($routeName, 'grants_mandate')) {
          return FALSE;
        }
        // But require mandate in all other grants routes.
        if (str_contains($routeName, 'grants_')) {
          return TRUE;
        }
      }
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

    if (!$this->needsRedirectToLogin($event) &&
      $this->needsRedirectToMandate($event)) {
      $redirectUrl = Url::fromRoute('grants_mandate.mandateform');
      $redirect = new TrustedRedirectResponse($redirectUrl->toString());
      $event->setResponse(
        $redirect
      );
      $this->messenger->addError(t('You must select company & authorise it.'));
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
