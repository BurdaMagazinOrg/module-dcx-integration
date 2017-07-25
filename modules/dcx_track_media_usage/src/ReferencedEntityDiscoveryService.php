<?php

namespace Drupal\dcx_track_media_usage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dcx_track_media_usage\Exception\FoundNonDcxEntityException;

/**
 * Class ReferencedEntityDiscoveryService.
 *
 * @package Drupal\dcx_track_media_usage
 */
class ReferencedEntityDiscoveryService implements ReferencedEntityDiscoveryServiceInterface {
  use StringTranslationTrait;

  /**
   * A plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManager;

  /**
   * ReferencedEntityDiscoveryService constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   *   The plugin manager service.
   */
  public function __construct(PluginManagerInterface $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function discover(EntityInterface $entity, $return_entities = FALSE) {
    $plugins = $this->pluginManager->getDefinitions();

    $referencedEntities = [];

    foreach ($plugins as $plugin) {
      $instance = $this->pluginManager->createInstance($plugin['id']);
      $referencedEntities += $instance->discover($entity, $this->pluginManager);
    }

    $usage = [];
    foreach ($referencedEntities as $referencedEntity) {
      $dcx_id = $referencedEntity->field_dcx_id->value;

      if (!empty($dcx_id)) {
        if ($return_entities) {
          $usage[$dcx_id] = $referencedEntity;
        }
        else {
          $usage[$dcx_id] = ['id' => $referencedEntity->id(), 'entity_type_id' => $referencedEntity->getEntityTypeid()];
        }
      }
    }

    return $usage;
  }

}
