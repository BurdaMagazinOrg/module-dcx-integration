<?php

namespace Drupal\dcx_integration\Exception;

/**
 *
 */
class MandatoryAttributeException extends \Exception {

  public $attribute;

  /**
   * Constructs MandatoryAttributeException.
   */
  public function __construct($attribute) {
    $message = sprintf("Attribute '%s' is mandatory", $attribute);
    parent::__construct($message);

    $this->attribute = $attribute;
  }

}
