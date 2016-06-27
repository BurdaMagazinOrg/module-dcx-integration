<?php

namespace Drupal\dcx_integration\Asset;

/**
 * Class Image.
 *
 * @package Drupal\dcx_integration\Asset
 */
class Image extends BaseAsset {
  static $mandatory_attributes = [
    'id',
    'filename',
    'title',
    'url',
    'status',
  ];

  static $optional_attributes = [
    'creditor',
    'copyright',
    'fotocredit',
    'source',
    'price',
    'kill_date',
  ];

  /**
   * Constructor.
   *
   * @param array $data
   *   Data representing this asset.
   */
  public function __construct($data) {
    parent::__construct($data, self::$mandatory_attributes, self::$optional_attributes);
  }

}
