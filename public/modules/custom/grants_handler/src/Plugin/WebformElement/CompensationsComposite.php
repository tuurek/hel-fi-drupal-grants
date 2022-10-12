<?php

namespace Drupal\grants_handler\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'webform_example_composite' element.
 *
 * @WebformElement(
 *   id = "grants_compensations",
 *   label = @Translation("Grants Compensations"),
 *   description = @Translation("Element for compensations element"),
 *   category = @Translation("Grants"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 *
 * @see \Drupal\webform_example_composite\Element\WebformExampleComposite
 * @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class CompensationsComposite extends WebformCompositeBase {

  /**
   * Compensation types.
   *
   * @var string[]
   */
  protected static $optionsForTypes = [
    1 => 'Toiminta-avustus',
    6 => 'Yleisavustus',
  ];

  /**
   * Return options for different compensation types.
   *
   * @return string[]
   *   Compensation types.
   */
  public static function getOptionsForTypes(): array {
    return self::$optionsForTypes;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    // Here you define your webform element's default properties,
    // which can be inherited.
    //
    // @see \Drupal\webform\Plugin\WebformElementBase::defaultProperties
    // @see \Drupal\webform\Plugin\WebformElementBase::defaultBaseProperties
    $parent = parent::defineDefaultProperties();

    return [
      'amount' => '',
      'subventionType' => '',
        // 'subventionTypeName' => '',
    ] + $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $retval = parent::getValue($element, $webform_submission, $options);
    return $retval;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // Here you can define and alter a webform element's properties UI.
    // Form element property visibility and default values are defined via
    // ::defaultProperties.
    //
    // @see \Drupal\webform\Plugin\WebformElementBase::form
    // @see \Drupal\webform\Plugin\WebformElement\TextBase::form
    $form['element']['subventionType'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Subvention type'),
      '#options' => self::$optionsForTypes,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    $types = self::getOptionsForTypes();

    return [
      $types[$value['subventionType']] . ': ' . $value['amount'] . '€',

    ];
  }

}
