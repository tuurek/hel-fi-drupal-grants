<?php

namespace Drupal\grants_profile;

use Drupal\Component\Serialization\Json;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;

/**
 * GrantsProfileService service.
 */
class GrantsProfileService {

  /**
   *
   * The helfi_atv service.
   *
   * @var AtvService
   **/
  protected AtvService $helfiAtv;

  /**
   *
   * The helfi_atv service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   **/
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  protected PrivateTempStore $tempStore;

  /**
   * Constructs a GrantsProfileService object.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   */
  public function __construct(
    AtvService               $helfi_atv,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    PrivateTempStoreFactory  $tempstore
  ) {
    $this->helfiAtv = $helfi_atv;
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;

    $this->tempStore = $tempstore->get('grants_profile');
  }

  /**
   * Format data from tempstore & save document back to ATV
   */
  public function saveGrantsProfile() {
    $selectedCompany = $this->tempStore->get('selected_company');
    $grantsProfile = $this->getGrantsProfile($selectedCompany);

    $updateData = [
      'content' => $grantsProfile['content']
    ];

    $this->helfiAtv->patchDocument($grantsProfile['id'], $updateData);
  }

  public function saveAddress($address_id, $address) {
    $selectedCompany = $this->tempStore->get('selected_company');
    $profileContent = $this->getGrantsProfileContent($selectedCompany);
    $addresses = $profileContent['addresses'] !== NULL ? $profileContent['addresses'] : [];

    if ($address_id == 'new') {
      $nextId = count($addresses) + 1;
    } else {
      $nextId = $address_id;
    }

    $addresses[$nextId] = $address;
    $profileContent['addresses'] = $addresses;
    $this->setToCache($selectedCompany, $profileContent);
  }

  public function getAddress($address_id){
    $selectedCompany = $this->tempStore->get('selected_company');
    $profileContent = $this->getGrantsProfileContent($selectedCompany);

    if (isset($profileContent['addresses'][$address_id])) {
      return $profileContent['addresses'][$address_id];
    } else {
      return [
        'street' => '',
        'city' => '',
        'post_code' => '',
        'country' => '',
      ];
    }

  }

  public function getGrantsProfileContent($businessId) {
    if ($this->isCached($businessId)) {
      $profileData = $this->getFromCache($businessId);
      if (is_string($profileData['content'])) {
        // TODO: when content is proper json, remove str_replace
        return Json::decode(str_replace("'", "\"", $profileData['content']));
      }
      return $profileData['content'];
    }

    $profileData = $this->getGrantsProfileFromAtv($businessId);
    $this->setToCache($businessId, $profileData);

    return Json::decode(str_replace("'", "\"", $profileData['content']));

  }

  public function getGrantsProfile($businessId) {
    if ($this->isCached($businessId)) {
      return $this->getFromCache($businessId);
    }

    $profileData = $this->getGrantsProfileFromAtv($businessId);
    $this->setToCache($businessId, $profileData);

    return $profileData;

  }

  private function getGrantsProfileFromAtv($businessId) {

    $searchParams = [
      'business_id' => $businessId,
      'type' => 'grants_profile',
    ];

    $searchDocuments = $this->helfiAtv->searchDocuments($searchParams);
    return reset($searchDocuments);
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
    return !empty($this->tempStore->get($key));
  }

  /**
   * Get item from cache.
   *
   * @param string $key
   *  Key to fetch from tempstore
   *
   * @return mixed|null
   *   Data in cache or null
   */
  private function getFromCache(string $key) {
    return !empty($this->tempStore->get($key)) ? $this->tempStore->get($key) : NULL;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param array $data
   *   Cached data.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  private function setToCache(string $key, array $data) {

    if (isset($data['content'])) {
      $this->tempStore->set($key, $data);
    }
    else {
      $grantsProfile = $this->getGrantsProfile($key);
      $grantsProfile['content'] = $data;
      $this->tempStore->set($key, $grantsProfile);
    }
  }


}
