<?php

namespace Drupal\grants_metadata;


use Drupal\Component\Serialization\Json;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManager;

/**
 * AtvSchema service.
 */
class AtvSchema {

  /**
   * Drupal\Core\TypedData\TypedDataManager definition.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected TypedDataManager $typedDataManager;

  protected array $structure;

  protected array $atvDocument;


  /**
   * Constructs an AtvShcema object.
   *
   */
  public function __construct(TypedDataManager $typed_data_manager) {

    $this->typedDataManager = $typed_data_manager;

    $schemaPath = getenv('ATV_SCHEMA_PATH');
    $jsonString = file_get_contents($schemaPath);
    $jsonStructure = Json::decode($jsonString);

    $this->structure = $jsonStructure;

  }


  /**
   */
  public function documentContentToTypedData(
    $documentContent,
    ComplexDataDefinitionInterface $typedDataDefinition):
  TypedDataInterface {

    $propertyDefinitions = $typedDataDefinition->getPropertyDefinitions();

    $typedData = $this->typedDataManager->create($typedDataDefinition);
    $typedDataValues = [];

    foreach ($propertyDefinitions as $definitionKey => $definition) {

      $jsonPath = $definition->getSetting('jsonPath');
      // If json path not configured for item, do nothing.
      if (is_array($jsonPath)) {
        $elementName = array_pop($jsonPath);

        $typedDataValues[$definitionKey] = $this->getValueFromDocument($documentContent, $jsonPath, $elementName);

      }

    }
    $typedData->setValue($typedDataValues);
    return $typedData;

  }

  /**
   * @param $elementName
   * @param $structure
   *
   * @return mixed|void
   */
  protected function getPropertySchema($elementName, $structure) {

    foreach ($structure['properties'] as $topLevelElement) {
      if ($topLevelElement['type'] == 'object') {
        if (array_key_exists($elementName, $topLevelElement['properties'])) {
          return $topLevelElement['properties'][$elementName];
        }
        else {
          foreach ($topLevelElement['properties'] as $key0 => $element0) {
            if ($element0['type'] == 'array') {
              if ($element0['items']['type'] == 'object') {
                if (in_array($elementName, $element0['items']['properties']['ID']['enum'])) {
                  return $element0['items'];
                }
              }
              else {
                if (in_array($elementName, $element0['items']['items']['properties']['ID']['enum'])) {
                  return $element0['items']['items'];
                }
              }
            }
            if ($element0['type'] == 'object') {
              if (array_key_exists($elementName, $element0['properties'])) {
                return $element0['properties'][$elementName];
              }
              else {
                foreach ($element0['properties'] as $k1 => $element1) {
                  if ($element1['type'] == 'array') {
                    if ($element1['items']['type'] == 'object') {
                      if (is_array($element1['items']['properties']['ID']['enum']) && in_array($elementName, $element1['items']['properties']['ID']['enum'])) {
                        return $element1['items'];
                      }
                    }
                    else {
                      if (in_array($elementName, $element1['items']['items']['properties']['ID']['enum'])) {
                        return $element1['items']['items'];
                      }
                    }
                  }
                }
              }
            }
            if ($element0['type'] == 'string') {
              return $element0;
            }
          }
        }
      }
    }
  }

  /**
   * @param \Drupal\Core\TypedData\ComplexDataInterface $typedData
   *
   * @return array
   */
  public function typedDataToDocumentContent(\Drupal\Core\TypedData\ComplexDataInterface $typedData): array {

    $documentStructure = [];

    foreach ($typedData as $property) {
      $item = [];
      $definition = $property->getDataDefinition();
      $value = $property->getValue();
      $propertyName = $property->getName();
      $propertyLabel = $definition->getLabel();
      $propertyType = $definition->getDataType();
      $jsonPath = $definition->getSetting('jsonPath');

      $numberOfItems = count($jsonPath);

      $elementName = array_pop($jsonPath);
      $baseIndex = count($jsonPath);

      $schema = $this->getPropertySchema($elementName, $this->structure);

      if ($propertyType == 'integer') {
        $valueTypeString = 'int';
      }
      elseif ($propertyType == 'datetime_iso8601') {
        $valueTypeString = 'datetime';
      }
      elseif ($propertyType == 'boolean') {
        $valueTypeString = 'bool';
      }
      else {
        $valueTypeString = 'string';
      }

      switch ($numberOfItems) {
        case 4:
          $valueArray = [
            'ID' => $elementName,
            'value' => $value,
            'valueType' => $valueTypeString,
            'label' => $propertyLabel,
          ];
          $documentStructure[$jsonPath[0]][$jsonPath[1]][$jsonPath[2]][] = $valueArray;
          break;
        case 3:
          if (is_array($value) && $this->numeric_keys($value)) {
            foreach ($value as $k2 => $v2) {
              $fvalues = [];
              foreach ($v2 as $fname => $fvalue) {
                $valueArray = [
                  'ID' => $fname,
                  'value' => $fvalue,
                  'valueType' => $valueTypeString,
                  'label' => $propertyLabel,
                ];
                $fvalues[] = $valueArray;
              }
              $documentStructure[$jsonPath[0]][$jsonPath[1]][$elementName][$k2] = $fvalues;
            }
          }
          else {
            if ($schema['type'] == 'number') {
              $documentStructure[$jsonPath[0]][$jsonPath[1]][$elementName] = $value;
            }
            else {
              $valueArray = [
                'ID' => $elementName,
                'value' => $value,
                'valueType' => $valueTypeString,
                'label' => $propertyLabel,
              ];
              $documentStructure[$jsonPath[0]][$jsonPath[1]][] = $valueArray;
            }
          }
          break;
        case 2:
          if (is_array($value) && $this->numeric_keys($value)) {
            if ($propertyType == 'list') {
              foreach ($property as $itemIndex => $item) {
                $fieldValues = [];
                $propertyItem = $item->getValue();
                $itemDataDefinition = $item->getDataDefinition();
                $itemValueDefinitions = $itemDataDefinition->getPropertyDefinitions();
                foreach ($itemValueDefinitions as $itemName => $itemValueDefinition) {
                  $itemValueDefinitionLabel = $itemValueDefinition->getLabel();
                  $itemValueDefinitionType = $itemValueDefinition->getDataType();
                  $itemValue = $propertyItem[$itemName];

                  if ($itemValueDefinitionType == 'integer') {
                    $itemValueDefinitionType = 'int';
                  }
                  elseif ($itemValueDefinitionType == 'datetime_iso8601') {
                    $itemValueDefinitionType = 'datetime';
                  }
                  elseif ($itemValueDefinitionType == 'boolean') {
                    $itemValueDefinitionType = 'bool';
                  }
                  //                  else {
                  //                    $itemValueDefinitionType = 'string';
                  //                  }

                  $valueArray = [
                    'ID' => $itemName,
                    'value' => $itemValue,
                    'valueType' => $itemValueDefinitionType,
                    'label' => $itemValueDefinitionLabel,
                  ];
                  $fieldValues[] = $valueArray;
                }
                $documentStructure[$jsonPath[0]][$elementName][$itemIndex] = $fieldValues;
              }
            }
          }
          else {
            $valueArray = [
              'ID' => $elementName,
              'value' => $value,
              'valueType' => $valueTypeString,
              'label' => $propertyLabel,
            ];
            if ($schema['type'] == 'string') {
              $documentStructure[$jsonPath[$baseIndex - 1]][$elementName] = $value;
            }
            else {
              $documentStructure[$jsonPath[$baseIndex - 1]][] = $valueArray;
            }
          }

          break;
        default:
          $d = 'asdf';
          break;
      }
    }
    return $documentStructure;
  }

  /**
   * Look for numeric keys in array, and return if they're found or not.
   *
   * @param $array
   *  Array to look in
   *
   * @return bool
   *  Is there only numeric keys?
   */
  protected function numeric_keys($array): bool {
    $non_numeric_key_found = FALSE;

    foreach (array_keys($array) as $key) {
      if (!is_numeric($key)) {
        $non_numeric_key_found = TRUE;
      }
    }
    return !$non_numeric_key_found;
  }

  /**
   * Get value from document content for given element / path.
   *
   * @param $content
   *  Decoded JSON content for document
   * @param $pathArray
   *  Path in JSONn document. From definition settings.
   * @param $elementName
   *  ELement name in JSON
   *
   * @return mixed
   *  Parsed typed data structure
   */
  protected function getValueFromDocument($content, $pathArray, $elementName): mixed {
    // Get new key to me evalued
    $newKey = array_shift($pathArray);

    // If key exist in content array
    if (array_key_exists($newKey, $content)) {
      // Get content for key
      $newContent = $content[$newKey];
      // And since we're not in root element, call self
      // to drill down to desired element
      return $this->getValueFromDocument($newContent, $pathArray, $elementName);
    }
    // If we are at the root of content, and the given element exists
    elseif (array_key_exists($elementName, $content)) {
      // If element is array
      if (is_array($content[$elementName])) {
        $retval = [];
        // we need to loop values and structure data in array as well
        foreach ($content[$elementName] as $key => $value) {
          foreach ($value as $v) {
            $retval[$key][$v['ID']] = $v['value'];
          }
        }
        return $retval;
      }
      // If element is not array
      else {
        // return value as is
        return $content[$elementName];
      }
    }
    // If keys are numeric, we know that we need to decode the last
    // item with id's / names in array
    elseif ($this->numeric_keys($content)) {
      // Loop content
      foreach ($content as $value) {
        // if content is not array, it means that content is returnable as is
        if (!is_array($value)) {
          return $value;
        }
        // if value is an array, then we need to return desired element value
        if ($value['ID'] == $elementName) {
          $retval = $value['value'];
          return $retval;
        }
      }
    }
    else {
      // if no item is specified with given name
      return NULL;
    }
    // shouldn't get here that often.
    return NULL;
  }

  /**
   * @return array
   */
  public function getAtvDocumentContent($atvDocument): array {
    if (is_string($atvDocument['content'])) {
      $replaced = str_replace("'", "\"", $atvDocument['content']);
      $replaced = str_replace("False", "false", $replaced);

      return Json::decode($replaced);
    }
    return $atvDocument['content'];
  }

  /**
   * @param array $atvDocument
   */
  public function setAtvDocumentContent(array $atvDocument, array $atvDocumentContent): array {
    $atvDocument['content'] = $atvDocumentContent;
    return $atvDocument;
  }


}
