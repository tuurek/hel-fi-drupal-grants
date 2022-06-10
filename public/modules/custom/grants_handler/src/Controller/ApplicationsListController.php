<?php

namespace Drupal\grants_handler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Grants Handler routes.
 */
class ApplicationsListController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The helfi_helsinki_profiili.userdata service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helfiHelsinkiProfiiliUserdata;

  /**
   * The grants_profile.service service.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * The grants_handler.application_handler service.
   *
   * @var \Drupal\grants_handler\ApplicationHandler
   */
  protected ApplicationHandler $grantsHandlerApplicationHandler;

  /**
   * The helfi_atv.atv_service service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $helfiAtvAtvService;

  /**
   * The helfi_atv.atv_service service.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata
   *   The helfi_helsinki_profiili.userdata service.
   * @param \Drupal\grants_profile\GrantsProfileService $grants_profile_service
   *   The grants_profile.service service.
   * @param \Drupal\grants_handler\ApplicationHandler $grants_handler_application_handler
   *   The grants_handler.application_handler service.
   * @param \Drupal\helfi_atv\AtvService $helfi_atv_atv_service
   *   The helfi_atv.atv_service service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\grants_metadata\AtvSchema $atvShema
   *   Parse document data.
   */
  public function __construct(
    AccountInterface $current_user,
    HelsinkiProfiiliUserData $helfi_helsinki_profiili_userdata,
    GrantsProfileService $grants_profile_service,
    ApplicationHandler $grants_handler_application_handler,
    AtvService $helfi_atv_atv_service,
    LoggerChannelFactoryInterface $logger,
    MessengerInterface $messenger,
    AtvSchema $atvShema
  ) {

    $this->currentUser = $current_user;
    $this->helfiHelsinkiProfiiliUserdata = $helfi_helsinki_profiili_userdata;
    $this->grantsProfileService = $grants_profile_service;
    $this->grantsHandlerApplicationHandler = $grants_handler_application_handler;
    $this->helfiAtvAtvService = $helfi_atv_atv_service;
    $this->logger = $logger->get('applications_list');
    $this->messenger = $messenger;
    $this->atvSchema = $atvShema;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('helfi_helsinki_profiili.userdata'),
      $container->get('grants_profile.service'),
      $container->get('grants_handler.application_handler'),
      $container->get('helfi_atv.atv_service'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('grants_metadata.atv_schema')
    );
  }

  /**
   * Builds the response.
   */
  public function build(): array {

    $selectedCompany = $this->grantsProfileService->getSelectedCompany();

    try {
      $applicationDocuments = $this->helfiAtvAtvService->searchDocuments([
        'service' => 'AvustushakemusIntegraatio',
        'business_id' => $selectedCompany,
      ],
        TRUE);

      $dataDefinition = YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus');

      $appEnv = strtoupper(getenv('APP_ENV'));
      $rows = [];
      foreach ($applicationDocuments as $key => $document) {
        if (str_contains($document->getTransactionId(), $appEnv)) {
          $typedData = $this->atvSchema->documentContentToTypedData($document->getContent(), $dataDefinition);
          $rows[] = [
            $typedData['application_number'],
            $typedData['status'],
            'data',
          ];
        }
      }

      $datatable = [
        '#theme' => 'datatable',
        '#header' => [
          'Application Number',
          'Status',
          'Otsikko 3',
        ],
        '#rows' => $rows,
      ];
    }
    catch (
      TempStoreException |
      AtvDocumentNotFoundException |
      AtvFailedToConnectException |
      GuzzleException $e) {
      throw new NotFoundHttpException($this->t('No documents found'));
    }

    $build = [
      '#theme' => 'application_list',
      '#datatable' => $datatable,
    ];

    return $build;
  }

}
