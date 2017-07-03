<?php

namespace Drupal\dcx_article_archive\Plugin\QueueWorker;

use Drupal\dcx_integration\ClientInterface;
use Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryServiceInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates product categories.
 *
 * @QueueWorker(
 *   id = "dcx_article_archiver",
 *   title = @Translation("Archive Articles to DC-X"),
 *   cron = {"time" = 60}
 * )
 */
class ArticleArchiver extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The referenced entity discovery service.
   *
   * @var \Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryServiceInterface
   */
  protected $discovery;

  /**
   * The DC-X Integration Client.
   *
   * @var \Drupal\dcx_integration\ClientInterface
   */
  protected $client;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The render service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * ArticleArchiver constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\dcx_track_media_usage\ReferencedEntityDiscoveryServiceInterface $discovery
   *   Entity type discovery service.
   * @param \Drupal\dcx_integration\ClientInterface $client
   *   The dcx client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, ReferencedEntityDiscoveryServiceInterface $discovery, ClientInterface $client, LoggerChannelFactoryInterface $logger_factory, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->discovery = $discovery;
    $this->client = $client;
    $this->logger = $logger_factory->get('dcx_article_archive');
    $this->renderer = $renderer;
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
      $container->get('entity_type.manager'),
      $container->get('dcx_track_media_usage.discover_referenced_entities'),
      $container->get('dcx_integration.client'),
      $container->get('logger.factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($id) {
    $node = $this->entityTypeManager->getStorage('node')->load($id);

    // The node might not exists anymore because it was deleted.
    if (!$node) {
      return;
    }

    $this->archive($node);
  }

  /**
   * Archives a node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node to archive.
   */
  protected function archive(EntityInterface $node) {
    $data = [];
    $data['title'] = $node->title->value;
    $data['status'] = $node->status->value;
    $data['id'] = $node->id();
    $data['entity_type_id'] = $node->getEntityTypeId();

    // Todo: Should probably use a custom view mode.
    $paragraphs = $node->field_paragraphs->view("default");
    $rendered = $this->renderer->renderPlain($paragraphs);
    $data['body'] = strip_tags($rendered);

    // Find attached images.
    $used_media = $this->discovery->discover($node, TRUE);

    foreach ($used_media as $dcx_id => $media_entity) {
      $caption = $media_entity->field_description->value;
      $data['media'][$dcx_id] = ['caption' => $caption, 'id' => $dcx_id];
    }

    $url = $node->toUrl()->setAbsolute()->toString();

    // This is NULL for new articles and that's perfectly fine.
    $existing_dcx_id = $node->field_dcx_id->value;

    try {
      $dcx_id = $this->client->archiveArticle($url, $data, $existing_dcx_id);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
      return;
    }

    // If a non-null id has changed while archiving something is severly wrong.
    // Yet another case of "this should never happen".
    if (NULL !== $existing_dcx_id && $existing_dcx_id != $dcx_id) {
      $message = t('Node %url changed its DC-X ID from %from to %to while archiving to DC-X.', [
        '%url' => $url,
        '%from' => $existing_dcx_id,
        '%to' => $dcx_id,
      ]);
      $this->logger->error($message);
      drupal_set_message($message, 'error');
      return;
    }

    // If the DC-X ID has changed, we need to save the id to the entity.
    if ($existing_dcx_id !== $dcx_id) {
      $node->set('field_dcx_id', $dcx_id, FALSE);
      // Prevent requeuing for archiving. See dcx_article_archive_node_update().
      $node->DO_NOT_QUEUE_AGAIN = TRUE;
      $node->save();
    }
  }

}
