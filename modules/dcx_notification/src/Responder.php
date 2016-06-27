<?php

namespace Drupal\dcx_notification;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dcx_migration\DcxImportServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class Responder extends ControllerBase {

  /**
   * The DC-X Client.
   *
   * @var \Drupal\dcx_migration\DcxImportServiceInterface $importService
   */
  protected $importService;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection $connection
   */
  protected $db_connection;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request $request
   */
  protected $request;

  /**
   * The router.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * The Constructor.
   *
   * @param \Drupal\dcx_migration\DcxImportServiceInterface $importService
   *   The DC-X Client.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   */
  public function __construct(DcxImportServiceInterface $importService, Connection $connection, Request $request, RouterInterface $router) {
    $this->importService = $importService;
    $this->db_connection = $connection;
    $this->request = $request;
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_migration.import'),
      $container->get('database'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('router')
    );
  }

  /**
   * Evaluates the GET parameters and acts appropriately.
   *
   * As this represents the one URL on which DC-X talks to us, it relies on
   * _GET params rather than fancy URLs.
   *
   * @return Response
   *   An appropriate Response depending on parameters.
   *
   * @throws NotAcceptableHttpException
   */
  public function trigger() {
    $path = $this->request->query->get('url', NULL);

    // If we get a path (e.g. node/42): "Please resave the entity (node) behind
    // this, because an image used on this entity was removed and we need to
    // reflect this."
    // Note: We migth have id and url here as parameters. We simply ignore the
    // id here (because the respective image is gone anyway by now.)
    if (NULL !== $path) {
      return $this->resaveNode($path);
    }

    // If we get an ID: "Please reimport the given DC-X ID to update the
    // respective entity, because the DC-X document has changed.".
    $id = $this->request->query->get('id', NULL);
    if (NULL !== $id) {
      return $this->reimportId($id);
    }

    throw new NotAcceptableHttpException($this->t('Invalid URL parameter.'));
  }

  /**
   * Evaluates the GET parameters and acts appropriately.
   *
   * As this represents the one URL on which DC-X talks to us, it relies on
   * _GET params rather than fancy URLs.
   *
   * @return Response
   *   An appropriate Response depending on parameters.
   *
   * @throws NotAcceptableHttpException
   */
  public function import() {

    $id = $this->request->query->get('id', NULL);
    if (NULL !== $id) {
      $query = $this->db_connection->select('migrate_map_dcx_migration', 'm')
        ->fields('m', ['destid1'])
        ->condition('sourceid1', $id);
      $result = $query->execute()->fetchAllKeyed(0, 0);

      if (0 == count($result)) {
        $this->importService->import([$id]);
        return new Response('OK', 200);
      }
      else {
        throw new NotAcceptableHttpException($this->t('ID already imported.'));

      }
    }

    throw new NotAcceptableHttpException($this->t('Invalid URL parameter.'));
  }

  /**
   * Triggers reimport (== update migration) of the media item belonging to the
   * given DC-X ID.
   *
   * @param string $id a DC-X ID to reimport.
   * @return Response an empty (204) response.
   *
   * @throws NotFoundHttpException if Drupal does not know this ID
   * @throws NotAcceptableHttpException if The ID is ambiguous.
   */
  protected function reimportId($id) {

    $query = $this->db_connection->select('migrate_map_dcx_migration', 'm')
      ->fields('m', ['destid1'])
      ->condition('sourceid1', $id);
    $result = $query->execute()->fetchAllKeyed(0, 0);

    if (0 == count($result)) {
      throw new NotFoundHttpException();
    }

    if (1 < count($result)) {
      throw new NotAcceptableHttpException($this->t('Parameters point to more than one entity.'));
    }

    // @TODO ->import() is handling Exceptions. How are we going to handle an
    // error here?
    $this->importService->import([$id]);

    return new Response('OK', 200);
  }

  /**
   * Resaves the node behind the given path.
   *
   * This triggers writing of usage information.
   *
   * @see dcx_track_media_usage_node_update
   *
   * @param type $path
   * @return Response an empty (204) response.
   *
   * @throws ResourceNotFoundException If the resource could not be found
   * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
   * @throws NotFoundHttpException if the path does not represent a valid node
   */
  public function resaveNode($path) {
    // This may trow exceptions, we allow them to bubble up.
    $params = $this->router->match("/$path");
    $node = isset($params['node']) ? $params['node'] : FALSE;

    if (!$node) {
      throw new NotFoundHttpException();
    }
    $node->save();

    return new Response(NULL, 204);
  }

}
