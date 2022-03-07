<?php

namespace Drupal\grants_attachments\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\grants_handler\Plugin\WebformHandler\GrantsHandler;
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
  protected function defineDefaultProperties() {
    // Here you define your webform element's default properties,
    // which can be inherited.
    //
    // @see \Drupal\webform\Plugin\WebformElementBase::defaultProperties
    // @see \Drupal\webform\Plugin\WebformElementBase::defaultBaseProperties
    return [
      'multiple' => '',
      'size' => '',
      'minlength' => '',
      'maxlength' => '',
      'placeholder' => '',
      'filetype' => '',
    ] + parent::defineDefaultProperties();
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
    $form['element']['filetype'] = [
      '#type' => 'select',
      '#title' => $this->t('Attachment filetype'),
      '#options' => [
        0 => 'Muu hakemusliite',
        1 => 'Toimintasuunnitelma (Vuodelle, jota hakemus koskee)',
        2 => 'Talousarvio (Vuodelle, jota hakemus koskee)',
        3 => 'Vahvistettu tuloslaskelma ja tase (edelliseltä tilikaudelta)',
        4 => 'Toimintakertomus (edelliseltä tilikaudelta)',
        5 => 'Tilintarkastuskertomus / toiminnantarkastuskertomus edelliseltä tilikaudelta allekirjoitettuna',
        6 => 'Pankin ilmoitus tilinomistajasta tai tiliotekopio (uusilta hakija tai pankkiyhteystiedot muuttuneet)',
        7 => 'Yhteisön säännöt (uusi hakija tai säännöt muuttuneet)',
        8 => 'Vuosikokouksen pöytäkirja allekirjoitettuna',
        10 => 'Vuokrasopimus (haettaessa vuokra-avustusta)',
        12 => 'Yhdistykseen kuuluvat paikallisosastot',
        13 => 'Ote yhdistysrekisteristä (uudet seurat)',
        14 => 'Ammattilaisproduktioilta työryhmän jäsenten ansioluettelot',
        15 => 'Kopio vuokrasopimuksesta (uusi hakija tai muuttunut sopimus)',
        16 => 'Laskukopiot (ajalta, jolta kompensaatiota haetaan)',
        17 => 'Myyntireskontra',
        19 => 'Projektisuunnitelma',
        22 => 'Talousarvio',
        23 => 'Arviointisuunnitelma',
        25 => 'Toimintaryhmien yhteystiedot-lomake / nuorten toimintaryhmät',
        26 => 'Rekisteriote',
        28 => 'Talousarvio ja toimintasuunnitelma',
        29 => 'Suunnistuskartat, joille avustusta haetaan',
        30 => 'Karttojen valmistukseen liittyvät laskut ja kuitit',
        31 => 'Kuitit kuljetuskustannuksista',
        32 => 'Ennakkotiedot leireistä (Excel liite)',
        36 => 'Tiedot toteutuneista leireistä (excel-liite)',
        37 => 'Tilankäyttöliite',
        38 => 'Tapahtumasuunnitelma',
        39 => 'Palvelusuunnitelma',
        40 => 'Hankesuunnitelma',
        41 => 'Selvitys avustuksen käytöstä',
        42 => 'Seuran toimintatiedot',
        43 => 'Tilinpäätös',
        44 => 'Hakemusliite',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function getValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {

    if (!isset($element['#webform_key']) && isset($element['#value'])) {
      return $element['#value'];
    }

    $webform_key = $element['#title'];

    $data = $webform_submission->getData();
    $value = NULL;

    if (isset($data['attachments'])) {
      foreach ($data['attachments'] as $item) {
        if ($item['description'] == $webform_key) {
          $value = $item;
        }
      }
    }
    else {
      foreach (GrantsHandler::getAttachmentFieldNames() as $fieldName) {
        if (!isset($data[$fieldName])) {
          continue;
        }
        $fieldData = $data[$fieldName];

        // $element["#webform_parents"][2]
        if (in_array($fieldName, $element["#webform_parents"])) {
          $value = $fieldData;
          // If (isset($fieldData['description'])) {
          // $value = $fieldData;
          // //          if ($fieldData['description'] == $webform_key) {
          // //            $value = $fieldData;
          // //            break;
          // //          }
          // }
          // elseif ($fieldName == 'muu_liite') {
          // $d = 'asdf';
          // //          // Muu liite.
          // //          $value = [];
          // //          foreach ($data[$fieldName] as $key => $fieldValue) {
          // //            if (is_numeric($key)) {
          // //              $value[] = $fieldValue;
          // //            }
          // //          }
          // }.
        }

      }
    }

    // Is value is NULL and there is a #default_value, then use it.
    if ($value === NULL && isset($element['#default_value'])) {
      $value = $element['#default_value'];
    }

    // Return multiple (delta) value or composite (composite_key) value.
    if (is_array($value)) {
      // Return $options['delta'] which is used by tokens.
      // @see _webform_token_get_submission_value()
      if (isset($options['delta'])) {
        $value = $value[$options['delta']] ?? NULL;
      }

      // Return $options['composite_key'] which is used by composite elements.
      // @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase::formatTableColumn
      if ($value && isset($options['composite_key'])) {
        $value = $value[$options['composite_key']] ?? NULL;
      }
    }

    return $value;

  }

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

    if (!is_array($value)) {
      return [];
    }

    if (isset($value['attachment']) && $value['attachment'] !== NULL) {
      // Load file.
      /** @var \Drupal\file\FileInterface|null $file */
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($value['attachment']);

      $lines[] = ($file !== NULL) ? $file->get('filename')->value : '';
    }
    if ($value["isDeliveredLater"] === '1') {
      $lines[] = $element["#webform_composite_elements"]["isDeliveredLater"]["#title"]->render();
    }
    if ($value["isIncludedInOtherFile"] === '1') {
      $lines[] = $element["#webform_composite_elements"]["isIncludedInOtherFile"]["#title"]->render();
    }
    if ($value["description"] !== NULL) {
      $lines[] = $value["description"];
    }

    return $lines;
  }

}
