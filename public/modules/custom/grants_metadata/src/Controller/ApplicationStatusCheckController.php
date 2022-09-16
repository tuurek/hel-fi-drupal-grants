<?php

namespace Drupal\grants_metadata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for Grant Applications: Form Metadata routes.
 */
class ApplicationStatusCheckController extends ControllerBase {

  /**
   * The helfi_atv service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $helfiAtv;

  /**
   * Helsinkiprofiili service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  /**
   * The controller constructor.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *   The helfi_atv service.
   */
  public function __construct(
    AtvService $helfi_atv,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData
    ) {
    $this->helfiAtv = $helfi_atv;
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_atv.atv_service'),
      $container->get('helfi_helsinki_profiili.userdata')
    );
  }

  /**
   * Builds the response.
   */
  public function build($submission_id = '') {
    return new JsonResponse(['data' => $this->getData($submission_id), 'method' => 'GET', 'status' => 200]);
  }

  /**
   * @return array
   */
  public function getData($submission_id) {

    $userData = $this->helsinkiProfiiliUserData->getUserData();

    if (empty($userData) || !isset($userData['sub'])) {
      return [];
    }

    $userDocuments = $this->helfiAtv->getUserDocuments($userData['sub']);

    /** AtvDocument */
    $selectedDocument = reset($userDocuments);

    return $selectedDocument->getStatusArray() ?? [];
  }

}
