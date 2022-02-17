<?php

namespace Drupal\grants_profile\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Address DataType.
 *
 * @DataType(
 * id = "grants_profile_bank_account",
 * label = @Translation("Bank Account"),
 * definition_class = "\Drupal\grants_profile\TypedData\Definition\BankAccountDefinition"
 * )
 */
class BankAccountData extends Map {

}
