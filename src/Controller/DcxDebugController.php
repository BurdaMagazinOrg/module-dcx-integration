<?php

namespace Drupal\dcx_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dcx_integration\ClientInterface;

/**
 * Class DcxDebugController.
 *
 * @package Drupal\dcx_integration\Controller
 */
class DcxDebugController extends ControllerBase {

  /**
   * Drupal\dcx_integration\ClientInterface definition.
   *
   * @var Drupal\dcx_integration\ClientInterface
   */
  protected $dcx_integration_client;

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $dcx_integration_client) {
    $this->dcx_integration_client = $dcx_integration_client;
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
   * Return debug information on the response for the given object identifiers.
   */
  public function debug($type, $id) {
    $dcxid = "dcxapi:" . $type . '/' . $id;

    try {
      if (method_exists($this->dcx_integration_client, 'getJson')) {
        $json = $this->dcx_integration_client->getJson($dcxid);
      }
      $data = $this->dcx_integration_client->getObject($dcxid);
    }
    catch (\Exception $e) {
      dpm($e->getMessage(), "Meh :(");
    }

    dpm($json, 'json');
    dpm($data, 'object');

    return [
      '#type' => 'markup',
      '#markup' => $this->t("Implement method: debug with parameter(s): $type, $id"),
    ];

  }

  public function archive() {
    $url = "http://burda.com/node/4711";
    $title = "Test test";
    $text = "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam"
      . " nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam"
      . " erat, sed diam voluptua. At vero eos et accusam et justo duo dolores"
      . " et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est"
      . " Lorem ipsum dolor sit amet.";
    $dcx_id = NULL;

    try {
      $dcx_id = $this->dcx_integration_client->archiveArticle($url, $title, $text, $dcx_id);
    }
    catch (\Exception $e) {
      dpm($e);
    }

    return [
      '#type' => 'markup',
      '#markup' => __METHOD__ . " " . $dcx_id,
    ];
  }

}
