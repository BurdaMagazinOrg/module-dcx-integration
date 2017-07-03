<?php

namespace Drupal\dcx_integration\Asset;

/**
 * Class Image.
 *
 * @package Drupal\dcx_integration\Asset
 */
class Image extends BaseAsset {
  public static $mandatoryAttributes = [
    'id',
    'filename',
    'title',
    'url',
    'status',
  ];

  public static $optionalAttributes = [
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
  public function __construct(array $data) {
    parent::__construct($data, self::$mandatoryAttributes, self::$optionalAttributes);
  }

}
