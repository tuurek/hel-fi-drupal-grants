<?php

namespace Drupal\grants_profile;

use Drupal\Component\Serialization\Json;
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

  protected array $grantsProfile;
  protected array $grantsProfileContent;

  /**
   * Constructs a GrantsProfileService object.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   */
  public function __construct(AtvService $helfi_atv, HelsinkiProfiiliUserData $helsinkiProfiiliUserData) {
    $this->helfiAtv = $helfi_atv;
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;
  }

  public function getGrantsProfileContent($businessId) {
    if(!empty($this->grantsProfileContent)) {
      return $this->grantsProfileContent;
    }

    $profileData = $this->getGrantsProfileFromAtv($businessId);
    $this->grantsProfileContent = Json::decode(str_replace("'","\"", $profileData['content']));
    $this->grantsProfile = $profileData;

    return $this->grantsProfileContent;

  }

  public function getGrantsProfile($businessId) {
    if(!empty($this->grantsProfile)) {
      return $this->grantsProfile;
    }

    $profileData = $this->getGrantsProfileFromAtv($businessId);
    $this->grantsProfileContent = Json::decode(str_replace("'","\"", $profileData['content']));
    $this->grantsProfile = $profileData;

    return $this->grantsProfile;

  }

  private function getGrantsProfileFromAtv($businessId) {

    $searchParams = [
      'business_id' => $businessId,
      'type' => 'grants_profile',
    ];

    $searchDocuments = $this->helfiAtv->searchDocuments($searchParams);
    return reset($searchDocuments);
  }


}
