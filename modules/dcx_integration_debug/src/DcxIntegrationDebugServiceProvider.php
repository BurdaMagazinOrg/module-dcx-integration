<?php

namespace Drupal\dcx_integration_debug;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

class DcxIntegrationDebugServiceProvider implements ServiceModifierInterface {

  /**
   * Modifies existing service definitions.
   *
   * @param ContainerBuilder $container
   *   The ContainerBuilder whose service definitions can be altered.
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('dcx_integration.client');
    $definition->setClass('Drupal\dcx_integration_debug\MockClient');
    $definition->setArguments([]);
  }

}
