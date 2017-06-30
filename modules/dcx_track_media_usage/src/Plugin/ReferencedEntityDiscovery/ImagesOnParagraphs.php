<?php

namespace Drupal\dcx_track_media_usage\Plugin\ReferencedEntityDiscovery;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryPluginInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Returns list of referenced media:image entities on entity reference fields.
 *
 * @TODO Replace hardcoded media:image with argument to make this a bit
 *   more flexible.
 *
 * @ReferencedEntityDiscovery(
 *   id = "images_on_paragraphs"
 * )
 */
class ImagesOnParagraphs extends PluginBase implements ReferencedEntityDiscoveryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function discover(EntityInterface $entity, PluginManagerInterface $plugin_manager) {
    $discovered = [];

    if (!$entity instanceof FieldableEntityInterface) {
      return $discovered;
    }

    // Iterate over the field definition of the given entitiy.
    foreach ($entity->getFieldDefinitions() as $definition) {
      // Fields have FieldConfig. Let's assume our media is referenced within a
      // field.
      if (!$definition instanceof FieldConfig) {
        continue;
      }
      // Only care about entity reference fields.
      if ('entity_reference_revisions' !== $definition->getType()) {
        continue;
      }
      $settings = $definition->getSettings();

      // We can't be sure that a target type is defined. Deal with it.
      $target_type = isset($settings['target_type']) ? $settings['target_type'] : NULL;

      // Only care about field referencing media.
      if ('paragraph' !== $target_type) {
        continue;
      }

      $field = $definition->getName();

      $referenced_entities = $entity->$field->referencedEntities();

      if (empty($referenced_entities)) {
        continue;
      }

      // Use the entity_reference_field plugin to search the paragraph entities.
      $images_by_reference_field_discovery = NULL;
      $images_on_galleries_discovery = NULL;
      foreach ($referenced_entities as $referenced_entity) {
        if ('gallery' == $referenced_entity->getType()) {
          if (!$images_on_galleries_discovery) {
            $images_on_galleries_discovery = $plugin_manager->createInstance('images_on_galleries');
          }
          $discovered += $images_on_galleries_discovery->discover($referenced_entity, $plugin_manager);
        }

        if ('media' == $referenced_entity->getType() || 'image' == $referenced_entity->getType()) {
          if (!$images_by_reference_field_discovery) {
            $images_by_reference_field_discovery = $plugin_manager->createInstance('images_by_reference_field');
          }
          $discovered += $images_by_reference_field_discovery->discover($referenced_entity, $plugin_manager);
        }
      }
    }

    return $discovered;
  }

}
