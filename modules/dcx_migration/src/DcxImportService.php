<?php

namespace Drupal\dcx_migration;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service to import documents from DC-X to Drupal.
 */
class DcxImportService implements DcxImportServiceInterface {
  use StringTranslationTrait;


  /**
   * The queue worker manager.
   *
   * @var QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * Queue factory.
   *
   * @var QueueFactory
   */
  protected $queueFactory;

  /**
   * The constructor.
   *
   * @param QueueWorkerManagerInterface $queueWorkerManager
   *   The queue worker manager.
   * @param QueueFactory $queueFactory
   *   Queue factory.
   */
  public function __construct(QueueWorkerManagerInterface $queueWorkerManager, QueueFactory $queueFactory) {
    $this->queueWorkerManager = $queueWorkerManager;
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function import($ids) {

    $queue = $this->queueFactory->get('dcx_import_worker', TRUE);

    foreach ($ids as $id) {
      $queue->createItem($id);
    }

    /** @var QueueWorkerInterface $queue_worker */
    $queue_worker = $this->queueWorkerManager->createInstance('dcx_import_worker');

    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        watchdog_exception('npq', $e);
      }
    }

  }

}
