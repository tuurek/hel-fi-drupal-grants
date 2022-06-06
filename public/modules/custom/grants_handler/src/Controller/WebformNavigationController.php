<?php

namespace Drupal\grants_handler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Grants Handler routes.
 */
class WebformNavigationController extends ControllerBase {

  /**
   * Clear submission logs for given submission.
   *
   * @param string $submission_id
   *   SUbmission.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to form. @todo this needs a dynamic version.
   */
  public function clearDraftData(string $submission_id): RedirectResponse {

    $submission = WebformSubmission::load($submission_id);

    /** @var \Drupal\webformnavigation\WebformNavigationHelper $wfNaviHelper */
    $wfNaviHelper = \Drupal::service('webformnavigation.helper');

    $wfNaviHelper->deleteSubmissionLogs($submission);

    return new RedirectResponse('/form/yleisavustushakemus');

  }

}
