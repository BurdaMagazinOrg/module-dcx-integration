<?php

namespace Drupal\dcx_debug_json_client;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class DcxDebugJsonClientServiceProvider.
 *
 * @package Drupal\dcx_debug_json_client
 */
class DcxDebugJsonClientServiceProvider implements ServiceModifierInterface {

  /**
   * Modifies existing service definitions.
   *
   * @param ContainerBuilder $container
   *   The ContainerBuilder whose service definitions can be altered.
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('dcx_integration.client');
    $definition->addArgument(new Reference("dcx_debug_json_client.client"));
  }

}
