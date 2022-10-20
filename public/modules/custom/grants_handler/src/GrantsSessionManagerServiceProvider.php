<?php

namespace Drupal\grants_handler;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 *
 */
class GrantsSessionManagerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('session_configuration');
    $definition->setClass('Drupal\grants_handler\GrantsSessionConfiguration');
  }

}
