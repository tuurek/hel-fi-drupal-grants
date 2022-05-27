<?php

namespace Drupal\grants_handler\Element;

use Drupal\Core\Form\FormStateInterface;
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

    // $elements['subvention_type_id'] = [
    // '#type' => 'hidden',
    // ];
    $elements['subventionTypeTitle'] = [
      '#type' => 'textfield',
      '#title' => t('Subvention name'),
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $elements['subventionType'] = [
      '#type' => 'hidden',
      '#title' => t('Subvention type'),
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $elements['amount'] = [
      '#type' => 'textfield',
      '#title' => t('Subvention amount'),
      '#required' => TRUE,
      '#input_mask' => "'alias': 'currency', 'prefix': '', 'suffix': '€','groupSeparator': ' ','radixPoint':','",
      '#attributes' => ['class' => ['input--borderless']],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    $parent = parent::valueCallback($element, $input, $form_state);

    if (!empty($parent)) {
      return $parent;
    }

    $retval = [
      'subventionType' => '',
      'amount' => '',
    ];

    if (isset($parent['subventionType']) && $parent['subventionType'] != "") {
      // $retval['subvention_type_id'] = $parent['subventionType'];
      $retval['subventionType'] = $parent['subventionType'];
      $retval['amount'] = $parent['amount'];
      // $retval['subventionType'] = $typeOptions[$parent['subventionType']];
    }
    return $retval;
  }

  /**
   * Processes a composite webform element.
   */
  public static function processWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    return $element;
  }

}
