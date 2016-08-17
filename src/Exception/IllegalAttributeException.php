<?php

/**
 * @file
 * Contains \Drupal\dcx_integration\Exception\IllegalAttributeException.
 */
namespace Drupal\dcx_integration\Exception;

class IllegalAttributeException extends \Exception {
  function __construct($attribute) {
    $message = sprintf("Attribute %s is not allowed", $attribute);
    parent::__construct($message);
  }
}
