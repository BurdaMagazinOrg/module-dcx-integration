<?php

namespace Drupal\dcx_migration\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class MediaRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    if (\Drupal::moduleHandler()->moduleExists('media')) {
      /** @var \Drupal\media\Entity\MediaType[] $bundles */
      $bundles = \Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple();

      foreach ($bundles as $bundle) {

        if ($bundle->get('source') == 'image') {
          $routes['dcx_migration.form.' . $bundle->id()] = new Route(
          // Path to attach this route to:
            'media/add/' . $bundle->id(),
            // Route defaults:
            [
              '_form' => '\Drupal\dcx_migration\Form\DcxImportForm',
              '_title' => 'Import Image from DC-X',
            ],
            // Route requirements:
            [
              '_entity_create_access' => 'media:' . $bundle->id(),
            ],
            [
              '_admin_route' => TRUE,
            ]
          );
        }
      }
    }

    return $routes;
  }

}
