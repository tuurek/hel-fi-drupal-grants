<?php

namespace Drupal\grants_profile;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\helfi_atv\AtvService;

/**
 * Handle all profile functionality.
 */
class GrantsProfileService {

  /**
   * The helfi_atv service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $helfiAtv;

  /**
   * Session storage for profile data.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Constructs a GrantsProfileService object.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore
   *   Storage factory for temp store.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Show messages to user.
   */
  public function __construct(
    AtvService $helfi_atv,
    PrivateTempStoreFactory $tempstore,
    MessengerInterface $messenger
  ) {
    $this->helfiAtv = $helfi_atv;
    $this->tempStore = $tempstore->get('grants_profile');
    $this->messenger = $messenger;
  }

  /**
   * Format data from tempstore & save document back to ATV.
   */
  public function saveGrantsProfile(): ?bool {
    $selectedCompany = $this->tempStore->get('selected_company');
    $grantsProfile = $this->getGrantsProfile($selectedCompany['business_id']);

    $updateData = [
      'content' => $grantsProfile['content'],
    ];

    if (!isset($grantsProfile['id'])) {
      $this->messenger->addError('No profile document / incorrect structure returned');
      return FALSE;
    }

    return $this->helfiAtv->patchDocument($grantsProfile['id'], $updateData);
  }

  /**
   * Save address to session + ATV.
   *
   * @param string $address_id
   *   Address id in store.
   * @param array $address
   *   Address array.
   */
  public function saveAddress(string $address_id, array $address): bool {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany);
    $addresses = (isset($profileContent['addresses']) && $profileContent['addresses'] !== NULL) ? $profileContent['addresses'] : [];

    if ($address_id == 'new') {
      $nextId = count($addresses) + 1;
    }
    else {
      $nextId = $address_id;
    }

    $addresses[$nextId] = $address;
    $profileContent['addresses'] = $addresses;
    return $this->setToCache($selectedCompany, $profileContent);
  }

  /**
   * Get address from store.
   *
   * @param string $address_id
   *   Address id to fetch.
   *
   * @return string[]
   *   Array containing address or new address
   */
  public function getAddress(string $address_id): array {
    $selectedCompany = $this->tempStore->get('selected_company');
    $profileContent = $this->getGrantsProfileContent($selectedCompany['business_id']);

    if (isset($profileContent['addresses'][$address_id])) {
      return $profileContent['addresses'][$address_id];
    }
    else {
      return [
        'street' => '',
        'city' => '',
        'post_code' => '',
        'country' => '',
      ];
    }
  }

  /**
   * Get official data from store.
   *
   * @param string $official_id
   *   ID to get.
   *
   * @return string[]
   *   Array containing official data
   */
  public function getOfficial(string $official_id): array {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany);

    if (isset($profileContent['officials'][$official_id])) {
      return $profileContent['officials'][$official_id];
    }
    else {
      return [
        'name' => '',
        'role' => '',
        'email' => '',
        'phone' => '',
      ];
    }
  }

  /**
   * Get bank account data from store.
   *
   * @param string $bank_account_id
   *   ID to get.
   *
   * @return string[]
   *   Array containing official data
   */
  public function getBankAccount(string $bank_account_id): array {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany);

    if (isset($profileContent['bank_accounts'][$bank_account_id])) {
      return $profileContent['bank_accounts'][$bank_account_id];
    }
    else {
      return [
        'bank_account' => '',
      ];
    }
  }

  /**
   * SAve official to store + ATV.
   *
   * @param string $official_id
   *   Id to save, "new" if adding a new.
   * @param array $official
   *   Data to be saved.
   */
  public function saveOfficial(string $official_id, array $official) {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany);
    $officials = (isset($profileContent['officials']) && $profileContent['officials'] !== NULL) ? $profileContent['officials'] : [];

    if ($official_id == 'new') {
      $nextId = count($officials) + 1;
    }
    else {
      $nextId = $official_id;
    }

    $officials[$nextId] = $official;
    $profileContent['officials'] = $officials;
    $this->setToCache($selectedCompany, $profileContent);
  }

  /**
   * Save bank account to ATV.
   *
   * @param string $bank_account_id
   *   Id to save, "new" if adding a new.
   * @param array $bank_account
   *   Data to be saved.
   */
  public function saveBankAccount(string $bank_account_id, array $bank_account) {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany);
    $bankAccounts = (isset($profileContent['bank_accounts']) && $profileContent['bank_accounts'] !== NULL) ? $profileContent['bank_accounts'] : [];

    if ($bank_account_id == 'new') {
      $nextId = count($bankAccounts) + 1;
    }
    else {
      $nextId = $bank_account_id;
    }

    $bankAccounts[$nextId] = $bank_account;
    $profileContent['bank_accounts'] = $bankAccounts;
    $this->setToCache($selectedCompany, $profileContent);
  }

  /**
   * Get "content" array from document in ATV.
   *
   * @param string $businessId
   *   What business data is fetched.
   *
   * @return array
   *   Content
   */
  public function getGrantsProfileContent(string $businessId): array {
    if ($this->isCached($businessId)) {
      $profileData = $this->getFromCache($businessId);
      if (is_string($profileData['content'])) {
        // @todo when content is proper json, remove str_replace
        return Json::decode(str_replace("'", "\"", $profileData['content']));
      }
      return $profileData['content'];
    }

    $profileData = $this->getGrantsProfile($businessId);
    if (isset($profileData['content']) && is_string($profileData['content'])) {
      // @todo when content is proper json, remove str_replace
      return Json::decode(str_replace("'", "\"", $profileData['content']));
    }
    return $profileData;

  }

  /**
   * Get profile Document.
   *
   * @param string $businessId
   *   Business id for profile.
   *
   * @return array
   *   Profiledata
   */
  public function getGrantsProfile(string $businessId): ?array {
    if ($this->isCached($businessId)) {
      $document = $this->getFromCache($businessId);
      if (!isset($document['id'])) {
        $this->messenger->addStatus('Refetching document...');
      }
      else {
        return $document;
      }
    }

    $profileData = $this->getGrantsProfileFromAtv($businessId);
    if (!empty($profileData)) {
      $this->setToCache($businessId, $profileData);
    }

    return $profileData;

  }

  /**
   * Get profile data from ATV.
   *
   * @param string $businessId
   *   Id to be fetched.
   *
   * @return array
   *   Profile data
   */
  private function getGrantsProfileFromAtv(string $businessId): array {

    $searchParams = [
      'business_id' => $businessId,
      'type' => 'grants_profile',
    ];

    $searchDocuments = $this->helfiAtv->searchDocuments($searchParams);

    if (empty($searchDocuments)) {
      return $searchDocuments;
    }

    return reset($searchDocuments);
  }

  /**
   * Get selected company id.
   *
   * @return string|null
   *   Selected company
   */
  public function getSelectedCompany(): ?string {
    if ($this->isCached('selected_company')) {
      $data = $this->getFromCache('selected_company');
      return $data['business_id'];
    }
    return NULL;
  }

  /**
   * Set selected business id to store.
   *
   * @param string $businessId
   *   ID to be saved.
   */
  public function setSelectedCompany(string $businessId): bool {
    return $this->setToCache('selected_company', ['business_id' => $businessId]);
  }

  /**
   * Whether or not we have made this query?
   *
   * @param string $key
   *   Used key for caching.
   *
   * @return bool
   *   Is this cached?
   */
  private function isCached(string $key): bool {
    $cacheData = $this->tempStore->get($key);
    return !is_null($cacheData);
  }

  /**
   * Get item from cache.
   *
   * @param string $key
   *   Key to fetch from tempstore.
   *
   * @return array|null
   *   Data in cache or null
   */
  private function getFromCache(string $key): ?array {
    $retval = !empty($this->tempStore->get($key)) ? $this->tempStore->get($key) : NULL;
    return $retval;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param array $data
   *   Cached data.
   *
   * @return bool
   *   Did save succeed?
   */
  private function setToCache(string $key, array $data): bool {

    try {
      if (isset($data['content'])) {
        $this->tempStore->set($key, $data);
        return TRUE;
      }
      elseif ($key == 'selected_company') {
        $this->tempStore->set($key, $data);
        return TRUE;
      }
      else {
        $grantsProfile = $this->getGrantsProfile($key);
        $grantsProfile['content'] = $data;
        $this->tempStore->set($key, $grantsProfile);
        return TRUE;
      }
    }
    catch (TempStoreException $e) {
      return FALSE;
    }
  }

}
