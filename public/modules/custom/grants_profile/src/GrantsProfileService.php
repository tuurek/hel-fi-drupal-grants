<?php

namespace Drupal\grants_profile;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\grants_metadata\AtvSchema;
use Drupal\grants_profile\TypedData\Definition\GrantsProfileDefinition;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;

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
   * The Messenger service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiili;

  /**
   * ATV Schema mapper
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * Constructs a GrantsProfileService object.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore
   *   Storage factory for temp store.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Show messages to user.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *   Access to Helsinki profiili data.
   */
  public function __construct(
    AtvService               $helfi_atv,
    PrivateTempStoreFactory  $tempstore,
    MessengerInterface       $messenger,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    AtvSchema                $atv_schema
  ) {
    $this->helfiAtv = $helfi_atv;
    $this->tempStore = $tempstore->get('grants_profile');
    $this->messenger = $messenger;
    $this->helsinkiProfiili = $helsinkiProfiiliUserData;
    $this->atvSchema = $atv_schema;
  }

  /**
   * Create new profile to be saved to ATV.
   *
   * @param array $data
   *   Data for the new profile document.
   *
   * @return array
   *   New profile
   */
  public function newProfile(array $data): array {

    $newProfile = [];
    $selectedCompany = $this->getSelectedCompany();
    $userProfile = $this->helsinkiProfiili->getUserProfileData();

    // If data is already in profile format, use that as is.
    if (isset($data['content'])) {
      $newProfile = $data;
    }
    else {
      // Or create new content field.
      $newProfile['content'] = $data;
    }

    $newProfile['type'] = 'grants_profile';
    $newProfile['business_id'] = $selectedCompany;
    $newProfile['user_id'] = $userProfile['id'];

    $newProfile['transaction_id'] = $this->newProfileTransactionId();
    $newProfile['tos_record_id'] = $this->newProfileTosRecordId();
    $newProfile['tos_function_id'] = $this->newProfileTosFunctionId();

    $newProfile['metadata'] = $this->newProfileMetadata();
    return $newProfile;
  }

  /**
   * Metadata fields for new profile.
   *
   * @return string[]
   *   Array containing metadata.
   */
  protected function newProfileMetadata(): array {
    return [
      'metadata-field' => 'metadata-value',
    ];
  }

  /**
   * Transaction ID for new profile.
   *
   * @return string
   *   Transaction ID
   * @todo This can probaably be hardcoded.
   *
   */
  protected function newProfileTransactionId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * TOS ID.
   *
   * @return string
   *   TOS id
   */
  protected function newProfileTosRecordId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * Function Id.
   *
   * @return string
   *   New function ID.
   */
  protected function newProfileTosFunctionId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * Format data from tempstore & save document back to ATV.
   *
   * @return bool|null
   *   Did save succeed?
   */
  public function saveGrantsProfile(): ?bool {
    // Get selected company.
    $selectedCompany = $this->tempStore->get('selected_company');
    // Get grants profile.
    $grantsProfile = $this->getGrantsProfile($selectedCompany['business_id']);
    $payloadData = [
      'content' => $grantsProfile['content'],
    ];

    // If we don't have profile, one needs to be created.
    if (!isset($grantsProfile['id'])) {
      $this->messenger->addError('No profile saved');

      $newProfile = $this->newProfile($payloadData);

      return FALSE;
    }
    else {

      return $this->helfiAtv->patchDocument($grantsProfile['id'], $payloadData);

    }
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
      $nextId = count($addresses);
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

    if (isset($profileContent["addresses"][$address_id])) {
      return $profileContent["addresses"][$address_id];
    }
    else {
      return [
        'street' => '',
        'city' => '',
        'postCode' => '',
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

    if (isset($profileContent["bankAccounts"][$bank_account_id])) {
      return $profileContent["bankAccounts"][$bank_account_id];
    }
    else {
      return [
        'bankAccount' => '',
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
      $nextId = count($officials);
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
    $bankAccounts = (isset($profileContent['bankAccounts']) && $profileContent['bankAccounts'] !== NULL) ? $profileContent['bankAccounts'] : [];

    if ($bank_account_id == 'new') {
      $nextId = count($bankAccounts);
    }
    else {
      $nextId = $bank_account_id;
    }

    $bankAccounts[$nextId] = $bank_account;
    $profileContent['bankAccounts'] = $bankAccounts;
    $this->setToCache($selectedCompany, $profileContent);
  }

  /**
   * Get "content" array from document in ATV.
   *
   * @param string $businessId
   *   What business data is fetched.
   * @param bool $refetch
   *   If true, data is fetched always.
   *
   * @return array
   *   Content
   */
  public function getGrantsProfileContent(string $businessId, bool $refetch = FALSE): array {


    if ($refetch === FALSE && $this->isCached($businessId)) {
      $profileData = $this->getFromCache($businessId);
      if (is_string($profileData['content'])) {
        // @todo when content is proper json, remove str_replace
        return Json::decode(str_replace("'", "\"", $profileData['content']));
      }
      return $profileData['content'];
    }


    $content = Json::decode($this->getDemoContent());

    $dataDefinition = GrantsProfileDefinition::create('grants_profile_profile');
    $profileData = $this->atvSchema->documentContentToTypedData($content, $dataDefinition)
      ->toArray();

    return $profileData;


    //
    //    $profileData = $this->getGrantsProfile($businessId);
    //    if (isset($profileData['content']) && is_string($profileData['content'])) {
    //      // @todo when content is proper json, remove str_replace
    //      return Json::decode(str_replace("'", "\"", $profileData['content']));
    //    }
    //    return $profileData;

  }

  private function getDemoContent() {
    return '{
  "grantsProfile": {
    "profileInfoArray": [
      {
        "ID": "companyName",
        "label": "Company name",
        "value": "Testiyritys",
        "valueType": "string"
      },
      {
        "ID": "companyNameShort",
        "label": "xx",
        "value": "TYY",
        "valueType": "string"
      },
      {
        "ID": "companyHome",
        "label": "home",
        "value": "Helsinki",
        "valueType": "string"
      },
      {
        "ID": "companyHomePage",
        "label": "Homepage",
        "value": "htps://www.yle.fi",
        "valueType": "string"
      },
      {
        "ID": "companyEmail",
        "label": "Email",
        "value": "testi@email.com",
        "valueType": "string"
      },
      {
        "ID": "foundingYear",
        "label": "Perustusvuosi",
        "value": "2022",
        "valueType": "int"
      }
    ],
    "officialsArray": [
      [
        {
          "ID": "name",
          "label": "Nimi",
          "value": "Koko Nimi",
          "valueType": "string"
        },
        {
          "ID": "role",
          "label": "Rooli",
          "value": "2",
          "valueType": "string"
        },
        {
          "ID": "email",
          "label": "Sähköposti",
          "value": "kolo@mail.com",
          "valueType": "string"
        },
        {
          "ID": "phone",
          "label": "Puhelinnumero",
          "value": "09-616527788",
          "valueType": "string"
        }
      ]
    ],
    "addressesArray": [
    [
      {
        "ID": "street",
        "label": "Katuosoite",
        "value": "Sannikontie",
        "valueType": "string"
      },
      {
        "ID": "phoneNumber",
        "label": "Puhelinnumero",
        "value": "+358404040404",
        "valueType": "string"
      },
      {
        "ID": "city",
        "label": "Postitoimipaikka",
        "value": "Kouvola",
        "valueType": "string"
      },
      {
        "ID": "postCode",
        "label": "Postinumero",
        "value": "46400",
        "valueType": "string"
      },
      {
        "ID": "country",
        "label": "Maa",
        "value": "Suomi",
        "valueType": "string"
      }
    ]
    ],
    "bankAccountsArray": [
      [
       {
        "ID": "bankAccount",
        "label": "Bank account",
        "value": "FI985763984657",
        "valueType": "string"
      }
      ]
    ]
  }
}';
  }

  /**
   * Get profile Document.
   *
   * @param string $businessId
   *   Business id for profile.
   * @param bool $refetch
   *   Force refetching of the data.
   *
   * @return array
   *   Profiledata
   */
  public function getGrantsProfile(string $businessId, bool $refetch = FALSE): array {
    if ($refetch == FALSE) {
      if ($this->isCached($businessId)) {
        $document = $this->getFromCache($businessId);
        if (!isset($document['id'])) {
          $this->messenger->addStatus('Refetching document...');
        }
        else {
          return $document ?? [];
        }
      }
    }

    $profileData = $this->getGrantsProfileFromAtv($businessId);
    if (!empty($profileData)) {
      $this->setToCache($businessId, $profileData);
      return $profileData;
    }
    else {
      return $document ?? [];
    }
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
      'transaction_id' => $businessId,
      'type' => 'grants_profile',
    ];

    $searchDocuments = $this->helfiAtv->searchDocuments($searchParams);

    if (empty($searchDocuments)) {
      return $searchDocuments;
    }
    // @todo merge profiles if multiple is saved for some reason.
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
    } catch (TempStoreException $e) {
      return FALSE;
    }
  }

}
