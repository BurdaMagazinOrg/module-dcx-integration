<?php

namespace Drupal\dcx_track_media_usage;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface ReferencedEntityDiscoveryPluginInterface.
 *
 * @package Drupal\dcx_track_media_usage
 */
interface ReferencedEntityDiscoveryPluginInterface extends PluginInspectionInterface {

  /**
   * Find referenced entities on the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   *   The plugin manager service.
   *
   * @return array
   *   List of referenced entities keyed by entity id
   */
  public function discover(EntityInterface $entity, PluginManagerInterface $plugin_manager);

}
