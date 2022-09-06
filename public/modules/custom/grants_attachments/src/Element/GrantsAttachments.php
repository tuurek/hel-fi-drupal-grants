<?php

namespace Drupal\grants_attachments\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\grants_handler\EventsService;
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

    $element['#tree'] = TRUE;
    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    $submission = $form_state->getFormObject()->getEntity();
    $submissionData = $submission->getData();

    $attachmentEvents = EventsService::filterEvents($submissionData['events'] ?? [], 'INTEGRATION_INFO_ATT_OK');

    if (isset($submissionData[$element['#webform_key']]) && is_array($submissionData[$element['#webform_key']])) {

      $dataForElement = $element['#value'];

      if (isset($dataForElement["fileType"])) {
        $element["fileType"]["#value"] = $dataForElement["fileType"];
      }
      elseif (isset($element["#filetype"])) {
        $element["fileType"]["#value"] = $element["#filetype"];
      }

      if (isset($dataForElement["integrationID"])) {
        $element["integrationID"]["#value"] = $dataForElement["integrationID"];
      }

      if (isset($dataForElement['isDeliveredLater'])) {
        $element["isDeliveredLater"]["#default_value"] = $dataForElement['isDeliveredLater'] == 'true';
        if ($element["isDeliveredLater"]["#default_value"] == TRUE) {
          $element["fileStatus"]["#value"] = 'deliveredLater';
        }
        if ($dataForElement['isDeliveredLater'] == '1') {
          $element["isDeliveredLater"]['#default_value'] = TRUE;
        }
      }
      if (isset($dataForElement['isIncludedInOtherFile'])) {
        $element["isIncludedInOtherFile"]["#default_value"] = ($dataForElement['isIncludedInOtherFile'] == 'true' || $dataForElement['isIncludedInOtherFile'] == '1');
        if ($element["isIncludedInOtherFile"]["#default_value"] == TRUE) {
          $element["fileStatus"]["#value"] = 'otherFile';
        }
      }
      if (isset($dataForElement['fileName'])) {
        $element['attachmentName'] = [
          '#type' => 'textfield',
          '#default_value' => $dataForElement['fileName'],
          '#value' => $dataForElement['fileName'],
          '#readonly' => TRUE,
          '#attributes' => ['readonly' => 'readonly'],
        ];

        $element["isIncludedInOtherFile"]["#disabled"] = TRUE;
        $element["isDeliveredLater"]["#disabled"] = TRUE;

        $element["attachment"]["#access"] = FALSE;
        $element["attachment"]["#readonly"] = TRUE;
        $element["attachment"]["#attributes"] = ['readonly' => 'readonly'];

        if (isset($element["isNewAttachment"])) {
          $element["isNewAttachment"]["#value"] = FALSE;
        }

        $element["fileStatus"]["#value"] = 'uploaded';

        // $element["description"]["#disabled"] = TRUE;
        $element["description"]["#readonly"] = TRUE;
        $element["description"]["#attributes"] = ['readonly' => 'readonly'];
      }
      if (isset($dataForElement['attachmentName']) && $dataForElement['attachmentName'] !== "") {
        $element['attachmentName'] = [
          '#type' => 'textfield',
          '#default_value' => $dataForElement['attachmentName'],
          '#value' => $dataForElement['attachmentName'],
          '#readonly' => TRUE,
          '#attributes' => ['readonly' => 'readonly'],
        ];

        $element["isIncludedInOtherFile"]["#disabled"] = TRUE;
        $element["isDeliveredLater"]["#disabled"] = TRUE;

        $element["attachment"]["#access"] = FALSE;
        $element["attachment"]["#readonly"] = TRUE;
        $element["attachment"]["#attributes"] = ['readonly' => 'readonly'];

        if (isset($element["isNewAttachment"])) {
          $element["isNewAttachment"]["#value"] = FALSE;
        }

        $element["fileStatus"]["#value"] = 'uploaded';

        // $element["description"]["#disabled"] = TRUE;
        $element["description"]["#readonly"] = TRUE;
        $element["description"]["#attributes"] = ['readonly' => 'readonly'];
      }
      if (isset($dataForElement['description'])) {
        $element["description"]["#default_value"] = $dataForElement['description'];
      }

      if (isset($dataForElement['fileType']) && $dataForElement['fileType'] == '6') {
        if (isset($dataForElement['attachmentName']) && $dataForElement['attachmentName'] !== ""){
          $element["fileStatus"]["#value"] = 'uploaded';
        }
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
      // '#value_callback' => [self::class, 'valueMFCallback'],
    ];

    $elements['attachmentName'] = [
      '#type' => 'textfield',
      '#readonly' => TRUE,
      '#attributes' => ['readonly' => 'readonly'],
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
      '#value' => NULL,
    ];
    $elements['fileType'] = [
      '#type' => 'hidden',
      '#value' => NULL,
    ];
    $elements['integrationID'] = [
      '#type' => 'hidden',
      '#value' => NULL,
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
