<?php

namespace Drupal\dcx_integration\Asset;

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
   * @throws \Exception
   *   If mandatory attributes are missing or unknown attributes are present.
   */
  public function __construct($data, $mandatory_attributes, $optional_attributes = []) {
    foreach ($mandatory_attributes as $attribute) {
      if (!isset($data[$attribute])) {
        throw new \Exception("Attribute $attribute is mandatory in " . __METHOD__);
      }
    }

    // Only allow mandatory and optional attributes.
    $unknown_attributes = array_diff(array_keys($data), array_merge($optional_attributes, $mandatory_attributes));
    if (!empty($unknown_attributes)) {
      throw new \Exception("The following attributes are not allowed: " . print_r($unknown_attributes, 1) . " in " . __METHOD__);
    }

    $this->data = $data;
  }

  public function data() {
    return $this->data;
  }

}
