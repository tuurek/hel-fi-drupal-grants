<?php

namespace Drupal\grants_handler\Plugin\WebformElement;

use Drupal\grants_profile\Form\ModalApplicationOfficialForm;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'community_officials_composite' element.
 *
 * @WebformElement(
 *   id = "community_officials_composite",
 *   label = @Translation("Community officials composite"),
 *   description = @Translation("Provides a address element for company."),
 *   category = @Translation("Helfi"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 *
 * @see \Drupal\grants_handler\Element\CommunityOfficialsComposite
 * @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class CommunityOfficialsComposite extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []): array|string {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []): array {
    $roles = ModalApplicationOfficialForm::getOfficialRoles();
    $value = $this->getValue($element, $webform_submission, $options);

    /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $role */
    $role = $roles[(int) $value['role']];

    return [
      $value['name'],
      $role->render(),
      $value['email'],
      $value['phone'],
    ];
  }

}
