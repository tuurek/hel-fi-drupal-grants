<?php

namespace Drupal\grants_webform_print\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;
use Twig\Error\RuntimeError;

/**
 * Returns responses for Webform Printify routes.
 */
class GrantsWebformPrintController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build(Webform $webform) {

    $webformArray = $webform->getElementsDecoded();
//    array_walk_recursive($webformArray, [$this, 'formatWebformElement']);
    $webformArray = $this->traverseWebform($webformArray);

    unset($webformArray['actions']);

    // Webform.
    return [
      '#theme' => 'grants_webform_print_webform',
      '#webform' => $webformArray
    ];
  }

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
   * @param $element
   *  Element to fix
   * @param $key
   *  Key on the form
   */
  private function fixWebformElement(&$element, $key) {

    unset($element["#states"]);

    if (isset($element['#element'])) {
      $elements = $element['#element'];
      unset($element['#element']);
      $element = [
        ...$element,
        ...$elements,
      ];
    }

    $children = array_filter(array_keys($element), function ($key) {
      return !str_contains($key, '#');
    });

    if ($children) {
      foreach ($children as $childKey) {
        $this->fixWebformElement($element[$childKey], $childKey);
      }
    }
    elseif (isset($element['#element'])) {
      $children2 = array_keys($element['#element']);
      foreach ($children2 as $childKey) {
        $this->fixWebformElement($element['#element'][$childKey], $childKey);
      }
    }

    // custom component
//    if (isset($element['#element'])) {
//      foreach ($element['#element'] as $childKey => &$childElement) {
//        $this->fixWebformElement($childElement, $childKey);
//      }
//    }

    $element['#id'] = $key;
//    $element['#description_display'] = [];


    if ($key == 'haettu_avustus_tieto') {
      $d = 'asdf';
    }

    if (isset($element['#type'])) {
      if ($element['#type'] === 'webform_wizard_page') {
        $element['#type'] = 'container';
      }
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
      if ($element['#type'] === 'radios' ) {
        $element['#type'] = 'checkboxes';
      }
      if ($element['#type'] === 'grants_attachments' ) {
        $d = 'asdf';
        $element['#type'] = 'textfield';
//        $element['#markup'] = $element["#description"];
      }
      if ($element['#type'] === 'textarea' || $element['#type'] === 'textfield') {
        $element['#value'] = '';
      }
      if ($element['#type'] === 'checkboxes' || $element['#type'] === 'radios') {
        $element['#value'] = '';
      }



    }


    if ($element['#type'] === 'checkboxes' ) {
      $element['#title_display'] = [];
    }

  }
}
