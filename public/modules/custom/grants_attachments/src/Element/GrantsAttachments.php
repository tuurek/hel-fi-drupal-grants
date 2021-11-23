<?php

namespace Drupal\grants_attachments\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * Provides a 'grants_attachments'.
 *
 * Webform composites contain a group of sub-elements.
 *
 * @FormElement("grants_attachments")
 *
 * @see \Drupal\webform\Element\WebformCompositeBase
 * @see \Drupal\grants_attachments\Element\GrantsAttachments
 */
class GrantsAttachments extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return parent::getInfo() + ['#theme' => 'grants_attachments'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element): array {
    $elements = [];
    $elements['attachment'] = [
      '#type' => 'managed_file',
      '#title' => t('Attachment'),
      '#multiple' => FALSE,
      '#uri_scheme' => 'private',
      '#upload_location' => 'private://grants_attachments',
    ];
    $elements['isDeliveredLater'] = [
      '#type' => 'checkbox',
      '#title' => t('Attachment will delivered at later time'),
      '#element_validate' => ['\Drupal\grants_attachments\Element\GrantsAttachments::validateDeliveredLaterCheckbox'],
    ];
    $elements['isIncludedInOtherFile'] = [
      '#type' => 'checkbox',
      '#title' => t('Attachment already delivered'),
      '#element_validate' => ['\Drupal\grants_attachments\Element\GrantsAttachments::validateIncludedOtherFileCheckbox'],
    ];

    return $elements;
  }

  /**
   * Validate Checkbox.
   *
   * @param array $element
   *   Validated element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Form itself.
   */
  public static function validateDeliveredLaterCheckbox(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form) {

    $file = $form_state->getValue([
      $element["#parents"][0],
      'attachment',
    ]);
    $checkboxValue = $form_state->getValue([
      $element["#parents"][0],
      'isDeliveredLater',
    ]);

    if ($file !== NULL && $checkboxValue === '1') {
      $form_state->setError($element, t('You cannot send file and have it delivered later'));
    }

  }

  /**
   * Validate checkbox.
   *
   * @param array $element
   *   Validated element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Form itself.
   */
  public static function validateIncludedOtherFileCheckbox(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form) {

    $file = $form_state->getValue([
      $element["#parents"][0],
      'attachment',
    ]);
    $checkboxValue = $form_state->getValue([
      $element["#parents"][0],
      'isIncludedInOtherFile',
    ]);

    if ($file !== NULL && $checkboxValue === '1') {
      $form_state->setError($element, t('You cannot send file and have it in another file'));
    }
  }

}
