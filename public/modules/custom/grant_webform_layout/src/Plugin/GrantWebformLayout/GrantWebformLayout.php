<?php

namespace Drupal\grant_webform_layout\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\ContainerBase;

/**
 * Provides a 'grant_webform_layout' element.
 *
 * @WebformElement(
 *   id = "grant_webform_layout",
 *   label = @Translation("Webform example element"),
 *   description = @Translation("Provides a webform element example."),
 *   category = @Translation("Example elements"),
 * )
 *
 * @see \Drupal\grant_webform_layout\Element\GrantWebformLayout
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class GrantWebformLayout extends ContainerBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    return [
      // Flexbox.
      'title' => 'grant_webform_layout',
    ] + parent::defineDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    // Here you can customize the webform element's properties.
    // You can also customize the form/render element's properties via the
    // FormElement.
    //
    // @see \Drupal\grant_webform_layout\Element\GrantWebformLayout::processWebformElementExample
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
    $form['grant_webform_layout'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Container Settings'),
    ];
    $form['grant_webform_layout']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Section Title'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
