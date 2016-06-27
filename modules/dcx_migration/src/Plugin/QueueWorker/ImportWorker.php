<?php

namespace Drupal\dcx_migration\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dcx_migration\DcxMigrateExecutable;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A Node Publisher that publishes nodes on CRON run.
 *
 * @QueueWorker(
 *   id = "dcx_import_worker",
 *   title = @Translation("DCX Importer"),
 * )
 */
class ImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {


  /**
   * The custom migrate exectuable.
   *
   * @var \Drupal\dcx_migration\DcxMigrateExecutable
   */
  protected $migration_executable;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $plugin_manager;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $event_dispatcher;

  /**
   * Constructs a new LocaleTranslation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param MigrationPluginManagerInterface $plugin_manager
   *   The plugin manager.
   * @param EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, MigrationPluginManagerInterface $plugin_manager, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->plugin_manager = $plugin_manager;
    $this->event_dispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migration'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Returns an instance of the custom migrate executable.
   *
   * Make sure it is created if not already done.
   *
   * @return \Drupal\dcx_migration\DcxMigrateExecutable
   */
  protected function getMigrationExecutable() {
    if (NULL == $this->migration_executable) {
      $migration = $this->plugin_manager->createInstance('dcx_migration');
      $this->migration_executable = new DcxMigrateExecutable($migration, $this->event_dispatcher);
    }

    return $this->migration_executable;
  }

  /**
   * Works on a single queue item.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Processing is not yet finished. This will allow another process to claim
   *   the item immediately.
   * @throws \Exception
   *   A QueueWorker plugin may throw an exception to indicate there was a
   *   problem. The cron process will log the exception, and leave the item in
   *   the queue to be processed again later.
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   More specifically, a SuspendQueueException should be thrown when a
   *   QueueWorker plugin is aware that the problem will affect all subsequent
   *   workers of its queue. For example, a callback that makes HTTP requests
   *   may find that the remote server is not responding. The cron process will
   *   behave as with a normal Exception, and in addition will not attempt to
   *   process further items from the current item's queue during the current
   *   cron run.
   *
   * @see \Drupal\Core\Cron::processQueues()
   */
  public function processItem($data) {

    $executable = $this->getMigrationExecutable();

    try {
      $executable->importItemWithUnknownStatus($data);
    }
    catch (\Exception $e) {
      $executable->display($e->getMessage());
    }

  }

}
