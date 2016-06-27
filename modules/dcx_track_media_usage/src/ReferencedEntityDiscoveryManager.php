<?php

namespace Drupal\dcx_track_media_usage;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Class ReferencedEntityDiscoveryManager.
 *
 * @package Drupal\dcx_track_media_usage
 */
class ReferencedEntityDiscoveryManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ReferencedEntityDiscovery',
      $namespaces,
      $module_handler,
      'Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryPluginInterface',
      'Drupal\dcx_track_media_usage\Annotation\ReferencedEntityDiscovery'
    );
    $this->alterInfo('referenced_entity_discovery');
    $this->setCacheBackend($cache_backend, 'referenced_entity_discovery');
  }

}
