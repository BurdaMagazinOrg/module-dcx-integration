<?php

namespace Drupal\dcx_media_image_clone;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

class UpdateClonedMediaService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The DC-X migration.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * Constructor.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   * @param MigrationPluginManagerInterface $migratePluginManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, MigrationPluginManagerInterface $migratePluginManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->migration = $migratePluginManager->createInstance('dcx_migration');
  }

  /**
   * Propagate the overwrite_properties defined in the DC-X Migration to all
   * clones of the given media.
   *
   * @param EntityInterface $media
   */
  public function updateClones(EntityInterface $media) {
    // Only operate on media:image entities which are not clones themselves.
    if (!('media' === $media->getEntityTypeId()
        && 'image' === $media->bundle()
        && NULL === $media->field_parent_media->target_id)) {
      return;
    }

    $destination_config = $this->migration->getDestinationConfiguration();
    if (!isset($destination_config['overwrite_properties'])) {
      return;
    }

    foreach ($this->getClones($media) as $clone) {
      foreach ($destination_config['overwrite_properties'] as $field_name) {
        $clone->$field_name = $media->$field_name;
      }
      $clone->save();
    }
  }

  /**
   * Delete clones of the given media:image entity
   *
   * @param EntityInterface $media
   */
  public function deleteClones(EntityInterface $media) {
    // Only operate on media:image entities which are not clones themselves.
    if (!('media' === $media->getEntityTypeId()
        && 'image' === $media->bundle()
        && NULL === $media->field_parent_media->target_id)) {
      return;
    }

    foreach ($this->getClones($media) as $clone) {
      $clone->delete();
    }
  }

  /**
   * Return all entities which are marked as clone of the given one.
   *
   * @param EntityInterface $media
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their ids.
   */
  protected function getClones(EntityInterface $media) {
    return $this->entityTypeManager->getStorage($media->getEntityTypeId())
      ->loadByProperties(['field_parent_media' => $media->id()]);
  }
}
