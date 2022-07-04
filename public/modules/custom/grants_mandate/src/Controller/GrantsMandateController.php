<?php

namespace Drupal\grants_mandate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\grants_mandate\GrantsMandateService;
use Drupal\grants_profile\GrantsProfileService;
use Laminas\Diactoros\Response\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for grants_mandate routes.
 *
 * ?code=52ezzTqZYRY3vsG0xxcPO5SZI_T2RWb_SM9eMJspkZsyYosjof_d4OAuppSHzbrI_K1ZQO4og7BemkBFBlegziTGmz3cfriIlqPyhrHaBTrwZpNl-zAFKbG-8ksnhnOk&state=1e132c9b-2407-4f52-b739-cead2a3eb21c.
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
   */
  public function mandateCallbackYpa() {

    $code = $this->requestStack->getMainRequest()->query->get('code');
    $state = $this->requestStack->getMainRequest()->query->get('state');

    $callbackUrl = Url::fromRoute('grants_mandate.callback_ypa', [], ['absolute' => TRUE])
      ->toString();

    $callbackUrl = str_replace('/fi', '', $callbackUrl);
    $callbackUrl = str_replace('/sv', '', $callbackUrl);
    $callbackUrl = str_replace('/ru', '', $callbackUrl);

    if (is_string($code) && $code != '') {
      $this->grantsMandateService->changeCodeToToken($code, '', $callbackUrl);
      $roles = $this->grantsMandateService->getRoles();

      $this->grantsProfileService->setSelectedCompany(reset($roles));
    }
    else {
      throw new \GrantsMandateException("Code Exchange failed");
    }

    return new RedirectResponse('/grants-profile');
  }

  /**
   *
   */
  public function mandateCallbackHpa() {
    $d = 'asdf';
  }

  /**
   *
   */
  public function mandateCallbackHpaList() {
    $d = 'asdf';
  }

}
