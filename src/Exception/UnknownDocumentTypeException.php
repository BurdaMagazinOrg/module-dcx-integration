<?php

namespace Drupal\dcx_integration\Exception;

/**
 *
 */
class UnknownDocumentTypeException extends \Exception {

  /**
   * Constructs UnknownDocumentTypeException.
   */
  public function __construct($type, $id) {
    $message = sprintf("DC-X object %s has unknown type '%s'.", $id, $type);
    parent::__construct($message);
  }

}
