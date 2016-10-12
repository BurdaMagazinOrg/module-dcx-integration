<?php

namespace Drupal\dcx_collections\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dcx_integration\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class Collection extends ControllerBase {

  protected $dcxClient;

  public function __construct(ClientInterface $dcxClient) {
    $this->dcxClient = $dcxClient;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_integration.client')
    );
  }

  public function docsOfCollection($id) {
    $doc_ids = $this->dcxClient->getDocsOfCollection($id);
    $raw_ids = array_map(function($d) { return preg_replace('#dcxapi:document/#', '', $d);}, $doc_ids);

    return new JsonResponse($raw_ids);
  }


  public function imagePreview($id) {
    $json = $this->dcxClient->getPreview("dcxapi:document/$id");

    return new JsonResponse($json);
  }
}
