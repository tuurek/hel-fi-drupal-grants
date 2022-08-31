<?php

namespace Drupal\grants_webform_print\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Webform Printify routes.
 */
class GrantsWebformSubmissionPrintController extends ControllerBase {

  /**
   * The helfi_helsinkiprofiili service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $helfiHelsinkiprofiili;

  /**
   * The helfi_atv service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $helfiAtv;

  /**
   * The controller constructor.
   *
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helfi_helsinkiprofiili
   *   The helfi_helsinkiprofiili service.
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   */
  public function __construct(HelsinkiProfiiliUserData $helfi_helsinkiprofiili, AtvService $helfi_atv) {
    $this->helfiHelsinkiprofiili = $helfi_helsinkiprofiili;
    $this->helfiAtv = $helfi_atv;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_helsinki_profiili.userdata'),
      $container->get('helfi_atv.atv_service')
    );
  }

  /**
   * Builds the response.
   */
  public function build($submission_id) {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Here we print submission data when coming NOT from form.'),
    ];

    return $build;
  }

}
