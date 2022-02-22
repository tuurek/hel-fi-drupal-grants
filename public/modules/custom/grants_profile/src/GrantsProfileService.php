<?php

namespace Drupal\grants_profile;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\file\Entity\File;
use Drupal\grants_metadata\AtvSchema;
use Drupal\helfi_atv\AtvDocument;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\helfi_yjdh\YjdhClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Handle all profile functionality.
 */
class GrantsProfileService {

  use StringTranslationTrait;

  const DOCUMENT_STATUS_NEW = 'DRAFT';

  const DOCUMENT_STATUS_SAVED = 'READY';

  /**
   * The helfi_atv service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

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
   * ATV Schema mapper.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * Access to YTJ / Yrtti.
   *
   * @var \Drupal\helfi_yjdh\YjdhClient
   */
  protected YjdhClient $yjdhClient;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected LoggerChannelFactory|LoggerChannelInterface|LoggerChannel $logger;

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
   * @param \Drupal\grants_metadata\AtvSchema $atv_schema
   *   Atv chema mapper.
   * @param \Drupal\helfi_yjdh\YjdhClient $yjdhClient
   *   Access to yjdh data.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger service.
   */
  public function __construct(
    AtvService $helfi_atv,
    PrivateTempStoreFactory $tempstore,
    MessengerInterface $messenger,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    AtvSchema $atv_schema,
    YjdhClient $yjdhClient,
    LoggerChannelFactory $loggerFactory
  ) {
    $this->atvService = $helfi_atv;
    $this->tempStore = $tempstore->get('grants_profile');
    $this->messenger = $messenger;
    $this->helsinkiProfiili = $helsinkiProfiiliUserData;
    $this->atvSchema = $atv_schema;
    $this->yjdhClient = $yjdhClient;
    $this->logger = $loggerFactory->get('helfi_atv');
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
  public function newProfile(array $data): AtvDocument {

    $newProfileData = [];
    $selectedCompany = $this->getSelectedCompany();
    $userProfile = $this->helsinkiProfiili->getUserProfileData();
    $userData = $this->helsinkiProfiili->getUserData();

    // If data is already in profile format, use that as is.
    if (isset($data['content'])) {
      $newProfileData = $data;
    }
    else {
      // Or create new content field.
      $newProfileData['content'] = $data;
    }

    $newProfileData['type'] = 'grants_profile';
    $newProfileData['business_id'] = $selectedCompany;
    $newProfileData['user_id'] = $userData["sub"];
    $newProfileData['status'] = self::DOCUMENT_STATUS_NEW;

    $newProfileData['tos_record_id'] = $this->newProfileTosRecordId();
    $newProfileData['tos_function_id'] = $this->newProfileTosFunctionId();

    $newProfileData['metadata'] = [
      'business_id' => $selectedCompany,
    ];

    return $this->atvService->createDocument($newProfileData);
  }

  /**
   * Transaction ID for new profile.
   *
   * @return string
   *   Transaction ID
   *
   * @todo Maybe these are Document level stuff?
   *
   * @todo This can probaably be hardcoded.
   */
  protected function newTransactionId($transactionId): string {
    return md5($transactionId);
  }

  /**
   * TOS ID.
   *
   * @return string
   *   TOS id
   *
   * @todo Maybe these are Document level stuff?
   */
  protected function newProfileTosRecordId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * Function Id.
   *
   * @return string
   *   New function ID.
   *
   * @todo Maybe these are Document level stuff?
   */
  protected function newProfileTosFunctionId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * Saves grants profile to cache.
   *
   * @param array|AtvDocument $data
   *   Profile data.
   */
  public function saveGrantsProfile(array|AtvDocument $data) {
    // Get selected company.
    $selectedCompany = $this->tempStore->get('selected_company');
    $this->setToCache($selectedCompany['business_id'], $data);
  }

  /**
   * Format data from tempstore & save document back to ATV.
   *
   * @return bool|AtvDocument
   *   Did save succeed?
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function saveGrantsProfileAtv(): bool|AtvDocument {
    // Get selected company.
    $selectedCompany = $this->tempStore->get('selected_company');
    // Get grants profile.
    $grantsProfileDocument = $this->getGrantsProfile($selectedCompany['business_id']);

    $transactionId = $this->newTransactionId(time());

    if ($grantsProfileDocument->isNew()) {
      $grantsProfileDocument->setStatus(self::DOCUMENT_STATUS_SAVED);
      $grantsProfileDocument->setTransactionId($transactionId);
      $this->logger->info('Grants profile POSTed, transactionID: ' . $transactionId);
      return $this->atvService->postDocument($grantsProfileDocument);
    }
    else {

      $documentContent = $grantsProfileDocument->getContent();

      foreach ($documentContent['bankAccounts'] as $key => $bank_account) {
        if (isset($bank_account['confirmationFile']) && str_contains($bank_account['confirmationFile'], 'FID-')) {
          $fileId = str_replace('FID-', '', $bank_account['confirmationFile']);
          $fileEntity = File::load((int) $fileId);
          if ($fileEntity) {
            $fileName = md5($bank_account['bankAccount']) . '.pdf';
            $documentContent['bankAccounts'][$key]['confirmationFile'] = $fileName;
            $retval = $this->atvService->uploadAttachment($grantsProfileDocument->getId(), $fileName, $fileEntity);

            if ($retval) {
              $this->messenger->addStatus(
                $this->t('Confirmation file saved for account %account. You can now use this account as receipient of grants.',
                  ['%account' => $bank_account['bankAccount']]
                )
              );
            }
            else {
              $this->messenger->addStatus(
                $this->t('Confirmation file saving failed for %account. This account cannot be used with applications without valid confirmation file.',
                  ['%account' => $bank_account['bankAccount']]
                )
              );
            }
            try {
              // Delete temp file.
              $fileEntity->delete();

              $this->logger->debug($this->t(
                'File deleted: %id.',
                [
                  '%id' => $fileEntity->id(),
                ]
              ));
            }
            catch (EntityStorageException $e) {
              $this->logger->error($this->t(
                'File deleting failed: %id.',
                [
                  '%id' => $fileEntity->id(),
                ]
              ));
            }
          }
          else {
            $this->logger->error($this->t(
              'No file found: %id.',
              [
                '%id' => $fileEntity->id(),
              ]
            ));

            $this->messenger->addError(
                        $this->t('Confirmation file saving failed for %account. This account cannot be used with applications without valid confirmation file.',
                          ['%account' => $bank_account['bankAccount']]
                        )
                      );
          }

        }
      }

      $payloadData = [
        'content' => $documentContent,
        'metadata' => $grantsProfileDocument->getMetadata(),
        'transaction_id' => $this->newTransactionId($transactionId),
      ];
      $this->logger->info('Grants profile POSTed, transactionID: ' . $transactionId);
      return $this->atvService->patchDocument($grantsProfileDocument->getId(), $payloadData);
    }
  }

  /**
   * Save address to session.
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
   * Delete attachment from selected company's grants profile document.
   *
   * @param string $selectedCompany
   *  Selected company.
   * @param string $attachmentId
   *  Attachment to delete.
   *
   * @return \Drupal\helfi_atv\AtvDocument|bool|array
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteAttachment(string $selectedCompany, string $attachmentId): AtvDocument|bool|array {
    $grantsProfile = $this->getGrantsProfile($selectedCompany);
    return $this->atvService->deleteAttachment($grantsProfile->getId(), $attachmentId);
  }

  /**
   * Get address from store.
   *
   * @param string $address_id
   *   Address id to fetch.
   * @param bool $refetch
   *   Refetch data from ATV.
   *
   * @return string[]
   *   Array containing address or new address
   */
  public function getAddress(string $address_id, $refetch = FALSE): array {
    $selectedCompany = $this->tempStore->get('selected_company');
    $profileContent = $this->getGrantsProfileContent($selectedCompany['business_id'], $refetch);

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
   * Make sure we have needed fields in our profile document.
   *
   * @param string $businessId
   *   Business id for profile data.
   * @param array $profileContent
   *   Profile content.
   *
   * @return array
   *   Profile content with required fields.
   */
  public function initGrantsProfile(string $businessId, array $profileContent): array {
    // @todo see if there's a better way to get readonly parameters from yrtti/ytj than here.
    $assosiationDetails = $this->yjdhClient->getAssociationBasicInfo($businessId);

    // $companyDetails = $this->yjdhClient->getCompany($businessId);
    // $companyDetails == NULL &&
    if (!empty($assosiationDetails)) {
      $profileContent["companyName"] = $assosiationDetails["AssociationNameInfo"][0]["AssociationName"];
      $profileContent["businessId"] = $assosiationDetails["BusinessId"];
      $profileContent["companyStatus"] = $assosiationDetails["AssociationStatus"];
      $profileContent["companyStatusSpecial"] = $assosiationDetails["AssociationSpecialCondition"];
      $profileContent["registrationDate"] = $assosiationDetails["RegistryDate"];
      $profileContent["companyHome"] = $assosiationDetails["Address"][0]["City"];
    }

    if (!isset($profileContent['foundingYear'])) {
      $profileContent['foundingYear'] = NULL;
    }
    if (!isset($profileContent['companyNameShort'])) {
      $profileContent['companyNameShort'] = NULL;
    }
    if (!isset($profileContent['companyHomePage'])) {
      $profileContent['companyHomePage'] = NULL;
    }
    if (!isset($profileContent['companyEmail'])) {
      $profileContent['companyEmail'] = NULL;
    }
    if (!isset($profileContent['businessPurpose'])) {
      $profileContent['businessPurpose'] = NULL;
    }

    if (!isset($profileContent['addresses'])) {
      $profileContent['addresses'] = [];
    }
    if (!isset($profileContent['officials'])) {
      $profileContent['officials'] = [];
    }
    if (!isset($profileContent['bankAccounts'])) {
      $profileContent['bankAccounts'] = [];
    }

    return $profileContent;

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
      return $profileData->getContent();
    }

    $profileData = $this->getGrantsProfile($businessId, $refetch);

    return $this->initGrantsProfile($businessId, $profileData->getContent());

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
  public function getGrantsProfileAttachments(string $businessId, bool $refetch = FALSE): array {

    if ($refetch === FALSE && $this->isCached($businessId)) {
      $profileData = $this->getFromCache($businessId);
      return $profileData->getAttachments();
    }
    else {
      $profileData = $this->getGrantsProfile($businessId, $refetch);
    }

    return $profileData->getAttachments();

  }

  /**
   * Get profile Document.
   *
   * @param string $businessId
   *   Business id for profile.
   * @param bool $refetch
   *   Force refetching of the data.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   Profiledata
   */
  public function getGrantsProfile(string $businessId, bool $refetch = FALSE): AtvDocument {
    if ($refetch == FALSE) {
      if ($this->isCached($businessId)) {
        $document = $this->getFromCache($businessId);
        return $document;
      }
    }

    // Get profile document from ATV.
    try {
      $profileDocument = $this->getGrantsProfileFromAtv($businessId);
    }
    catch (AtvDocumentNotFoundException $e) {
      $this->messenger->addStatus($this->t('Grants profile not found for %s, new profile created.', ['%s' => $businessId]));
      $this->logger->info($this->t('Grants profile not found for %s, new profile created.', ['%s' => $businessId]));
      // Initialize new profile.
      $profileDocument = $this->newProfile([]);
    }

    if (!empty($profileDocument)) {
      $this->setToCache($businessId, $profileDocument);
      return $profileDocument;
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
   * @return \Drupal\helfi_atv\AtvDocument|bool
   *   Profile data
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   */
  private function getGrantsProfileFromAtv(string $businessId): AtvDocument|bool {

    $searchParams = [
      'business_id' => $businessId,
      'type' => 'grants_profile',
    ];

    try {
      $searchDocuments = $this->atvService->searchDocuments($searchParams);
    }
    catch (AtvFailedToConnectException | GuzzleException $e) {
      throw new AtvDocumentNotFoundException('Not found');
    }

    if (empty($searchDocuments)) {
      return FALSE;
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
   * @return array|\Drupal\helfi_atv\AtvDocument|null
   *   Data in cache or null
   */
  private function getFromCache(string $key): array|AtvDocument|NULL {
    $retval = !empty($this->tempStore->get($key)) ? $this->tempStore->get($key) : NULL;
    return $retval;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param array|\Drupal\helfi_atv\AtvDocument $data
   *   Cached data.
   *
   * @return bool
   *   Did save succeed?
   */
  private function setToCache(string $key, array|AtvDocument $data): bool {

    try {

      if (gettype($data) == 'object') {
        $this->tempStore->set($key, $data);
        return TRUE;
      }
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
        $grantsProfile->setContent($data);
        $this->tempStore->set($key, $grantsProfile);
        return TRUE;
      }
    }
    catch (TempStoreException $e) {
      return FALSE;
    }
  }

}
