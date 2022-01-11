<?php

namespace Drupal\grants_metadata\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Address DataType.
 *
 * @DataType(
 * id = "grants_metadata_yleisavustushakemus",
 * label = @Translation("Yleisavustushakemus"),
 * definition_class = "\Drupal\grants_metadata\TypedData\Definition\YleisavustusHakemusDefinition"
 * )
 */
class YleisavustusHakemusData extends Map {

}
