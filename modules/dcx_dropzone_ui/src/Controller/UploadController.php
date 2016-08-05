<?php

namespace Drupal\dcx_dropzone_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
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
   * @var \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   */
  protected $request;

  /**
   * Constructs dropzone upload controller route controller.
   *
   * @param \Drupal\dcx_migration\DcxImportServiceInterface $importService
   *   The DCX Import Service actually processing the input.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  public function __construct(DcxImportServiceInterface $importService, Request $request) {
    $this->importService = $importService;

    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_migration.import'),
      $container->get('request_stack')->getCurrentRequest()
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

    $response = batch_process(Url::fromRoute('dcx_dropzone.batch_finish'));
    $batch_url = $response->headers->get('location');

    preg_match('/\?id=(.*)&/', $batch_url, $matches);
    $batch_id = $matches[1];

    require_once 'core/includes/batch.inc';

    $GET = ['id' => $batch_id, 'op' => 'start'];
    $request = new Request($GET);

    $build = _batch_page($request);

    $settings = $build['content']['#attached']['drupalSettings']['batch'];

    $markup = drupal_render($build['content']);

    return new JsonResponse(['markup' => $markup, 'settings' => $settings]);
  }

  public function batchFinish() {
    $messages = drupal_render(\Drupal\Core\Render\Element\StatusMessages::renderMessages(NULL));
    return new JsonResponse(['markup' => $messages]);
  }
}
