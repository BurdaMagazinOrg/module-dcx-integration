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
   * @var string name of the last method call on this object
   */
  public $method;

  /**
   *
   * @var mixed arguments of the last call
   */
  public $args;

  public function getObject($url, array $params, &$data) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    return $this->_doit($url, $params, $data, $data);
  }

  public function createObject($url, array $params, array $data, &$response_body) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    return $this->_doit($url, $params, $data, $response_body);
  }

  public function setObject($url, array $params, array $data, &$response_body) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    return $this->_doit($url, $params, $data, $response_body);
  }

  public function deleteObject($url, array $params, &$response_body) {
    // __METHOD__ would return namespaced value.
    $this->method = __FUNCTION__;
    return $this->_doit($url, $params, $data, $response_body);
  }

  public function _doit($url, array $params, &$data, &$response_body) {
    $this->args = func_get_args();

    // Provides means to manipulate $response_body.
    $response_body = isset($this->expected_response_body) ? $this->expected_response_body : $response_body;

    return isset($this->expected_return_value) ? $this->expected_return_value : 200;
  }

}
