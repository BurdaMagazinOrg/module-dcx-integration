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
  function __construct($method, $code, $url, $params = [], $json = [], $message = '') {
    $message = sprintf('Error performing %s on url "%s". Status code was %s. Params: %s. JSON: %s. %s', $method, $url, $code, json_encode($params), json_encode($json), $message);
    parent::__construct($message, $code);
  }
}
