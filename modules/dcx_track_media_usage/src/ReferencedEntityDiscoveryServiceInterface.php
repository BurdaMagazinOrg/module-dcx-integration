<?php

namespace Drupal\dcx_track_media_usage;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface ReferencedEntityDiscoveryServiceInterface.
 *
 * @package Drupal\dcx_track_media_usage
 */
interface ReferencedEntityDiscoveryServiceInterface {

  /**
   * Collect media:image entities referenced by this $entity.
   *
   * In any way we can detect by the implemented plugins.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param bool $return_entities
   *   Returns List of entities keyed by DC-X IDs instead of the IDs.
   *
   * @return array
   *   Array of DC-X IDs.
   */
  public function discover(EntityInterface $entity, $return_entities = FALSE);

}
