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
   *   List of mandatory attributes for this asset.
   * @param array $optional_attributes
   *   List of optional attributes for this asset.
   *
   * @throws \Drupal\dcx_integration\Exception\MandatoryAttributeException
   *   If mandatory attributes are missing.
   * @throws \Drupal\dcx_integration\Exception\IllegalAttributeException
   *   If unknown attributes are present.
   */
  public function __construct(array $data, array $mandatory_attributes, array $optional_attributes = []) {
    foreach ($mandatory_attributes as $attribute) {
      if (!isset($data[$attribute])) {
        $e = new MandatoryAttributeException($attribute);
        throw $e;
      }
    }

    // Only allow mandatory and optional attributes.
    $unknown_attributes = array_diff(array_keys($data), array_merge($optional_attributes, $mandatory_attributes));
    if (!empty($unknown_attributes)) {
      $e = new IllegalAttributeException(current($unknown_attributes));
      throw $e;
    }

    $this->data = $data;
  }

  /**
   * The data representing the asset.
   *
   * @return array
   *   Return the data.
   */
  public function data() {
    return $this->data;
  }

}
