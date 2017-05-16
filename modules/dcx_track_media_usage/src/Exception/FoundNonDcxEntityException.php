<?php

namespace Drupal\dcx_track_media_usage\Exception;

/**
 *
 */
class FoundNonDcxEntityException extends \Exception {

  /**
   *
   */
  public function __construct($entity_type, $bundle, $entity_id) {
    $message = sprintf("Found entity '%s::%s::%s' without DC-X ID.", $type, $bundle, $entity_id);
    parent::__construct($message);
  }

}
