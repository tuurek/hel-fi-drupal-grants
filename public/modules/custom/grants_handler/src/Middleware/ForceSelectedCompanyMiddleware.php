<?php

namespace Drupal\grants_handler\Middleware;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * This class will catch paths, and if user has not selected active company,
 * ie have not gone throug authorization, we redirect them to do so.
 */
class ForceSelectedCompanyMiddleware implements HttpKernelInterface {

  use StringTranslationTrait;

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs the ForceSelectedCompanyMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    LoggerChannelFactoryInterface $logger_factory,
    ) {
    $this->httpKernel = $http_kernel;
    $this->logger = $logger_factory->get('selected_company');

  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {

    // $profileService = \Drupal::service('openid_connect.session');
    // $profileService = \Drupal::service('grants_handler.events_service');

    // $currentPath = $request->getPathInfo();

    if ($request->getClientIp() == '127.0.0.10') {
      return new Response($this->t('Bye!'), 403);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
