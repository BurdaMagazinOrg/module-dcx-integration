<?php

namespace Drupal\dcx_integration\Asset;

use Drupal\dcx_integration\Exception\IllegalAttributeException;

/**
 * Class Image.
 *
 * @package Drupal\dcx_integration\Asset
 */
class Image extends BaseAsset {

  /**
   * Allowed MIME types.
   *
   * @var array
   */
  protected static $allowedMimeTypes = [
    'image/jpeg',
    'image/png',
  ];

  /**
   * {@inheritdoc}
   */
  public static $mandatoryAttributes = [
    'id',
    'filename',
    'title',
    'url',
    'status',
  ];

  /**
   * {@inheritdoc}
   */
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
    $mimeType = \Drupal::service('file.mime_type.guesser')->guessMimeType($data['url']);
    if (!in_array($mimeType, static::$allowedMimeTypes)) {
      throw new IllegalAttributeException($data['url']);
    }
  }

}
