<?php

namespace Drupal\dcx_collections\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dcx_integration\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class Collection.
 *
 * @package Drupal\dcx_collections\Controller
 */
class Collection extends ControllerBase {

  /**
   * The dcx client service.
   *
   * @var \Drupal\dcx_integration\ClientInterface
   */
  protected $dcxClient;

  /**
   * Collection constructor.
   *
   * @param \Drupal\dcx_integration\ClientInterface $dcxClient
   *   The dcx client service.
   */
  public function __construct(ClientInterface $dcxClient) {
    $this->dcxClient = $dcxClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_integration.client')
    );
  }

  /**
   * Returns all docs of a collection.
   *
   * @param int $id
   *   Collection id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response of docs.
   */
  public function docsOfCollection($id) {
    $doc_ids = $this->dcxClient->getDocsOfCollection($id);
    $raw_ids = array_map(function ($d) {
      return preg_replace('#dcxapi:document/#', '', $d);
    }, $doc_ids);

    return new JsonResponse($raw_ids);
  }

  /**
   * Returns an image of a document.
   *
   * @param int $id
   *   Document id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json repsonse of preview image.
   */
  public function imagePreview($id) {
    $json = $this->dcxClient->getPreview("dcxapi:document/$id");

    return new JsonResponse($json);
  }

}
