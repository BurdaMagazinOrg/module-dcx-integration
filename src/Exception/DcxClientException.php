<?php

/**
 * @file
 * Contains \Drupal\dcx_integration\Exception\DcxClientException
 */
namespace Drupal\dcx_integration\Exception;

/**
 * Throw whenever the DC-X API client returns some status different from 200.
 */
class DcxClientException extends \Exception {
  function __construct($url, $code) {
    $message = sprintf('Error getting "%s". Status code was %s.', $url, $code);
    parent::__construct($message, $code);
  }
}
