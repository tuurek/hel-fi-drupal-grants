<?php

namespace Drupal\grants_handler\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * Provides a 'community_address_composite'.
 *
 * Webform composites contain a group of sub-elements.
 *
 *
 * IMPORTANT:
 * Webform composite can not contain multiple value elements (i.e. checkboxes)
 * or composites (i.e. community_address_composite)
 *
 * @FormElement("community_address_composite")
 *
 * @see \Drupal\webform\Element\WebformCompositeBase
 * @see \Drupal\grants_handler\Element\WebformExampleComposite
 */
class CommunityAddressComposite extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return parent::getInfo() + ['#theme' => 'community_address_composite'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element): array {
    $elements = [];

    $elements['community_address_select'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => t('Select address'),
      '#after_build' => [[get_called_class(), 'buildAddressOptions']],
      '#options' => [],
    ];

    $elements['community_street'] = [
      '#type' => 'hidden',
      '#title' => t('Street address'),
    ];
    $elements['community_post_code'] = [
      '#type' => 'hidden',
      '#title' => t('Post code'),
    ];
    $elements['community_city'] = [
      '#type' => 'hidden',
      '#title' => t('City'),
    ];
    $elements['community_country'] = [
      '#type' => 'hidden',
      '#title' => t('Country'),
    ];

    return $elements;
  }

  /**
   * Build select option from profile data.
   *
   * The default selection CANNOT be done here.
   *
   * @param array $element
   *   Element to change.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Updated element
   *
   * @see grants_handler.module
   */
  public static function buildAddressOptions(array $element, FormStateInterface $form_state): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $selectedCompany = $grantsProfileService->getSelectedCompany();
    $profileData = $grantsProfileService->getGrantsProfileContent($selectedCompany ?? '');

    $formValues = $form_state->getValues();
    $formSelection = ($formValues['community_address']['community_street'] ?? '') . ', ' .
      ($formValues['community_address']['community_post_code'] ?? '') . ', ' .
      ($formValues['community_address']['community_city'] ?? '');

    $defaultDelta = '0';

    $options = [
      '' => '-' . t('Select address') . '-',
    ];

    if (!isset($profileData['addresses'])) {
      return $element;
    }

    foreach ($profileData['addresses'] as $delta => $address) {
      $deltaString = (string) $delta;
      $optionSelection = $address['street'] . ', ' . $address['postCode'] .
        ', ' . $address['city'];
      $options[$deltaString] = $optionSelection;

      if ($formSelection == $optionSelection) {
        $defaultDelta = $deltaString;
      }
    }

    $element['#options'] = $options;
    $element['#default_value'] = $defaultDelta;

    return $element;

  }

}
