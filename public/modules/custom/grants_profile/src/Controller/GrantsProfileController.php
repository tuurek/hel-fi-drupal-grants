<?php

namespace Drupal\grants_profile\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\Url;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Grants Profile routes.
 */
class GrantsProfileController extends ControllerBase {

  /**
   * View single application.
   *
   * @param string $document_uuid
   *   Uuid to be shown.
   *
   * @return array
   *   Build for the page.
   *
   * @throws \Exception
   */
  public function viewApplication(string $document_uuid) {

    $submissionObject = ApplicationHandler::submissionObjectFromApplicationNumber($document_uuid);

    if ($submissionObject) {
      $data = $submissionObject->getData();
      $webForm = $submissionObject->getWebform();

      // $submissionForm = $webForm->getSubmissionForm(['sid' =>
      // $submissionObject->id(),'data' => $data],'edit');
      if (!empty($data)) {
        // @todo Set up some way to show data. Is webformSubmission needed?
        // $build['#application'] = $submissionObject->getData();
        // $build['#submission_form'] = $submissionForm;
        return $this->redirect('entity.webform.user.submission', [
          'webform' => $webForm->id(),
          'webform_submission' => $submissionObject->id(),
        ]);
      }
      else {
        \Drupal::messenger()
          ->addWarning('No data for submission: ' . $document_uuid);
      }
    }
    else {
      \Drupal::messenger()
        ->addWarning('No submission: ' . $document_uuid);
    }

    $build['#theme'] = 'view_application';

    return $build;
  }

  /**
   * Show company select form.
   *
   * @return array
   *   Build data.
   */
  public function selectCompany(): array {
    $form = \Drupal::formBuilder()
      ->getForm('Drupal\grants_profile\Form\CompanySelectForm');
    $build['#company_select_form'] = $form;

    $build['#theme'] = 'company_select';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;

  }

  /**
   * Builds the response.
   *
   * @return array|\Laminas\Diactoros\Response\RedirectResponse
   *   Data to render
   */
  public function ownProfile(): array|RedirectResponse {
    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    if ($selectedCompany == NULL) {
      $this->messenger()
        ->addError($this->t('No profile data available, select company'), TRUE);
      return new RedirectResponse('/select-company');
    }
    else {
      $profile = $grantsProfileService->getGrantsProfileContent($selectedCompany, TRUE);

      /** @var \Drupal\helfi_atv\AtvService $atvService */
      $atvService = \Drupal::service('helfi_atv.atv_service');

      try {
        // @todo Fix application search when ATV supports better methods.
        $applicationDocuments = $atvService->searchDocuments([
          'type' => 'ECONOMICGRANTAPPLICATION',
          'business_id' => $selectedCompany,
        ],
          TRUE);
        $applications = [];
        /** @var \Drupal\helfi_atv\AtvDocument $document */
        foreach ($applicationDocuments as $document) {
          $transactionId = $document->getTransactionId();
          if (str_contains($transactionId, 'GRANTS-' . GrantsHandler::getAppEnv())) {

            $url = Url::fromRoute(
              'grants_profile.view_application',
              ['document_uuid' => $transactionId],
              [
                'attributes' => [
                  'data-drupal-selector' => 'application-edit-link',
                  'target' => '_blank',
                ],
              ]
            );

            $attachments = $document->attachmentsUploadStatus();

            $uploaded = [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#items' => $attachments['uploaded'],
              '#attributes' => ['class' => 'uploaded-attachments'],
              '#wrapper_attributes' => ['class' => 'container'],
            ];

            $non_uploaded = [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#items' => $attachments['not-uploaded'],
              '#attributes' => ['class' => 'not-uploaded-attachments'],
              '#wrapper_attributes' => ['class' => 'container'],
            ];

            $applications[] = [
              'transaction_id' => Link::fromTextAndUrl($transactionId, $url),
              'uploadedAttachments' => \Drupal::service('renderer')
                ->render($uploaded),
              'missingAttachments' => \Drupal::service('renderer')
                ->render($non_uploaded),
              'status' => $document->getStatus(),
              'statusHistory' => $document->getStatusHistory(),
            ];
          }
        }
        $table = [
          '#type' => 'table',
          '#header' => [
            'transaction_id' => $this->t('Application #'),
            'uploadedAttachments' => $this->t('Attachments uploaded'),
            'missingAttachments' => $this->t('Attachments en route'),
            'status' => $this->t('Application status'),
            'statusHistory' => $this->t('Status history'),
          ],
          '#rows' => $applications,
          '#empty' => t('No content has been found.'),
        ];
        $build['#applications'] = $table;

      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException | TempStoreException $e) {
      }

      $build['#profile'] = $profile;
    }

    $gpForm = \Drupal::formBuilder()
      ->getForm('Drupal\grants_profile\Form\GrantsProfileForm');
    $build['#grants_profile_form'] = $gpForm;

    $build['#theme'] = 'own_profile';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    $build['#title'] = $profile['companyName'];
    $initials = NULL;
    $name = $profile['companyName'];
    $words = explode(' ', $name);
    if (count($words) >= 2) {
      $initials = strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    }
    else {
      preg_match_all('#([A-Z]+)#', $name, $capitals);
      if (count($capitals[1]) >= 2) {
        $initials = substr(implode('', $capitals[1]), 0, 2);
      }
      else {
        $initials = strtoupper(substr($name, 0, 2));
      }

    }
    $build['#initials'] = $initials;
    $build['#colorscheme'] = 0;

    $build['#attached']['library'][] = 'grants_profile/tabs';
    return $build;
  }

  /**
   * Builds the response.
   */
  public function ownAddresses(): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);

    if (!empty($grantsProfileContent['addresses'])) {
      $build['#addresses'] = $grantsProfileContent['addresses'];
    }

    $build['#theme'] = 'own_addresses';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

  /**
   * Builds the response.
   */
  public function applicationOfficials(): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);
    $build['#officials'] =
      (isset($grantsProfile["content"]["officials"]) &&
        !empty($grantsProfile["content"]["officials"])) ?
        $grantsProfile["content"]["officials"] :
        [];

    $build['#theme'] = 'application_officials';
    return $build;
  }

  /**
   * Builds the response.
   */
  public function bankAccounts(): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);

    $build['#bank_accounts'] =
      (isset($grantsProfile["content"]["bank_accounts"]) &&
        !empty($grantsProfile["content"]["bank_accounts"])) ?
        $grantsProfile["content"]["bank_accounts"] :
        [];

    $build['#theme'] = 'bank_accounts';
    $build['#content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

  /**
   * Delete bank account attachment from ATV.
   *
   * @param string $bank_account_id
   *   ID / Index of bank account.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to profile page.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteBankAccountAttachment(string $bank_account_id): RedirectResponse {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);
    $bankAccount = $grantsProfileService->getBankAccount($bank_account_id);

    $attachment = $grantsProfile->getAttachmentForFilename($bankAccount['confirmationFile']);
    try {
      $grantsProfileService->deleteAttachment($selectedCompany, $attachment['id']);

      unset($bankAccount['confirmationFile']);

      $grantsProfileService->saveBankAccount($bank_account_id, $bankAccount);
      $grantsProfileService->saveGrantsProfileAtv();

      $this->messenger()
        ->addStatus($this->t('Bank account confirmation successfully deleted'));

    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      unset($bankAccount['confirmationFile']);

      $grantsProfileService->saveBankAccount($bank_account_id, $bankAccount);
      try {
        $grantsProfileService->saveGrantsProfileAtv();
      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
        $this->getLogger('grants_profile')
          ->error('Profile saving failed. ' . $e->getMessage());
        $this->messenger()
          ->addStatus($this->t('Bank account confirmation deleting failed. Issue has been logged.'));
      }
    }

    return new RedirectResponse('/grants-profile/bank-accounts/' . $bank_account_id);

  }

  /**
   * Remove address from profile data.
   *
   * @param string $address_id
   *   Address id/delta to be deleted.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   REdirect to profile
   */
  public function deleteAddress(string $address_id): RedirectResponse {
    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $grantsProfileService->removeAddress($address_id);

    try {
      $grantsProfileService->saveGrantsProfileAtv();

      $this->messenger()
        ->addStatus($this->t('Address deleted.'));

    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      $this->getLogger('grants_profile')
        ->error('Profile saving failed. ' . $e->getMessage());
      $this->messenger()
        ->addStatus($this->t('Official deleted & profile saved.'));
    }

    return new RedirectResponse('/grants-profile');
  }

  /**
   * Remove official.
   *
   * @param string $official_id
   *   Official id / delta to be removed.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to profile.
   */
  public function deleteOfficial(string $official_id): RedirectResponse {
    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $grantsProfileService->removeOfficial($official_id);

    try {
      $grantsProfileService->saveGrantsProfileAtv();

      $this->messenger()
        ->addStatus($this->t('Official deleted.'));

    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      $this->getLogger('grants_profile')
        ->error('Profile saving failed. ' . $e->getMessage());
      $this->messenger()
        ->addStatus($this->t('Official deleted & profile saved.'));
    }

    return new RedirectResponse('/grants-profile');
  }

  /**
   * Remove bank account.
   *
   * @param string $bank_account_id
   *   BAnk account id/delta.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to profile page.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteBankAccount(string $bank_account_id): RedirectResponse {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();
    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);

    $bankAccount = $grantsProfileService->getBankAccount($bank_account_id);

    $attachment = $grantsProfile->getAttachmentForFilename($bankAccount['confirmationFile']);
    try {
      $grantsProfileService->deleteAttachment($selectedCompany, $attachment['id']);

      unset($bankAccount['confirmationFile']);

      $grantsProfileService->removeBankAccount($bank_account_id);
      $grantsProfileService->saveGrantsProfileAtv();

      $this->messenger()
        ->addStatus($this->t('Bank account deleted'));

    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      unset($bankAccount['confirmationFile']);
      $grantsProfileService->removeBankAccount($bank_account_id);
      try {
        $grantsProfileService->saveGrantsProfileAtv();
      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
        $this->getLogger('grants_profile')
          ->error('Profile saving failed. ' . $e->getMessage());
        $this->messenger()
          ->addStatus($this->t('Bank account deletion failed, and error has been logged.'));
      }
    }

    return new RedirectResponse('/grants-profile');

  }

}
