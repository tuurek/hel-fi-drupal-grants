<?php

namespace Drupal\grants_profile;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Login And Logout Redirect Per Role helper service.
 */
class UserRedirect implements UserRedirectInterface {
  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a new Login And Logout Redirect Per Role service object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   */
  public function __construct(RequestStack $request_stack,
                              AccountProxyInterface $current_user) {
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function setLoginRedirection(string $url, AccountInterface $account = NULL) {
    $this->prepareDestination($url, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function setLogoutRedirection(string $url, AccountInterface $account = NULL) {
    $this->prepareDestination($url, $account);
  }

  /**
   * Set "destination" parameter to do redirect.
   *
   * @param string $redirect_url
   *   Configuration key (login or logout).
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   User account to set destination for.
   */
  protected function prepareDestination(string $redirect_url, AccountInterface $account = NULL) {

    $loggedin_user_roles = array_reverse($this->currentUser->getRoles());

    if (in_array('helsinkiprofiili', $loggedin_user_roles)) {

    }
    if ($redirect_url) {
      if (UrlHelper::isExternal($redirect_url)) {
        $response = new TrustedRedirectResponse($redirect_url);
        $response->send();
      }
      else {
        $url = Url::fromUserInput($redirect_url);
        if ($url instanceof Url) {
          $this->currentRequest->query->set('destination', $url->toString());
        }
      }
    }
  }

  /**
   * Return requested configuration items (login or logout) ordered by weight.
   *
   * @param string $key
   *   Configuration key (login or logout).
   *
   * @return array
   *   Requested configuration items (login or logout) ordered by weight.
   */
  protected function getConfig($key) {
    $config = $this->config->get($key);
    if ($config) {
      uasort($config, [SortArray::class, 'sortByWeightElement']);
      return $config;
    }

    return [];
  }

}
