<?php

namespace Drupal\grants_profile\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Returns responses for Grants Profile routes.
 */
class GrantsProfileController extends ControllerBase {

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
    $selectedCompanyArray = $grantsProfileService->getSelectedCompany();

    if ($selectedCompanyArray == NULL) {
      $this->messenger()
        ->addError($this->t('No profile data available, select company'), TRUE);

      return new RedirectResponse('/asiointirooli-valtuutus');
    }
    else {

      $selectedCompany = $selectedCompanyArray['identifier'];

      $profile = $grantsProfileService->getGrantsProfileContent($selectedCompany, TRUE);

      if (empty($profile)) {
        $editProfileUrl = Url::fromRoute(
          'grants_profile.edit'
        );
        return new RedirectResponse($editProfileUrl->toString());
      }

      $build['#profile'] = $profile;
    }

    $build['#theme'] = 'own_profile';
    $initials = NULL;
    $name = $profile['companyName'] ?? '';
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

    $editProfileUrl = Url::fromRoute(
      'grants_profile.edit',
      [
        'attributes' => [
          'data-drupal-selector' => 'application-edit-link',
        ],
      ]
    );
    $build['#editProfileLink'] = Link::fromTextAndUrl($this->t('Edit profile <i class="hds-icon icon hds-icon--arrow-right hds-icon--size-s vertical-align-small-or-medium-icon" aria-hidden="true"></i>'), $editProfileUrl);

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
    $selectedCompany = $grantsProfileService->getSelectedCompany();

    if ($grantsProfileService->removeAddress($address_id)) {
      try {

        $profileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany['identifier']);
        $grantsProfileService->saveGrantsProfile($profileContent);

        $this->messenger()
          ->addStatus($this->t('Address deleted.'));

      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
        $this->getLogger('grants_profile')
          ->error('Profile saving failed. ' . $e->getMessage());
        $this->messenger()
          ->addStatus($this->t('Address deleting failed.'));
      }
    }
    $editProfileUrl = Url::fromRoute(
      'grants_profile.edit'
    );
    return new RedirectResponse($editProfileUrl->toString());
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
    $selectedCompanyData = $grantsProfileService->getSelectedCompany();
    $selectedCompany = $selectedCompanyData['identifier'];

    if ($grantsProfileService->removeOfficial($official_id)) {
      $profileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);

      try {
        $grantsProfileService->saveGrantsProfile($profileContent);

        $this->messenger()
          ->addStatus($this->t('Official deleted.'));

      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
        $this->getLogger('grants_profile')
          ->error('Profile saving failed. ' . $e->getMessage());
        $this->messenger()
          ->addStatus($this->t('Official deleted & profile saved.'));
      }
    }

    $editProfileUrl = Url::fromRoute(
      'grants_profile.edit'
    );
    return new RedirectResponse($editProfileUrl->toString());
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
    $selectedCompanyData = $grantsProfileService->getSelectedCompany();
    $selectedCompany = $selectedCompanyData['identifier'];
    $grantsProfile = $grantsProfileService->getGrantsProfile($selectedCompany);

    $bankAccount = $grantsProfileService->getBankAccount($bank_account_id);

    $attachment = $grantsProfile->getAttachmentForFilename($bankAccount['confirmationFile']);
    try {

      unset($bankAccount['confirmationFile']);

      $grantsProfileService->removeBankAccount($bank_account_id);
      $profileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);

      $grantsProfileService->saveGrantsProfile($profileContent);

      $this->messenger()
        ->addStatus($this->t('Bank account deleted'));

    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      unset($bankAccount['confirmationFile']);
      $grantsProfileService->removeBankAccount($bank_account_id);
      $profileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany);
      try {
        $grantsProfileService->saveGrantsProfile($profileContent);
      }
      catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
        $this->getLogger('grants_profile')
          ->error('Profile saving failed. ' . $e->getMessage());
        $this->messenger()
          ->addStatus($this->t('Bank account deletion failed, and error has been logged.'));
      }
    }

    $editProfileUrl = Url::fromRoute(
      'grants_profile.edit'
    );
    return new RedirectResponse($editProfileUrl->toString());

  }

}
