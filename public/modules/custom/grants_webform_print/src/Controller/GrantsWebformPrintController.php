<?php

declare(strict_types=1);

namespace Drupal\grants_webform_print\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;

/**
 * Returns responses for Webform Printify routes.
 */
class GrantsWebformPrintController extends ControllerBase {

  /**
   * Builds the response.
   *
   * @param \Drupal\webform\Entity\Webform $webform
   *   Webform to print.
   *
   * @return array
   *   Render array.
   */
  public function build(Webform $webform): array {

    $webformArray = $webform->getElementsDecoded();
    // array_walk_recursive($webformArray, [$this, 'formatWebformElement']);.
    $webformArray = $this->traverseWebform($webformArray);

    unset($webformArray['actions']);

    // Webform.
    return [
      '#theme' => 'grants_webform_print_webform',
      '#webform' => $webformArray,
    ];
  }

  /**
   * Page title callback.
   *
   * @param \Drupal\webform\Entity\Webform $webform
   *   Webform to print.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   *   Title to show.
   */
  public function title(Webform $webform) {
    return $webform->label();
  }

  /**
   * Traverse through a webform to make changes to fit the print styles.
   *
   * @param array $webformArray
   *   The Webform in question.
   */
  private function traverseWebform(array $webformArray) {
    foreach ($webformArray as $key => &$item) {
      $this->fixWebformElement($item, $key);
    }
    return $webformArray;
  }

  /**
   * Clean out unwanted things from form elements.
   *
   * @param array $element
   *   Element to fix.
   * @param string $key
   *   Key on the form.
   */
  private function fixWebformElement(array &$element, string $key) {

    // Remove states from printing.
    unset($element["#states"]);

    // In case of custom component, the element parts are in #element
    // so we need to sprad those out for printing.
    if (isset($element['#element'])) {
      $elements = $element['#element'];
      unset($element['#element']);
      $element = [
        ...$element,
        ...$elements,
      ];
    }
    // Look for non render array parts from element.
    $children = array_filter(array_keys($element), function ($key) {
      return !str_contains($key, '#');
    });

    // If there is some, then loop as long as there is som.
    if ($children) {
      foreach ($children as $childKey) {
        $this->fixWebformElement($element[$childKey], $childKey);
      }
    }

    // If no id for the field, we get warnigns.
    $element['#id'] = $key;
    // Force description display after element.
    $element['#description_display'] = 'after';

    // Field type specific alters.
    if (isset($element['#type'])) {
      // Make wizard pages show as containers.
      if ($element['#type'] === 'webform_wizard_page') {
        $element['#type'] = 'container';
      }
      // Custom components as select.
      if ($element['#type'] === 'community_address_composite') {
        $element['#type'] = 'select';
        $element['#options'] = [0 => t('Select address')];
      }
      if ($element['#type'] === 'community_officials_composite') {
        $element['#type'] = 'select';
        $element['#options'] = [0 => t('Select official')];
      }
      if ($element['#type'] === 'bank_account_composite') {
        $element['#type'] = 'select';
        $element['#options'] = [0 => t('Select bank account')];
      }
      // Subventions as hidden textfield.
      if ($element['#type'] === 'grants_compensations') {
        $element['#type'] = 'textfield';
        $element["#attributes"]["class"][] = 'hide-input';
      }
      // Get attachment descriptions from subfields.
      if ($element['#type'] === 'grants_attachments') {
        $element['#type'] = 'textfield';
        $element["#attributes"]["class"][] = 'hide-input';
        $element["#description__access"] = TRUE;
        if (!empty($element["#attachment__description"])) {
          $element['#description'] = $element["#attachment__description"];
        }
      }
      // Show no radios, hidden textfields.
      if ($element['#type'] === 'radios') {
        $element['#type'] = 'textfield';
        $element["#attributes"]["class"][] = 'hide-input';
      }

      if ($element['#type'] === 'textarea' || $element['#type'] === 'textfield') {
        $element['#value'] = '';
      }
      if ($element['#type'] === 'checkboxes' || $element['#type'] === 'radios') {
        $element['#value'] = '';
      }
    }
    if ($element['#type'] === 'checkboxes') {
      $element['#title_display'] = [];
    }
  }

}
