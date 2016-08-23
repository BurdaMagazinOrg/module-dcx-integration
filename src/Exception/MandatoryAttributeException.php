<?php

/**
 * @file
 * Contains \Drupal\dcx_integration\Exception\MandatoryAttributeException.
 */
namespace Drupal\dcx_integration\Exception;

class MandatoryAttributeException extends \Exception {

  public $attribute;

  function __construct($attribute) {
    $message = sprintf("Attribute '%s' is mandatory", $attribute);
    parent::__construct($message);

    $this->attribute = $attribute;
  }
}
