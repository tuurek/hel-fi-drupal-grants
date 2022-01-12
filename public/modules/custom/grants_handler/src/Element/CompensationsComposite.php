<?php

namespace Drupal\grants_handler\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\grants_handler\Plugin\WebformElement\CompensationsComposite as CompensationsCompositeElement;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * Compensations webform component.
 *
 * @FormElement("grants_compensations")
 *
 * @see \Drupal\webform\Element\WebformCompositeBase
 * @see \Drupal\grants_handler\Element\CompensationsComposite
 */
class CompensationsComposite extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return parent::getInfo() + ['#theme' => 'compensation_composite'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element): array {
    $elements = [];

    $elements['subvention_type_id'] = [
      '#type' => 'hidden',
    ];
    $elements['subvention_type'] = [
      '#type' => 'textfield',
      '#title' => t('Subvention type'),
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $elements['subvention_amount'] = [
      '#type' => 'textfield',
      '#title' => t('Subvention amount'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    $parent = parent::valueCallback($element, $input, $form_state);

    $retval = [
      'subvention_type_id' => '',
      'subvention_type' => '',
      'subvention_amount' => '',
    ];

    $typeOptions = CompensationsCompositeElement::getOptionsForTypes();

    if (isset($parent['subventionType']) && $parent['subventionType'] != "") {
      $retval['subvention_type_id'] = $parent['subventionType'];
      $retval['subvention_amount'] = $parent['amount'];
      $retval['subvention_type'] = $typeOptions[$parent['subventionType']];

      return $retval;
    }
    return $parent;
  }

  /**
   * Processes a composite webform element.
   */
  public static function processWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    return $element;
  }

}
