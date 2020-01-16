<?php

namespace Drupal\dcx_integration\Exception;

/**
 * Class MandatoryAttributeException.
 */
class MandatoryAttributeException extends \Exception {

  /**
   * The missing attribute string.
   *
   * @var string
   */
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
