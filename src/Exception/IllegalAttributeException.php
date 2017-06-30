<?php

namespace Drupal\dcx_integration\Exception;

/**
 * Class IllegalAttributeException.
 */
class IllegalAttributeException extends \Exception {

  /**
   * Constructs IllegalAttributeException.
   */
  public function __construct($attribute) {
    $message = sprintf("Attribute %s is not allowed", $attribute);
    parent::__construct($message);
  }

}
