<?php

namespace Drupal\Tests\dcx_integration;

/**
 * Dummy API client class.
 *
 * It allows introspection of called methods and arguments for testing.
 */
class DummyDcxApiClient /* implement DcxApiClientInterface */ {

  /**
   *
   * @var stringnameofthelastmethodcallonthisobject
   */
  public $method;

  /**
   *
   * @var mixedargumentsofthelastcall
   */
  public $args;

  /**
   *
   */
  public function getObject($url, array $params, &$data) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    return $this->_doit($url, $params, $data, $data);
  }

  /**
   *
   */
  public function createObject($url, array $params, array $data, &$response_body) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    $data = NULL;
    return $this->_doit($url, $params, $data, $response_body);
  }

  /**
   *
   */
  public function setObject($url, array $params, array $data, &$response_body) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    $data = NULL;
    return $this->_doit($url, $params, $data, $response_body);
  }

  /**
   *
   */
  public function deleteObject($url, array $params, &$response_body) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    $data = NULL;
    return $this->_doit($url, $params, $data, $response_body);
  }

  /**
   *
   */
  public function _doit($url, array $params, &$data, &$response_body) {
    $this->args = func_get_args();

    // Provides means to manipulate $response_body.
    // If $this->expected_response_body_array its items are set to
    // $response_body consecutive calls.
    // If $this->expected_response_body (and *_array is empty) $response_body is
    // set to it.
    if (empty($this->expected_response_body_array)) {
      $response_body = isset($this->expected_response_body) ? $this->expected_response_body : $response_body;
    }
    else {
      $response_body = array_shift($this->expected_response_body_array);
    }

    // Provides means to manipulate the return value
    // If $this->expected_return_value_array its items are returned on
    // consecutive calls.
    // If $this->expected_return_value (and *_array is empty) it's returned
    // instead of the default value.
    if (empty($this->expected_return_value_array)) {
      $return = isset($this->expected_return_value) ? $this->expected_return_value : 200;
    }
    else {
      $return = array_shift($this->expected_return_value_array);
    }

    // Keep track of methods called on this instance.
    $this->methods[] = $this->method;
    $this->urls[] = $url;

    /*
    // This helps a big deal finding out where this method was called initially.
    $bt = debug_backtrace();
    fputs(STDERR, print_r($bt[1]['file']. ":" . $bt[1]['line'] . "::" . $this->method . "\n" ,1));
     */

    return $return;
  }

}
