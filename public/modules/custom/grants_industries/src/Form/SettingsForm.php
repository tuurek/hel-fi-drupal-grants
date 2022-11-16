<?php

declare(strict_types=1);

namespace Drupal\grants_industries\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Configure grants_industries settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grants_industries_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['grants_industries.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $roles = Role::loadMultiple();

    // Unset unwanted roles.
    unset($roles['anonymous']);
    unset($roles['authenticated']);
    unset($roles['admin']);
    unset($roles['helsinkiprofiili']);
    unset($roles['read_only']);

    $roleOptions = [];
    foreach ($roles as $rolename => $roleObject) {
      $roleOptions[$rolename] = $roleObject->label();
    }

    // Gather the number of names in the form already.
    $num_mappings = $form_state
      ->get('num_mappings');

    // We have to ensure that there is at least one mapping field.
    if ($num_mappings === NULL) {
      $mapping_field = $form_state
        ->set('num_mappings', 1);
      $num_mappings = 1;
    }

    $form['ad_mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Map local roles to AD ones..'),
      '#prefix' => '<div id="mappings-section-wrapper">',
      '#suffix' => '</div>',
      '#open' => TRUE,
    ];

    for ($i = 0; $i < $num_mappings; $i++) {
      $form['ad_mappings']['mappings_fieldset'][$i] = [
        '#type' => 'fieldset',
        '#title' => 'AD <-> Role',
      ];
      $form['ad_mappings']['mappings_fieldset'][$i]['ad_group'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Ad group'),
      ];
      $form['ad_mappings']['mappings_fieldset'][$i]['local_role'] = [
        '#type' => 'select',
        '#title' => $this->t('Role'),
        '#options' => $roleOptions,
      ];
      $form['ad_mappings']['mappings_fieldset'][$i]['item_index_' . $i] = [
        '#type' => 'hidden',
        '#value' => $i,
      ];

      // If there is more than one name, add the remove button.
      if ($num_mappings > 1) {
        $form['ad_mappings']['mappings_fieldset'][$i]['remove_mapping_' . $i] = [
          '#type' => 'submit',
          '#value' => $this
            ->t('Remove this'),
          '#submit' => [
            '::removeCallback',
          ],
          '#ajax' => [
            'callback' => '::addmoreCallback',
            'wrapper' => 'mappings-section-wrapper',
          ],
        ];
      }

    }

    $form['ad_mappings']['mappings_fieldset']['add_mapping'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Add one more'),
      '#submit' => [
        '::addOne',
      ],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'mappings-section-wrapper',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['ad_mappings'];
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement();
    $selectorExplode = explode('-', $triggeringElement["#attributes"]["data-drupal-selector"]);
    $selector = (int) array_pop($selectorExplode);

    unset($form["ad_mappings"]["mappings_fieldset"][$selector]);

    $num_mappings = $form_state
      ->get('num_mappings');
    if ($num_mappings > 1) {
      $remove_button = $num_mappings - 1;
      $form_state
        ->set('num_mappings', $remove_button);
    }

    // Since our buildForm() method relies on the value of 'num_names' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state
      ->setRebuild();
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $num_mappings = $form_state
      ->get('num_mappings');
    $add_button = $num_mappings + 1;
    $form_state
      ->set('num_mappings', $add_button);

    // Since our buildForm() method relies on the value of 'num_names' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state
      ->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    $this->config('grants_industries.settings')
      ->set('example', $form_state->getValue('example'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
