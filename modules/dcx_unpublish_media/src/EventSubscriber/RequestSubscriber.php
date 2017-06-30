<?php

namespace Drupal\dcx_unpublish_media\EventSubscriber;

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\media_entity\Entity\MediaBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RequestSubscriber.
 *
 * @package Drupal\dcx_unpublish_media
 */
class RequestSubscriber implements EventSubscriberInterface {

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

    $uri = \Drupal::request()->getRequestUri();

    if (strpos($uri, $publicPath . DIRECTORY_SEPARATOR . 'styles') !== FALSE) {

      $path = parse_url($uri)['path'];
      $filename = pathinfo($path)['basename'];

      $query = \Drupal::entityQuery('file');
      $query->condition('filename', $filename);
      $fids = $query->execute();

      if ($fids) {
        foreach (MediaBundle::loadMultiple() as $bundle) {
          if ($bundle->get('type') == 'image') {

            $field = $bundle->get('type_configuration')['source_field'];

            $query = \Drupal::entityQuery('media');
            $query->condition("$field.target_id", current($fids));
            $mids = $query->execute();

            if ($mids) {
              $media = entity_load('media', current($mids));

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
