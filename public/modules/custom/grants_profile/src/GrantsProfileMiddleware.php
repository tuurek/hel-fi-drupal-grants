<?php

namespace Drupal\grants_profile;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * GrantsProfileMiddleware middleware.
 */
class GrantsProfileMiddleware implements HttpKernelInterface {

  use StringTranslationTrait;

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs the GrantsProfileMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    AccountProxyInterface $currentUser
  )
{

    $this->httpKernel = $http_kernel;
  $this->currentUser = $currentUser;

  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {

    if ($request->getClientIp() == '127.0.0.10') {
      return new Response($this->t('Bye!'), 403);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
