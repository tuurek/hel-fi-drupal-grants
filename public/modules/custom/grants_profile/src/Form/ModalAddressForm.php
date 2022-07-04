<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\Core\Url;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\grants_profile\TypedData\Definition\AddressDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class ModalAddressForm extends FormBase {

  /**
   * Drupal\Core\TypedData\TypedDataManager definition.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected TypedDataManager $typedDataManager;

  /**
   * Access to grants profile data.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Constructs a new ModalAddressForm object.
   */
  public function __construct(TypedDataManager $typed_data_manager, GrantsProfileService $grantsProfileService) {
    $this->typedDataManager = $typed_data_manager;
    $this->grantsProfileService = $grantsProfileService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ModalAddressForm|static {

    // Create a new form object and inject its services.
    $form = new static(
      $container->get('typed_data_manager'),
      $container->get('grants_profile.service')
    );
    $form->setRequestStack($container->get('request_stack'));
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grants_profile_modal_address_form';
  }

  /**
   * Helper method so we can have consistent dialog options.
   *
   * @return string[]
   *   An array of jQuery UI elements to pass on to our dialog form.
   */
  public static function getDataDialogOptions(): array {
    return [
      'width' => '50%',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $address_id = '', string $nojs = ''): array {

    // Add the core AJAX library.
    $form['#attached']['library'][] = 'core/drupal.ajax';

    $selectedAddress = $this->grantsProfileService->getAddress($address_id);

    // Add a link to show this form in a modal dialog if we're not already in
    // one.
    if ($nojs == 'nojs') {
      $form['use_ajax_container'] = [
        '#type' => 'details',
        '#open' => TRUE,
      ];
      $form['use_ajax_container']['description'] = [
        '#type' => 'item',
        '#markup' => $this->t('In order to show a modal dialog by clicking on a link, that link has to have class <code>use-ajax</code> and <code>data-dialog-type="modal"</code>. This link has those attributes.'),
      ];
      $form['use_ajax_container']['use_ajax'] = [
        '#type' => 'link',
        '#title' => $this->t('See this form as a modal.'),
        '#url' => Url::fromRoute('form_api_example.modal_form', ['nojs' => 'ajax']),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(static::getDataDialogOptions()),
          // Add this id so that we can test this form.
          'id' => 'addres-modal-form-link',
        ],
      ];
    }

    // This element is responsible for displaying form errors in the AJAX
    // dialog.
    if ($nojs == 'ajax') {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -999,
      ];
    }

    $form['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['street'],
    ];
    $form['postCode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Post code'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['postCode'],
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['city'],
    ];
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
      '#default_value' => $selectedAddress['country'],
    ];
    $form['address_id'] = [
      '#type' => 'hidden',
      '#value' => $address_id,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save address'),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
    ];

    // Set the form to not use AJAX if we're on a nojs path. When this form is
    // within the modal dialog, Drupal will make sure we're using an AJAX path
    // instead of a nojs one.
    if ($nojs == 'nojs') {
      unset($form['actions']['submit']['#ajax']);
    }

    $url = Url::fromRoute(
      'grants_profile.company_addresses.remove',
      ['address_id' => $address_id]
    );
    $form['actions']['delete'] = [
      '#type' => 'link',
      '#title' => t('Delete address'),
      '#attributes' => ['class' => ['button', 'delete']],
      '#url' => $url,
      '#cache' => [
        'contexts' => [
          'url.query_args:destination',
        ],
      ],
      '#weight' => 10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get definition.
    $addressDefinition = AddressDefinition::create('grants_profile_address');
    // Create data object.
    $addressData = $this->typedDataManager->create($addressDefinition);

    // Get & set values from form.
    $tempValues = $form_state->getValues();
    $values = [
      'street' => $tempValues['street'],
      'postCode' => $tempValues['postCode'],
      'city' => $tempValues['city'],
      'country' => $tempValues['country'],
    ];
    try {
      // Set values.
      $addressData->setValue($values);
      // Validate inserted data.
      $violations = $addressData->validate();
      // If there's violations in data.
      if ($violations->count() != 0) {
        foreach ($violations as $violation) {
          // Print errors by form item name.
          $form_state->setErrorByName(
            $violation->getPropertyPath(),
            $violation->getMessage());
        }
      }
      else {
        // Move addressData object to form_state storage.
        $form_state->setStorage(['addressData' => $addressData]);
      }
    }
    catch (ReadOnlyException $e) {
      $this->messenger()->addError('Data read only');
      $form_state->setError($form, 'Trying to write to readonly value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    if (!isset($storage['addressData'])) {
      $this->messenger()->addError($this->t('addressData not found!'));
      return;
    }

    $addressData = $storage['addressData'];
    $addressId = $form_state->getValue('address_id');

    $this->grantsProfileService->saveAddress($addressId, $addressData->toArray());

    $this->grantsProfileService->saveGrantsProfileAtv();

    $this->messenger()->addStatus($this->t('Address has been saved.'));

    $form_state->setRedirect('grants_profile.show');
  }

  /**
   * Implements the submit handler for the modal dialog AJAX call.
   *
   * @param array $form
   *   Render array representing from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Array of AJAX commands to execute on submit of the modal form.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    // We begin building a new ajax reponse.
    $response = new AjaxResponse();

    // If the user submitted the form and there are errors, show them the
    // input dialog again with error messages. Since the title element is
    // required, the empty string wont't validate and there will be an error.
    if ($form_state->getErrors()) {
      // If there are errors, we can show the form again with the errors in
      // the status_messages section.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new OpenModalDialogCommand($this->t('Errors'), $form, static::getDataDialogOptions()));
    }
    // If there are no errors, show the output dialog.
    else {

      $url = Url::fromRoute(
        'grants_profile.show'
      );

      $response->addCommand(new CloseModalDialogCommand());

      $command = new RedirectCommand($url->toString());
      $response->addCommand($command);

    }

    // Finally return our response.
    return $response;
  }

}
