<?php

namespace Drupal\dcx_debug_json_client;

/**
 * Class DpmOnScreenClient.
 *
 * @package Drupal\dcx_debug_json_client\
 */
class DpmOnScreenClient {

  public function __call($name, $arguments) {
    dpm($arguments, $name);
  }

}
