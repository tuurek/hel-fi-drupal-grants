<?php

namespace Drupal\grants_oma_asiointi\Plugin\Block;

use Drupal\Core\Url;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "grants_oma_asiointi_block",
 *   admin_label = @Translation("Grants Oma Asiointi"),
 *   category = @Translation("Oma Asiointi")
 * )
 */
class OmaAsiointiBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  protected ApplicationHandler $applicationHandler;

  /**
   * The helfi_atv.atv_service service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $helfiAtvAtvService;

  /**
   * Construct block object.
   *
   * @param array $configuration
   *   Block config.
   * @param string $plugin_id
   *   Plugin.
   * @param mixed $plugin_definition
   *   Plugin def.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *   Helsinki profile user data.
   * @param \Drupal\grants_profile\GrantsProfileService $grants_profile_service
   *   The grants_profile.service service.
   * @param \Drupal\grants_handler\ApplicationHandler $grants_handler_application_handler
   *   The grants_handler.application_handler service.
   * @param \Drupal\helfi_atv\AtvService $helfi_atv_atv_service
   *   The helfi_atv.atv_service service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    GrantsProfileService $grants_profile_service,
    ApplicationHandler $grants_handler_application_handler,
    AtvService $helfi_atv_atv_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->helfiHelsinkiProfiiliUserdata = $helsinkiProfiiliUserData;
    $this->grantsProfileService = $grants_profile_service;
    $this->applicationHandler = $grants_handler_application_handler;
    $this->helfiAtvAtvService = $helfi_atv_atv_service;
  }

  /**
   * Factory function.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   * @param array $configuration
   *   Block config.
   * @param string $plugin_id
   *   Plugin.
   * @param mixed $plugin_definition
   *   Plugin def.
   *
   * @return static
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('helfi_helsinki_profiili.userdata'),
      $container->get('grants_profile.service'),
      $container->get('grants_handler.application_handler'),
      $container->get('helfi_atv.atv_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $selectedCompany = $this->grantsProfileService->getSelectedCompany();

    // If no company selected, no mandates no access.
    if ($selectedCompany == NULL) {
      $destination = \Drupal::request()->getRequestUri();
      $redirectUrl = new Url('grants_mandate.mandateform', [], ['destination' => $destination]);
      $redirectResponse = new RedirectResponse($redirectUrl->toString());
      $redirectResponse->send();
    }

    $helsinkiProfileData = $this->helfiHelsinkiProfiiliUserdata->getUserProfileData();
    $appEnv = ApplicationHandler::getAppEnv();

    $messages = [];
    $submissions = [];

    try {
      $applicationDocuments = $this->helfiAtvAtvService->searchDocuments([
        'service' => 'AvustushakemusIntegraatio',
        'business_id' => $selectedCompany['identifier'],
        'lookfor' => 'appenv:' . $appEnv,
      ]);

      /**
       * Create rows for table.
       *
       * @var integer $key
       * @var  \Drupal\helfi_atv\AtvDocument $document
       */
      foreach ($applicationDocuments as $key => $document) {
        if (
          str_contains($document->getTransactionId(), $appEnv) &&
          array_key_exists($document->getType(), ApplicationHandler::$applicationTypes)
        ) {

          try {
            $submission = ApplicationHandler::submissionObjectFromApplicationNumber($document->getTransactionId(), $document);
            $submissionData = $submission->getData();
            $submissionMessages = ApplicationHandler::parseMessages($submissionData, TRUE);
            $messages += $submissionMessages;

            $ts = strtotime($submissionData['form_timestamp']);
            $submissions[$ts] = $submissionData;

          }
          catch (AtvDocumentNotFoundException $e) {
          }
        }
      }

    }
    catch (\Exception $e) {
    }

    $lang = \Drupal::languageManager()->getCurrentLanguage();
    ksort($submissions);
    $build = [
      '#theme' => 'grants_oma_asiointi_block',
      '#messages' => $messages,
      '#submissions' => $submissions,
      '#userProfileData' => $helsinkiProfileData['myProfile'],
      '#applicationTypes' => ApplicationHandler::$applicationTypes,
      '#lang' => $lang->getId(),
    ];
    return $build;
  }

}
