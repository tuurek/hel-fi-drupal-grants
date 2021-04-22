<?php

namespace Drupal\webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'webform_example_element' element.
 *
 * @WebformElement(
 *   id = "webform_example_element",
 *   label = @Translation("Webform example element"),
 *   description = @Translation("Provides a webform element example."),
 *   category = @Translation("Example elements"),
 * )
 *
 * @see \Drupal\webform_example_element\Element\HelfiGrantsWebformLayout
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class HelfiGrantsWebformLayout extends Container {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    return [
      // Flexbox.
      'title' => '',
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
    // @see \Drupal\webform_example_element\Element\HelfiGrantsWebformLayout::processWebformElementExample
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
    $form['webformlayoutcontainer'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Container Settings'),
    ];
    $form['webformlayoutcontainer']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Section Title'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
