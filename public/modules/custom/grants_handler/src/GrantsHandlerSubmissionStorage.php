<?php

namespace Drupal\grants_handler;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition;
use Drupal\helfi_atv\AtvService;
use Drupal\webform\WebformSubmissionInterface;
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
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

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

    /** @var \Drupal\Core\Session\AccountInterface account */
    $instance->account = \Drupal::currentUser();

    return $instance;
  }

  /**
   * Make sure no form data is saved.
   *
   * Maybe we could save data to ATV here? Probably not though, depends how
   * often this is called.
   *
   * @inheritdoc
   */
  public function saveData(WebformSubmissionInterface $webform_submission, $delete_first = TRUE) {
  }

  /**
   * Save webform submission data from the 'webform_submission_data' table.
   *
   * @param array $webform_submissions
   *   An array of webform submissions.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function loadData(array &$webform_submissions) {
    parent::loadData($webform_submissions);

    // $dataDefinition = YleisavustusHakemusDefinition::create('grants_metadata_yleisavustushakemus');

    // /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    // foreach ($webform_submissions as $submission) {
    //   $applicationNumber = '';
    //   try {
    //     if ($submission->getOwnerId() == $this->account->id()) {
    //       $applicationNumber = ApplicationHandler::createApplicationNumber($submission);
    //       $results = $this->atvService->searchDocuments(['transaction_id' => $applicationNumber], TRUE);

    //       /** @var \Drupal\helfi_atv\AtvDocument $document */
    //       $document = reset($results);

    //       // $attStatus = $document->attachmentsUploadStatus();
    //       $appData = $this->atvSchema->documentContentToTypedData($document->getContent(), $dataDefinition);

    //       // $data = $appData->toArray();
    //       $submission->setData($appData);

    //     }
    //   }
    //   catch (\Exception $exception) {
    //     $this->loggerFactory->get('GrantsHandlerSubmissionStorage')
    //       ->error('Document ' . $applicationNumber .
    //         ' not found when loading WebformSubmission: ' .
    //         $submission->uuid() . '. Error: ' . $exception->getMessage());
    //     $submission->setData([]);
    //   }

    // }
  }

}
