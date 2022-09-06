<?php

namespace Drupal\grants_oma_asiointi\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks.
 */
class DynamicLocalTasks extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /* Implement dynamic logic to provide values for the same keys as
     * in example. links.task.yml.
     */

    $this->derivatives['grants_oma_asiointi.front'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.front']['title'] = "My Services";
    $this->derivatives['grants_oma_asiointi.front']['route_name'] = 'grants_oma_asiointi.front';
    $this->derivatives['grants_oma_asiointi.front']['base_route'] = 'grants_oma_asiointi.front';

    $this->derivatives['grants_oma_asiointi.grantsprofile.show'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.grantsprofile.show']['title'] = "Grants profile";
    $this->derivatives['grants_oma_asiointi.grantsprofile.show']['route_name'] = 'grants_profile.show';
    $this->derivatives['grants_oma_asiointi.grantsprofile.show']['base_route'] = 'grants_oma_asiointi.front';

    $this->derivatives['grants_oma_asiointi.grantsprofile.edit'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.grantsprofile.edit']['title'] = "Grants profile";
    $this->derivatives['grants_oma_asiointi.grantsprofile.edit']['route_name'] = 'grants_profile.edit';
    $this->derivatives['grants_oma_asiointi.grantsprofile.edit']['base_route'] = 'grants_profile.show';

    $this->derivatives['grants_oma_asiointi.applications'] = $base_plugin_definition;
    $this->derivatives['grants_oma_asiointi.applications']['title'] = "Company applications";
    $this->derivatives['grants_oma_asiointi.applications']['route_name'] = 'grants_oma_asiointi.applications_list';
    $this->derivatives['grants_oma_asiointi.applications']['base_route'] = 'grants_oma_asiointi.front';

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
