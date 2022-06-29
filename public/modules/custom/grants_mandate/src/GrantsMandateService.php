<?php

namespace Drupal\grants_mandate;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\Url;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * GrantsMandateAuthorize service.
 */
class GrantsMandateService {

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
   * Private store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Profile data.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  /**
   * Construct the service object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   Store for session id.
   */
  public function __construct(
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    ClientInterface $httpClient,
    LoggerChannelFactory $loggerFactory,
    PrivateTempStoreFactory $temp_store_factory
  ) {
    $this->httpClient = $httpClient;
    $this->logger = $loggerFactory->get('grants_mandate');
    $this->tempStore = $temp_store_factory->get('grants_mandate');
    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;

    $this->clientId = getenv('DVV_WEBAPI_CLIENT_ID');
    $this->clientSecret = getenv('DVV_WEBAPI_CLIENT_SECRET');
    $this->apiKey = getenv('DVV_WEBAPI_APIKEY');
    $this->webApiUrl = getenv('DVV_WEBAPI_URL');

  }

  /**
   * @param string $mode
   *
   * @return string
   * @throws \GrantsMandateException
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
   * Exchange session tokens to auth token.
   *
   * @param string $webApiSessionId
   * @param string $code
   * @param string $issue
   * @param string $callbackUri
   */
  public function changeCodeToToken(string $code, string $issue, string $callbackUri) {

    $url = $this->webApiUrl . '/oauth/token?code=' . $code . '&grant_type=authorization_code&redirect_uri=' . $callbackUri;

    $options = [
      'headers' => [
        'Authorization' => $this->createBasicAuthorizationHeader(),
      ],
    ];

    try {
      $response = $this->httpClient->request(
        'POST',
        $url,
        $options
      );
      // Get existing session details.
      $sessionData = $this->getSessionData();
      // Parse content.
      $content = Json::decode($response->getBody()->getContents());
      // Merge session data.
      $sessionData = array_merge($sessionData, $content);
      // Save updated session data.
      $this->setSessionData($sessionData);
    }
    catch (\Exception $e) {
      throw new \GrantsMandateException('Token exchange failed');
    }
  }

  /**
   *
   */
  public function getRoles() {
    // Get existing session details.
    $sessionData = $this->getSessionData();

    $resourceUrl = '/service/' . $sessionData['mode'] . '/api/organizationRoles/' . $sessionData['sessionId'] . '?requestId=' . $sessionData['requestId'];
    $checksumHeaderValue = $this->createxAuthorizationHeader($resourceUrl);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $sessionData['access_token'],
        'X-AsiointivaltuudetAuthorization' => $checksumHeaderValue,
      ],
    ];

    try {
      $response = $this->httpClient->request(
        'GET',
        $this->webApiUrl . $resourceUrl,
        $options
      );

      // Parse content.
      $content = Json::decode($response->getBody()->getContents());

      return $content;

    }
    catch (\Exception $exception) {
      $d = 'asdf';
    }
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
    $registerPath = '/service/' . $mode . '/user/register/' . $this->clientId . '/' . $personId . '?requestId=' . $requestId;
    // . '&endUserId=nodeEndUser';
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

      // Save requestId for later usage.
      $data['requestId'] = $requestId;
      $data['mode'] = $mode;

      $this->setSessionData($data);

      return $data;

    }
    catch (TempStoreException | GuzzleException $e) {
      throw new \GrantsMandateException($e->getMessage());
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

    $dt = new \DateTime();
    $dt->setTimezone(new \DateTimeZone('Europe/Helsinki'));
    // 'Y-m-d\TH:i:s'
    $timestamp = $dt->format('c');

    // Generate hash from path & timestamp.
    $hash = hash_hmac('sha256', $path . ' ' . $timestamp, $this->clientSecret, TRUE);

    // Return formatted string.
    return $this->clientId . ' ' . $timestamp . ' ' . base64_encode($hash);
  }

  /**
   * Create basic auth string from client details.
   *
   * @return string
   */
  private function createBasicAuthorizationHeader() {
    $encoded = base64_encode($this->clientId . ':' . $this->apiKey);
    return 'Basic ' . $encoded;
  }

  /**
   * Save user mandata session data to users' session.
   *
   * @param mixed $data
   *
   * @return void
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setSessionData(mixed $data): void {
    $this->tempStore->set('user_session_data', $data);
  }

  /**
   *
   * @return mixed
   */
  public function getSessionData() {
    return $this->tempStore->get('user_session_data');
  }

}
