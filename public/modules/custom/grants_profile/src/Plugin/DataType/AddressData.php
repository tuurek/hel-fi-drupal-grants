<?php

namespace Drupal\grants_profile\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Address DataType.
 *
 * @DataType(
 * id = "grants_profile_address",
 * label = @Translation("Address"),
 * definition_class = "\Drupal\grants_profile\TypedData\Definition\AddressDefinition"
 * )
 */
class AddressData extends Map {

}
