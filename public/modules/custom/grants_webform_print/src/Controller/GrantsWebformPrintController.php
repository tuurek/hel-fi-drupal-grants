<?php

namespace Drupal\grants_webform_print\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Undocumented class.
 */
class GrantsWebformPrintController extends ControllerBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The webform message manager.
   *
   * @var \Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager;

  /**
   * The webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->renderer = $container->get('renderer');
    $instance->messageManager = $container->get('webform.message_manager');
    $instance->requestHandler = $container->get('webform.request');
    return $instance;
  }

  /**
   * Does the transformations for the Element array of the form.
   *
   * @param mixed $item
   *   The reference to the item of the key-item pair.
   * @param string $key
   *   The key associated with the item above.
   */
  private function formatWebformElement(&$item, string $key) {
    if ($key === '#type' && $item === 'webform_wizard_page') {
      $item = 'container';
    }
    elseif ($key === '#type' && $item === 'select') {
      $item = 'checkboxes';
    }
    elseif ($key === '#type' && $item === 'radios') {
      $item = 'checkboxes';
    }
    if (!str_contains($key, '#')) {
    }
  }

  /**
   * Traverse through a webform to make changes to fit the print styles.
   *
   * @param array $webformArray
   *   The Webform in question.
   */
  private function traverseWebform(array &$webformArray) {
    foreach ($webformArray as $key => &$item) {
      if (is_array($item)) {
        $this->traverseWebform($item);
        if (isset($item['#help'])) {
          if (!isset($item['#description'])) {
            $item['#description'] = '';
          }
          $item['#description'] = $item['#help'] . "\n" . $item['#description'];
          $item['#help'] = NULL;
        }
        if (isset($item['#type'])) {
          if ($item['#type'] === 'textarea' || $item['#type'] === 'textfield') {
            $item['#value'] = '';
          }
          if ($item['#type'] === 'checkboxes' || $item['#type'] === 'radios') {
            $item['#value'] = '';
          }
        }
      }
    }
  }

  /**
   * Returns a webform to be printed.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param string|null $library
   *   The iframe JavaScript library.
   * @param string|null $version
   *   The iframe JavaScript library version.
   *
   * @return array
   *   The webform rendered in a page template with only the content.
   *
   * @see page--grants-webform-print.html.twig
   */
  public function page(Request $request, $library = NULL, $version = NULL) {
    $webform = $this->requestHandler->getCurrentWebform();
    $sourceEntity = $this->requestHandler->getCurrentSourceEntity(['webform']);
    $webformArray = $webform->getElementsDecoded();
    array_walk_recursive($webformArray, [$this, 'formatWebformElement']);
    $this->traverseWebform($webformArray);

    // Create a webform.
    $webform->setElements($webformArray);
    $build = [
      'webform' => [
        '#type' => 'webform',
        '#webform' => $webform,
        '#source_entity' => $sourceEntity,
        '#prefix' => '<div class="webform-print-submission-form">',
        '#suffix' => '</div>',
      ],
    ];
    // Webform.
    return $build;

  }

}
