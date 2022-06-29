<?php

namespace Drupal\grants_mandate;

use DateTime;
use DateTimeZone;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\Url;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use GrantsMandateException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;

/**
 * GrantsMandateAuthorize service.
 */
class GrantsMandateService {

  /**
   * The helfi_helsinki_profiili service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  /**
   * The grants_profile service.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Client id.
   *
   * @var string
   */
  protected string $clientId;

  /**
   * Client secret.
   *
   * @var string
   */
  protected string $clientSecret;

  /**
   * ApiKey.
   *
   * @var string
   */
  protected string $apiKey;

  /**
   * Web Api url.
   *
   * @var string
   */
  protected string $webApiUrl;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory|\Drupal\Core\Logger\LoggerChannelInterface|\Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannelFactory|LoggerChannelInterface|LoggerChannel $logger;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Construct the service object.
   *
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *   Access to helsinkiprofiili data.
   * @param \Drupal\grants_profile\GrantsProfileService $grantsProfileService
   *   Access to grants profile.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger.
   */
  public function __construct(
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    GrantsProfileService $grantsProfileService,
    ClientInterface $httpClient,
    LoggerChannelFactory $loggerFactory,
    PrivateTempStoreFactory $temp_store_factory
  ) {
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;
    $this->grantsProfileService = $grantsProfileService;
    $this->httpClient = $httpClient;
    $this->logger = $loggerFactory->get('grants_mandate');
    $this->tempStore = $temp_store_factory->get('grants_mandate');

    $this->clientId = getenv('DVV_WEBAPI_CLIENT_ID');
    $this->clientSecret = getenv('DVV_WEBAPI_CLIENT_SECRET');
    $this->apiKey = getenv('DVV_WEBAPI_APIKEY');
    $this->webApiUrl = getenv('DVV_WEBAPI_URL');

  }

  /**
   *
   */
  public function getUserMandateRedirectUrl(string $mode) {

    $userData = $this->helsinkiProfiiliUserData->getUserData();
    $userProfile = $this->helsinkiProfiiliUserData->getUserProfileData();
    $personId = $userProfile["myProfile"]["verifiedPersonalInformation"]["nationalIdentificationNumber"];
    $requestId = $this->getRequestId();

    $sessionData = $this->register($mode, $personId, $requestId);

    $callbackUrl = Url::fromRoute('grants_mandate.callback_' . $mode, [], ['absolute' => TRUE]);

    $url = $callbackUrl->toString();

    $url = str_replace('/fi', '', $url);
    $url = str_replace('/sv', '', $url);
    $url = str_replace('/ru', '', $url);

    return $this->webApiUrl . '/oauth/authorize?client_id=' . $this->clientId . '&response_type=code&redirect_uri=' . $url . '&user=' . $sessionData['userId'];
  }

  /**
   * Get new request id.
   *
   * @return string
   *   Uuid.
   */
  protected function getRequestId(): string {
    return Uuid::uuid4()->toString();
  }

  /**
   * Register user session to mandates WebApi.
   *
   * @param string $mode
   *   Mode to be used: ypa, hpa, hpalist supported.
   * @param string $personId
   *   Personal identification from auth service.
   * @param string $requestId
   *   Id.
   *
   * @throws \GrantsMandateException
   */
  protected function register(string $mode, string $personId, string $requestId): mixed {

    // Registering WEB API session.
    $registerPath = '/service/' . $mode . '/user/register/' . $this->clientId . '/' . $personId . '?requestId=' . $requestId . '&endUserId=nodeEndUser';
    // Adding X-AsiointivaltuudetAuthorization header.
    $checksumHeaderValue = $this->createxAuthorizationHeader($registerPath);

    try {
      $response = $this->httpClient->request(
        'GET',
        $this->webApiUrl . $registerPath,
        [
          'headers' => [
            'X-AsiointivaltuudetAuthorization' => $checksumHeaderValue,
          ],
        ]
      );

      $data = Json::decode($response->getBody()->getContents());

      $this->tempStore->set('user_session_data', $data);

      return $data;

    }
    catch (TempStoreException | GuzzleException $e) {
      throw new GrantsMandateException($e->getMessage());
    }

  }

  /**
   * Create authorisation header from client & path details.
   *
   * @param string $path
   *   Url path for calculating hash.
   *
   * @return string
   *   Authorisation header for requests.
   */
  private function createxAuthorizationHeader(string $path): string {

    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('Europe/Helsinki'));
    // 'Y-m-d\TH:i:s'
    $timestamp = $dt->format('c');

    // Generate hash from path & timestamp.
    $hash = hash_hmac('sha256', $path . ' ' . $timestamp, $this->clientSecret, TRUE);

    // Return formatted string.
    return $this->clientId . ' ' . $timestamp . ' ' . base64_encode($hash);
  }

  /**
   *
   */
  private function createBasicAuthorizationHeader() {
    $encoded = base64_encode($this->clientId . ':' . $this->clientSecret);
    return 'Basic ' . $encoded;
  }

}
