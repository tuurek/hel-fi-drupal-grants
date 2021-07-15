<?php

namespace Drupal\grants_webform_summation_field\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElementBase;

/**
 * Provides a 'grants_webform_summation_field' element.
 *
 * @WebformElement(
 *   id = "grants_webform_summation_field",
 *   label = @Translation("Grants Webform Summation Field"),
 *   description = @Translation("Provide a webform summation field field for grants applications."),
 *   category = @Translation("Advanced elements"),
 * )
 */
class GrantsWebformSummationField extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {

    return parent::getDefaultProperties() + [
      'collect_field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Get webform object.
    $webform_obj = $form_state->getFormObject()->getWebform();
    $webform_field = $webform_obj->getElementsInitializedFlattenedAndHasValue();
    $collect_field = [];

    // Collect Field.
    foreach ($webform_field as $field_key => $field_detail) {
      if ($field_detail['#type'] == 'grants_webform_summation_field') {
        continue;
      }

      $collect_field[$field_key] = $field_detail['#title'];
    }

    $form['grants_webform_summation_field'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('webform summation field settings'),
    ];

    $form['grants_webform_summation_field']['collect_field'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Collect Fields'),
      '#options' => $collect_field,
      '#description' => $this->t('Which fields should be collected.'),
    ];

    return $form;
  }

}
