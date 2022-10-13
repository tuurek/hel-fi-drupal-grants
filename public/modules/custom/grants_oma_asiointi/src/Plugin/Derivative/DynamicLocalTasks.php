<?php

namespace Drupal\grants_oma_asiointi\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines dynamic local tasks.
 */
class DynamicLocalTasks extends DeriverBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /* Implement dynamic logic to provide values for the same keys as
     * in example. links.task.yml.
     */

    $this->derivatives['grants_oma_asiointi.front'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.front']['title'] = $this->t("My Services");
    $this->derivatives['grants_oma_asiointi.front']['route_name'] = 'grants_oma_asiointi.front';
    $this->derivatives['grants_oma_asiointi.front']['base_route'] = 'grants_oma_asiointi.front';

    $this->derivatives['grants_oma_asiointi.grantsprofile.show'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.grantsprofile.show']['title'] = $this->t("Grants profile");
    $this->derivatives['grants_oma_asiointi.grantsprofile.show']['route_name'] = 'grants_profile.show';
    $this->derivatives['grants_oma_asiointi.grantsprofile.show']['base_route'] = 'grants_oma_asiointi.front';

    $this->derivatives['grants_oma_asiointi.grantsprofile.edit'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.grantsprofile.edit']['title'] = $this->t("Grants profile");
    $this->derivatives['grants_oma_asiointi.grantsprofile.edit']['route_name'] = 'grants_profile.edit';
    $this->derivatives['grants_oma_asiointi.grantsprofile.edit']['base_route'] = 'grants_profile.show';

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
