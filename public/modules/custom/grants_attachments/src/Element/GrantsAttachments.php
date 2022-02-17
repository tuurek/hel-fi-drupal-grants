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
  public function getInfo(): array {
    return parent::getInfo() + ['#theme' => 'grants_attachments'];
  }

  // @codingStandardsIgnoreStart
  /**
   * Build webform element based on data in ATV document.
   *
   * @param array $element
   *   Element that is being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Full form.
   *
   * @return array[]
   *   Form API element for webform element.
   */
  public static function processWebformComposite(&$element, FormStateInterface $form_state, &$complete_form): array {
    $element = parent::processWebformComposite($element, $form_state, $complete_form);
    $elementTitle = $element['#title'];

    $submission = $form_state->getFormObject()->getEntity();
    $submissionData = $submission->getData();
    if (isset($submissionData['attachments']) && is_array($submissionData['attachments'])) {

      $dataForElement = $submissionData['attachments'][array_search($elementTitle, array_column($submissionData['attachments'], 'description'))];
      if (isset($dataForElement['isDeliveredLater'])) {
        $element["isDeliveredLater"]["#default_value"] = $dataForElement['isDeliveredLater'] == 'true';
        if ($element["isDeliveredLater"]["#default_value"] == TRUE) {
          $element["fileStatus"]["#value"] = 'deliveredLater';
        }
      }
      if (isset($dataForElement['isIncludedInOtherFile'])) {
        $element["isIncludedInOtherFile"]["#default_value"] = $dataForElement['isIncludedInOtherFile'] == 'true';
        if ($element["isIncludedInOtherFile"]["#default_value"] == TRUE) {
          $element["fileStatus"]["#value"] = 'otherFile';
        }
      }
      if (isset($dataForElement['fileName'])) {
        $element['attachment'] = [
          '#type' => 'textfield',
          '#default_value' => $dataForElement['fileName'],
          '#disabled' => TRUE,
        ];

        $element["isIncludedInOtherFile"]["#disabled"] = TRUE;
        $element["isDeliveredLater"]["#disabled"] = TRUE;
        $element["fileStatus"]["#value"] = 'uploaded';

      }

    }
    return $element;
  }
  // @codingStandardsIgnoreEnd

  /**
   * Form elements for attachments.
   *
   * @todo Use description field always and poplate contents from field title.
   * @todo Allowed file extensions for attachments??
   *
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element): array {
    $elements = [];
    $elements['attachment'] = [
      '#type' => 'managed_file',
      '#title' => t('Attachment'),
      '#multiple' => FALSE,
      '#uri_scheme' => 'private',
      '#file_extensions' => 'doc,docx,gif,jpg,jpeg,pdf,png,ppt,pptx,rtf,txt,xls,xlsx,zip',
      '#upload_validators' => [
        'file_validate_extensions' => 'doc,docx,gif,jpg,jpeg,pdf,png,ppt,pptx,rtf,txt,xls,xlsx,zip',
      ],
      '#upload_location' => 'private://grants_attachments',
      '#sanitize' => TRUE,
    ];
    $elements['description'] = [
      '#type' => 'textfield',
      '#title' => t('Attachment description'),
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
    $elements['fileStatus'] = [
      '#type' => 'hidden',
      '#value' => 'new',
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
    $isDeliveredLaterCheckboxValue = $form_state->getValue([
      $element["#parents"][0],
      'isDeliveredLater',
    ]);

    if ($file !== NULL && $isDeliveredLaterCheckboxValue === '1') {
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
