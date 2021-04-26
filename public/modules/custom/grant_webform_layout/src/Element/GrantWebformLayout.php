<?php

namespace Drupal\grant_webform_layout\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Container;

/**
 * Provides a render element for webform flexbox.
 *
 * @FormElement("grant_webform_layout")
 */
class GrantWebformLayout extends Container {

  /**
   * {@inheritdoc}
   */
  public static function processContainer(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processContainer($element, $form_state, $complete_form);
    $element['#attributes']['class'][] = 'webform-layoutcontainer';
    $element['#attributes']['class'][] = 'js-webform-layoutcontainer';
    if (isset($element['#align'])) {
      $element['#attributes']['class'][] = 'webform-layoutcontainer--' . $element['#align'];
    }
    else {
      $element['#attributes']['class'][] = 'webform-layoutcontainer--equal';
    }
    $element['#attached']['library'][] = 'grant_webform_layout/webform.element.grant_webform_layout';
    return $element;
  }

}
