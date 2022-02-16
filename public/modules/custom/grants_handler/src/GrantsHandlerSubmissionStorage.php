<?php

namespace Drupal\grants_handler;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\helfi_atv\AtvService;
use Drupal\webform\WebformSubmissionStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Override loading of WF submission from data from ATV.
 *
 * This could be used overriding the saving as well,
 * but for now this is enough.
 */
class GrantsHandlerSubmissionStorage extends WebformSubmissionStorage {

  /**
   * Atv service object.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Schema mapper.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type): WebformSubmissionStorage|EntityHandlerInterface {

    /** @var \Drupal\webform\WebformSubmissionStorage $instance */
    $instance = parent::createInstance($container, $entity_type);

    /** @var \Drupal\helfi_atv\AtvService atvService */
    $instance->atvService = $container->get('helfi_atv.atv_service');

    /** @var \Drupal\grants_metadata\AtvSchema $atvSchema */
    $instance->atvSchema = \Drupal::service('grants_metadata.atv_schema');

    return $instance;
  }

  /**
   * Save webform submission data from the 'webform_submission_data' table.
   *
   * @param array $webform_submissions
   *   An array of webform submissions.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function loadData(array &$webform_submissions) {
    parent::loadData($webform_submissions);

    $dataDefinition = YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus');

    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    foreach ($webform_submissions as $submission) {

      // $documentContent =
      // $this->getSubmission('e5ed6430-4059-4284-859f-50137a1eee53');
      $document = $this->atvService->getDocument('e5ed6430-4059-4284-859f-50137a1eee53');

      $appData = $this->atvSchema->documentContentToTypedData($document->getContent(), $dataDefinition);

      $data = $appData->toArray();

      $submission->setData($data);
    }
  }

}
