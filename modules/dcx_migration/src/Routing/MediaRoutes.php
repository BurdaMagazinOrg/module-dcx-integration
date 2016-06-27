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

    /** @var MediaBundle[] $bundles */
    $bundles = \Drupal::entityTypeManager()->getStorage('media_bundle')->loadMultiple();

    $routes = array();

    foreach ($bundles as $bundle) {

      if ($bundle->get('type') == 'image') {
        $routes['dcx_migration.form.' . $bundle->id()] = new Route(
        // Path to attach this route to:
          'media/add/' . $bundle->id(),
          // Route defaults:
          array(
            '_form' => '\Drupal\dcx_migration\Form\DcxImportForm',
            '_title' => 'Import Image from DC-X',
          ),
          // Route requirements:
          array(
            '_entity_create_access' => 'media:' . $bundle->id(),
          ),
          array(
            '_admin_route' => TRUE,
          )
        );
      }

    }

    return $routes;
  }

}
