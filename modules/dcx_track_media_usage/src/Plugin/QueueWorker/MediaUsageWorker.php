<?php

namespace Drupal\dcx_track_media_usage\Plugin\QueueWorker;

/**
 * @file
 * A worker that tracks usages on CRON run.
 */

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dcx_integration\ClientInterface;
use Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * QueueWorker annotation.
 *
 * @QueueWorker(
 *   id = "dcx_media_usage_worker",
 *   title = @Translation("DCX Media usage worker"),
 *   cron = {"time" = 60}
 * )
 */
class MediaUsageWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity discovery service.
   *
   * @var \Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryServiceInterface
   */
  protected $entityDiscoveryService;

  /**
   * DCX Client.
   *
   * @var \Drupal\dcx_integration\ClientInterface
   */
  protected $dcxClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LocaleTranslation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryServiceInterface $entityDiscoveryService
   *   The entity discovery service.
   * @param \Drupal\dcx_integration\ClientInterface $dcxClient
   *   DCX Client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ReferencedEntityDiscoveryServiceInterface $entityDiscoveryService, ClientInterface $dcxClient, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityDiscoveryService = $entityDiscoveryService;
    $this->dcxClient = $dcxClient;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dcx_track_media_usage.discover_referenced_entities'),
      $container->get('dcx_integration.client'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $entity = $this->entityTypeManager->getStorage($data['entity_type'])->load($data['entity_id']);

    if ($entity) {
      $usage = $this->entityDiscoveryService->discover($entity, FALSE);

      $url = $entity->toUrl()->getInternalPath();

      $status = $entity->status->value;
      try {
        $this->dcxClient->trackUsage($usage, $url, $status, 'image');
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

}
