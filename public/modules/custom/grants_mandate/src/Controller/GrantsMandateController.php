<?php

namespace Drupal\grants_mandate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\grants_mandate\GrantsMandateService;
use Drupal\grants_profile\GrantsProfileService;
use Laminas\Diactoros\Response\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\grants_mandate\GrantsMandateException;

/**
 * Returns responses for grants_mandate routes.
 */
class GrantsMandateController extends ControllerBase implements ContainerInjectionInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The request stack used to access request globals.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Mandate service.
   *
   * @var \Drupal\grants_mandate\GrantsMandateService
   */
  protected GrantsMandateService $grantsMandateService;


  /**
   * Access to profile data.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Logger access.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * CompanyController constructor.
   */
  public function __construct(
    RequestStack $requestStack,
    AccountProxyInterface $current_user,
    LanguageManagerInterface $language_manager,
    GrantsMandateService $grantsMandateService,
    GrantsProfileService $grantsProfileService
  ) {
    $this->requestStack = $requestStack;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->grantsMandateService = $grantsMandateService;
    $this->grantsProfileService = $grantsProfileService;
    $this->logger = $this->getLogger('grants_mandate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GrantsMandateController|static {
    return new static(
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('grants_mandate.service'),
      $container->get('grants_profile.service'),
    );
  }

  /**
   * Callback for YPA service in DVV valtuutuspalvelu.
   *
   * @return \Laminas\Diactoros\Response\RedirectResponse
   *   REdirect to profile page.
   *
   * @throws \GrantsMandateException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function mandateCallbackYpa() {

    $code = $this->requestStack->getMainRequest()->query->get('code');
    $state = $this->requestStack->getMainRequest()->query->get('state');

    $callbackUrl = Url::fromRoute('grants_mandate.callback_ypa', [], ['absolute' => TRUE])
      ->toString();

    // @todo find some way to remove language part from routes / urls.
    $callbackUrl = str_replace('/fi', '', $callbackUrl);
    $callbackUrl = str_replace('/sv', '', $callbackUrl);
    $callbackUrl = str_replace('/ru', '', $callbackUrl);

    if (is_string($code) && $code != '') {
      $this->grantsMandateService->changeCodeToToken($code, $callbackUrl);
      $roles = $this->grantsMandateService->getRoles();

      $this->grantsProfileService->setSelectedCompany(reset($roles));
    }
    else {

      $error = $this->requestStack->getMainRequest()->query->get('error');
      $error_description = $this->requestStack->getMainRequest()->query->get('error_description');
      $error_uri = $this->requestStack->getMainRequest()->query->get('error_uri');

      $msg = $this->t('Code exchange error. @error: @error_desc. State: @state, Error URI: @error_uri',
        [
          '@error' => $error,
          '@error_description' => $error_description,
          '@state' => $state,
          '@error_uri' => $error_uri,
        ]);

      $this->logger->error($msg->render());

      throw new GrantsMandateException("Code Exchange failed, state: " . $state);
    }

    // Redirect user to grants profile page.
    $redirectUrl = Url::fromRoute('grants_profile.show');
    return new RedirectResponse($redirectUrl->toString());
  }

  /**
   * Callback for user mandates.
   */
  public function mandateCallbackHpa() {
    $d = 'asdf';
  }

  /**
   * Callback for hpa listing.
   */
  public function mandateCallbackHpaList() {
    $d = 'asdf';
  }

}
