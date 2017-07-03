<?php

namespace Drupal\dcx_track_media_usage\Exception;

/**
 * Class FoundNonDcxEntityException.
 */
class FoundNonDcxEntityException extends \Exception {

  /**
   * Constructs FoundNonDcxEntityException.
   */
  public function __construct($entity_type, $bundle, $entity_id) {
    $message = sprintf("Found entity '%s::%s::%s' without DC-X ID.", $entity_type, $bundle, $entity_id);
    parent::__construct($message);
  }

}
