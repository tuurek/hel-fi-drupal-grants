<?php

namespace Drupal\grants_oma_asiointi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_mandate\Controller\GrantsMandateController;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Oma Asiointi routes.
 */
class GrantsOmaAsiointiController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The request stack used to access request globals.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Access to profile data.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Logger access.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * The grants_handler.application_handler service.
   *
   * @var \Drupal\grants_handler\ApplicationHandler
   */
  protected ApplicationHandler $applicationHandler;

  /**
   * The helfi_atv.atv_service service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $helfiAtvAtvService;

  /**
   * CompanyController constructor.
   */
  public function __construct(
    RequestStack $requestStack,
    AccountProxyInterface $current_user,
    LanguageManagerInterface $language_manager,
    GrantsProfileService $grantsProfileService,
    ApplicationHandler $grants_handler_application_handler,
    AtvService $helfi_atv_atv_service,
  ) {
    $this->requestStack = $requestStack;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->grantsProfileService = $grantsProfileService;
    $this->logger = $this->getLogger('grants_oma_asiointi');
    $this->applicationHandler = $grants_handler_application_handler;
    $this->helfiAtvAtvService = $helfi_atv_atv_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GrantsMandateController|static {
    return new static(
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('grants_profile.service'),
      $container->get('grants_handler.application_handler'),
      $container->get('helfi_atv.atv_service'),
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();

    if ($selectedCompany == NULL) {
      // $current_uri = \Drupal::request()->getRequestUri();
      $url = Url::fromRoute('grants_mandate.mandateform')
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($url);
      return $response;
    }

    $appEnv = ApplicationHandler::getAppEnv();

    $applications = ApplicationHandler::getCompanyApplications(
      $selectedCompany,
      $appEnv,
      FALSE,
      TRUE,
      'application_list_item'
    );
    $drafts = $applications['DRAFT'] ?? [];
    unset($applications['DRAFT']);

    $other = [];
    $unreadMsg = [];

    foreach ($applications as $status => $values) {
      $other += $values;
      foreach ($values as $application) {
        $appMessages = ApplicationHandler::parseMessages($application['#submission']->getData());
        foreach ($appMessages as $msg) {
          if ($msg["messageStatus"] == 'UNREAD') {
            $unreadMsg[] = [
              '#theme' => 'message_list_item',
              '#message' => $msg,
            ];
          }
        }
      }
    }

    $build = [
      '#theme' => 'grants_oma_asiointi_front',
      '#drafts' => [
        '#theme' => 'application_list',
        '#type' => 'drafts',
        '#header' => t('Applications in progress'),
        '#id' => 'oma-asiointi__drafts',
        '#description' => 'DESCRIPTION GOES HERE',
        '#items' => $drafts,
      ],
      '#others' => [
        '#theme' => 'application_list',
        '#type' => 'sent',
        '#header' => t('Sent applications'),
        '#id' => 'oma-asiointi__sent',
        '#description' => 'DESCRIPTION GOES HERE',
        '#items' => $other,
      ],
      '#unread' => $unreadMsg,
    ];

    return $build;
  }

  /**
   * Get title for oma asiointi page.
   *
   * @return string
   *   Title.
   */
  public function title() :string {
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    return $selectedCompany['name'];
  }

}
