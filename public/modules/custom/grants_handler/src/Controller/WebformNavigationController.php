<?php

namespace Drupal\grants_handler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Grants Handler routes.
 */
class WebformNavigationController extends ControllerBase {


  public function clearDraftData($submission_id) {

    $request = \Drupal::request();

    $submission = WebformSubmission::load($submission_id);

    /** @var \Drupal\webformnavigation\WebformNavigationHelper $wfNaviHelper */
    $wfNaviHelper = \Drupal::service('webformnavigation.helper');

    $wfNaviHelper->deleteSubmissionLogs($submission);

    return new RedirectResponse('/form/yleisavustushakemus');

  }

}
