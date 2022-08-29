<?php

namespace Drupal\grants_handler\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * Provides a 'bank_account_composite'.
 *
 * Webform composites contain a group of sub-elements.
 *
 *
 * IMPORTANT:
 * Webform composite can not contain multiple value elements (i.e. checkboxes)
 * or composites (i.e. bank_account_composite)
 *
 * @FormElement("bank_account_composite")
 *
 * @see \Drupal\webform\Element\WebformCompositeBase
 * @see \Drupal\grants_handler\Element\WebformExampleComposite
 */
class BankAccountComposite extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return parent::getInfo() + ['#theme' => 'bank_account_composite'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element): array {
    $elements = [];

    $elements['account_number_select'] = [
      '#type' => 'select',
      // '#required' => TRUE,
      '#title' => t('Bank account'),
      '#options' => [],
      '#after_build' => [[get_called_class(), 'buildAccountOptions']],
    ];

    $elements['account_number'] = [
      '#type' => 'hidden',
    ];

    return $elements;
  }

  /**
   * Build options for bank account select.
   *
   * @param array $element
   *   Element to add things to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Edited element.
   */
  public static function buildAccountOptions(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $selectedCompany = $grantsProfileService->getSelectedCompany();
    $profileData = $grantsProfileService->getGrantsProfileContent($selectedCompany ?? '');

    $accOoptions = [
      '' => '-' . t('Select account') . '-',
    ];

    if (!isset($profileData["bankAccounts"])) {
      return $element;
    }

    foreach ($profileData["bankAccounts"] as $delta => $account) {
      $accOoptions[$account['bankAccount']] = $account['bankAccount'];
    }

    $element['#options'] = $accOoptions;

    return $element;
  }

}
