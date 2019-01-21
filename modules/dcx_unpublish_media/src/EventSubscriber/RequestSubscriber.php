<?php

namespace Drupal\dcx_unpublish_media\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RequestSubscriber.
 *
 * @package Drupal\dcx_unpublish_media
 */
class RequestSubscriber implements EventSubscriberInterface {

  protected $request;

  protected $entityTypeManager;

  /**
   * RequestSubscriber constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Current request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(RequestStack $requestStack, EntityTypeManagerInterface $entityTypeManager) {
    $this->request = $requestStack->getCurrentRequest();
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['kernel.request'] = ['kernelRequest'];

    return $events;
  }

  /**
   * This method is called whenever the kernel.request event is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   Kernel request event.
   */
  public function kernelRequest(Event $event) {

    $publicPath = PublicStream::basePath();

    $uri = $this->request->getRequestUri();

    if (strpos($uri, $publicPath . DIRECTORY_SEPARATOR . 'styles') !== FALSE) {

      $path = parse_url($uri)['path'];
      $filename = pathinfo($path)['basename'];

      $query = $this->entityTypeManager->getStorage('file')->getQuery();
      $query->condition('filename', $filename);
      $fids = $query->execute();

      if ($fids) {
        $bundles = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
        foreach ($bundles as $bundle) {
          if ($bundle->get('source') == 'image') {

            $field = $bundle->get('type_configuration')['source_field'];

            $query = $this->entityTypeManager->getStorage('media')->getQuery();
            $query->condition("$field.target_id", current($fids));
            $mids = $query->execute();

            if ($mids) {
              $media = $this->entityTypeManager->getStorage('media')->load(current($mids));

              if (!$media->status->value) {
                $event->setResponse(new Response(NULL, 410));
              }
            }
          }
        }
      }
    }
  }

}
