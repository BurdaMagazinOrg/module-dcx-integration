<?php

namespace Drupal\dcx_integration\Asset;

use Drupal\dcx_integration\Exception\MandatoryAttributeException;
use Drupal\dcx_integration\Exception\IllegalAttributeException;
/**
 * Base class for abstraction object for DC-X documents.
 */
abstract class BaseAsset {

  protected $data;

  /**
   * Constructor.
   *
   * The whole point of this is to enforce and restrict the presence of certain
   * data.
   *
   * @param array $data
   *   The data representing the asset.
   * @param array $mandatory_attributes
   * @param array $optional_attributes
   *
   * @throws \Drupal\dcx_integration\Exception\MandatoryAttributeException
   *   if mandatory attributes are missing.
   * @throws \Drupal\dcx_integration\Exception\IllegalAttributeException
   *   if munknown attributes are present.
   */
  public function __construct($data, $mandatory_attributes, $optional_attributes = []) {
    foreach ($mandatory_attributes as $attribute) {
      if (!isset($data[$attribute])) {
        $e = new \MandatoryAttributeException($attribute);
        watchdog_exception(__METHOD__, $e);
        throw $e;
      }
    }

    // Only allow mandatory and optional attributes.
    $unknown_attributes = array_diff(array_keys($data), array_merge($optional_attributes, $mandatory_attributes));
    if (!empty($unknown_attributes)) {
      $e = new \IllegalAttributeException($unknown_attributes);
      watchdog_exception(__METHOD__, $e);
      throw $e;
    }

    $this->data = $data;
  }

  public function data() {
    return $this->data;
  }

}
