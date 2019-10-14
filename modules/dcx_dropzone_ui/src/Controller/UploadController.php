<?php

namespace Drupal\dcx_dropzone_ui\Controller;

use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\dcx_migration\DcxImportServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class UploadController.
 *
 * Handles requests that dcx dropzone issues when uploading files.
 *
 * @package Drupal\dcx_dropzone_ui\Controller
 */
class UploadController extends ControllerBase {

  protected $importService;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   *   The HTTP request object.
   */
  protected $request;

  protected $renderer;

  /**
   * Constructs dropzone upload controller route controller.
   *
   * @param \Drupal\dcx_migration\DcxImportServiceInterface $importService
   *   The DCX Import Service actually processing the input.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer object.
   */
  public function __construct(DcxImportServiceInterface $importService, Request $request, RendererInterface $renderer) {
    $this->importService = $importService;
    $this->request = $request;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_migration.import'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('renderer')
    );
  }

  /**
   * Handles Dcx Dropzone uploads.
   */
  public function handleUploads() {
    $data = $this->request->getContent();

    $ids = [];
    // Data might be a simple string, which is technically not JSON ... so
    // we need to check.
    $json = json_decode($data);

    // Decoding failed -> single item URL as string.
    if ($json === NULL) {
      preg_match('|dcx/(document/doc.*)\?|', $data, $matches);
      if (!empty($matches)) {
        $ids[] = "dcxapi:" . $matches[1];
      }
    }
    // Decoding was successfull -> data is JSON -> data is multiple ids.
    else {
      $data = $json;
      foreach ($data as $val) {
        $ids[] = "dcxapi:" . current($val);
      }
    }

    if (empty($ids)) {
      throw new NotFoundHttpException();
    }

    $this->importService->import($ids, TRUE);

    // To make this work we need to patch core.
    // See https://www.drupal.org/node/2781993
    $batch_id = batch_process(Url::fromRoute('dcx_dropzone.batch_finish'), NULL, [__CLASS__, 'batchRedirectCallback']);

    require_once 'core/includes/batch.inc';

    $getParameter = ['id' => $batch_id, 'op' => 'start'];
    $request = new Request($getParameter);

    $build = _batch_page($request);

    $settings = $build['content']['#attached']['drupalSettings']['batch'];

    $markup = $this->renderer->render($build['content']);

    return new JsonResponse(['markup' => $markup, 'settings' => $settings]);
  }

  /**
   * Custom finish callback for AJAX processed batch.
   *
   * Renders all status messages and returns them in a JSON Response object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response object.
   */
  public static function batchFinish() {
    $status = StatusMessages::renderMessages(NULL);
    $messages = \Drupal::service('renderer')->render($status);
    return new JsonResponse(['markup' => $messages]);
  }

  /**
   * Custom batch redirect callback as used in batch_process as third argument.
   *
   * In this case we do not return a redirect response (as it is the default)
   * behaviour, but the id of the batch to be able to process it by AJAX.
   */
  public static function batchRedirectCallback($url, $query_options) {
    return $query_options['query']['id'];
  }

}
