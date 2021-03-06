<?php

namespace Drupal\dcx_notification;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dcx_migration\DcxImportServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class Responder.
 *
 * @package Drupal\dcx_notification
 */
class Responder extends ControllerBase {

  /**
   * The DC-X Client.
   *
   * @var \Drupal\dcx_migration\DcxImportServiceInterface
   */
  protected $importService;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConnection;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The router.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * Responder constructor.
   *
   * @param \Drupal\dcx_migration\DcxImportServiceInterface $importService
   *   The DC-X Client.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   Router service.
   */
  public function __construct(DcxImportServiceInterface $importService, Connection $connection, Request $request, RouterInterface $router) {
    $this->importService = $importService;
    $this->dbConnection = $connection;
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
   * @return \Symfony\Component\HttpFoundation\Response
   *   An appropriate Response depending on parameters.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
   */
  public function trigger() {

    $token = $this->request->query->get('token', NULL);

    if (NULL !== $token) {

      $config = $this->config('dcx_integration.jsonclientsettings');
      if ($token != $config->get('notification_access_key')) {
        throw new AccessDeniedHttpException();
      }
    }
    else {
      throw new AccessDeniedHttpException();
    }

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
   * @return \Symfony\Component\HttpFoundation\Response
   *   An appropriate Response depending on parameters.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
   */
  public function import() {

    $token = $this->request->query->get('token', NULL);

    if (NULL !== $token) {

      $config = $this->config('dcx_integration.jsonclientsettings');
      if ($token != $config->get('notification_access_key')) {
        throw new AccessDeniedHttpException();
      }
    }
    else {
      throw new AccessDeniedHttpException();
    }

    $id = $this->request->query->get('id', NULL);
    if (NULL !== $id) {
      $query = $this->dbConnection->select('migrate_map_dcx_migration', 'm')
        ->fields('m', ['destid1'])
        ->condition('sourceid1', $id);
      $result = $query->execute()->fetchAllKeyed(0, 0);

      if (0 == count($result)) {
        $this->importService->import([$id]);

        $batch =& batch_get();
        $batch['progressive'] = FALSE;
        batch_process();

        return new Response('OK', 200);
      }
      else {
        throw new NotAcceptableHttpException($this->t('ID already imported.'));

      }
    }

    throw new NotAcceptableHttpException($this->t('Invalid URL parameter.'));
  }

  /**
   * Re-Import.
   *
   * Triggers reimport (== update migration) of the media item belonging to the
   * given DC-X ID.
   *
   * @param string $id
   *   a DC-X ID to reimport.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   OK 200 if success.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If Drupal does not know this ID.
   * @throws \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
   *   If The ID is ambiguous.
   */
  protected function reimportId($id) {

    $query = $this->dbConnection->select('migrate_map_dcx_migration', 'm')
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

    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    batch_process();

    return new Response('OK', 200);
  }

  /**
   * Resaves the node behind the given path.
   *
   * This triggers writing of usage information.
   *
   * @param string $path
   *   The internal path.
   *
   * @see dcx_track_media_usage_node_update
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the path does not represent a valid node.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   an empty (204) response.
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
