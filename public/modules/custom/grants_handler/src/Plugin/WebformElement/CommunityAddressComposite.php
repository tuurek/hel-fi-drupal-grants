<?php

namespace Drupal\grants_handler\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'community_address_composite' element.
 *
 * @WebformElement(
 *   id = "community_address_composite",
 *   label = @Translation("Community address composite"),
 *   description = @Translation("Provides a address element for company."),
 *   category = @Translation("Helfi"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 *
 * @see \Drupal\grants_handler\Element\CommunityAddressComposite
 * @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class CommunityAddressComposite extends WebformCompositeBase {

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
    // $lines[] = $value['community_street'];
    $lines[] = ($value['community_street'] ?? '') . ' ' .
      ($value['community_post_code'] ?? '') . ' ' . ($value['community_city'] ?? '') . ' ' .
      ($value['community_country'] ?? '');
    return $lines;
  }

}
