<?php

namespace Drupal\grants_handler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Grants Handler routes.
 */
class WebformNavigationController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Clear submission logs for given submission.
   *
   * @param string $submission_id
   *   SUbmission.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to form. @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function clearDraftData(string $submission_id): RedirectResponse {

    $submission = WebformSubmission::load($submission_id);
    $submissionData = $submission->getData();
    $webForm = $submission->getWebform();

    if (empty($submissionData)) {
      $submissionDeleteResult = $submission->delete();
    }
    elseif ($submissionData['status'] !== 'DRAFT') {
      \Drupal::messenger()
        ->addError($this->t('Only DRAFT status submissions are deletable'));
      // Throw new AccessException('Only DRAFT status submissions
      // are deletable');.
    }
    else {

      /** @var \Drupal\webformnavigation\WebformNavigationHelper $wfNaviHelper */
      $wfNaviHelper = \Drupal::service('grants_handler.navigation_helper');

      /** @var \Drupal\helfi_atv\AtvService $atvService */
      $atvService = \Drupal::service('helfi_atv.atv_service');

      $applicationNumber = ApplicationHandler::createApplicationNumber($submission);
      $wfNaviHelper->deleteSubmissionLogs($submission);

      try {
        $document = $atvService->searchDocuments(
          [
            'transaction_id' => $applicationNumber,
          ]
        );
        /** @var \Drupal\helfi_atv\AtvDocument $document */
        $document = reset($document);

        if ($atvService->deleteDocument($document)) {
          $submissionDeleteResult = $submission->delete();
          \Drupal::messenger()->addStatus('Draft deleted & data cleared');
        }
      }
      catch (\Exception $e) {
        \Drupal::messenger()
          ->addError($this->t('Deleting failed. Error has been logged, please contact support.'));
        \Drupal::logger('grants_handler')->error($e->getMessage());
      }
    }

    return new RedirectResponse('/oma-asiointi');

  }

}
