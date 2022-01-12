<?php

namespace Drupal\grants_webform_print\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\grants_webform_print\GrantsWebformPrintHelper;

/**
 * Sets the theme for the webform share page.
 *
 * @see \Drupal\grants_webform_print\Controller\GrantsWebformPrintController::page
 * @see page--webform-share.html.twig
 */
class GrantsWebformPrintThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * The system theme config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a GrantsWebformPrintThemeNegotiator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return GrantsWebformPrintHelper::isPage($route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $route_match->getParameter('webform');
    return $webform->getSetting('share_theme_name', TRUE)
      ?: $this->configFactory->get('system.theme')->get('default');
  }

}
