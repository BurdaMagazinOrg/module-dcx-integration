<?php

namespace Drupal\dcx_track_media_usage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class ReferencedEntityDiscoveryService.
 *
 * @package Drupal\dcx_track_media_usage
 */
class ReferencedEntityDiscoveryService implements ReferencedEntityDiscoveryServiceInterface {
  use StringTranslationTrait;

  /**
   * A plugin manager taking care of plugins implementing the
   * ReferencedEntityDiscoveryPluginInterface.
   *
   * @see ReferencedEntityDiscoveryPluginInterface
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $plugin_manager;

  /**
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   */
  public function __construct(PluginManagerInterface $plugin_manager, TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
    $this->plugin_manager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function discover(EntityInterface $entity, $return_entities = FALSE) {
    $plugins = $this->plugin_manager->getDefinitions();

    $referencedEntities = [];

    foreach ($plugins as $plugin) {
      $instance = $this->plugin_manager->createInstance($plugin['id']);
      $referencedEntities += $instance->discover($entity, $this->plugin_manager);
    }

    $usage = [];
    foreach ($referencedEntities as $referencedEntity) {
      $dcx_id = $referencedEntity->field_dcx_id->value;

      if (empty($dcx_id)) {
        throw new \Exception($this->t('Found media:image %id without DC-X ID.', ['%id' => $referencedEntity->id()]));
      }

      if ($return_entities) {
        $usage[$dcx_id] = $referencedEntity;
      }
      else {
        $usage[$dcx_id] = $dcx_id;
      }
    }

    return $usage;
  }

}
