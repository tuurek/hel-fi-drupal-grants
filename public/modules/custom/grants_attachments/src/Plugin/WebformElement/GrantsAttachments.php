<?php

namespace Drupal\grants_attachments\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'grants_attachments' element.
 *
 * @WebformElement(
 *   id = "grants_attachments",
 *   label = @Translation("Grants attachments"),
 *   description = @Translation("Provides a grants attachment element."),
 *   category = @Translation("Hel.fi elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 *
 * @see \Drupal\grants_attachments\Element\GrantsAttachments
 * @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class GrantsAttachments extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []): array {
    $value = $this->getValue($element, $webform_submission, $options);
    $lines = [];

    if ($value['attachment'] !== NULL) {
      // load file
      /** @var \Drupal\file\FileInterface|null $file */
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($value['attachment']);

      $lines[] = ($file !== NULL) ? $file->get('filename')->value : '';
    }

    $lines[] = $value["isDeliveredLater"] === '1' ?
      $element["#webform_composite_elements"]["isDeliveredLater"]["#title"]->render() : NULL;

    $lines[] = $value["isIncludedInOtherFile"] === '1' ?
      $element["#webform_composite_elements"]["isIncludedInOtherFile"]["#title"]->render() : NULL;


    return $lines;
  }

}
