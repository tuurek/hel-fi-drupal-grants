<?php

namespace Drupal\grants_handler\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;

/**
 * Event that is fired when a user logs in.
 */
class UserLogoutEvent extends Event {

  const EVENT_NAME = 'grants_handler_user_logout';

  /**
   * The user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account of the user logged in.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }

}
