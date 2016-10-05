<?php

namespace Drupal\dcx_collections\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class ImagePreview extends ControllerBase {

  public function preview($id) {
    $client = \Drupal::service('dcx_integration.client');
    $json = $client->getPreview("dcxapi:document/$id");

    return new JsonResponse($json);
  }
}
