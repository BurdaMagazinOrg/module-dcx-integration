<?php

/**
 * @file
 * Contains the code to generate the custom drush commands.
 */

use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Implements hook_drush_command().
 */
function dcx_integration_drush_command() {
  $items = [];
  $items['dcx-queue'] = [
    'description' => 'Works on the dcx queue',
  ];
  return $items;
}

/**
 * Implements drush_{module}_{command}.
 */
function drush_dcx_integration_dcx_queue() {

  $queueFactory = \Drupal::service('queue');
  $queueManager = \Drupal::service('plugin.manager.queue_worker');

  $dcxQueues = [
    'dcx_article_archiver',
    'dcx_media_usage_worker',
  ];

  foreach ($queueManager->getDefinitions() as $queue_name => $info) {

    if (!in_array($queue_name, $dcxQueues)) {
      continue;
    }

    // !! Code copied from cron->processQueue() !!
    if (isset($info['cron'])) {
      // Make sure every queue exists. There is no harm in trying to recreate
      // an existing queue.
      $queueFactory->get($queue_name)->createQueue();

      $queue_worker = $queueManager->createInstance($queue_name);
      $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
      $queue = $queueFactory->get($queue_name);
      while (time() < $end && ($item = $queue->claimItem())) {
        try {
          $queue_worker->processItem($item->data);
          $queue->deleteItem($item);
        }
        catch (RequeueException $e) {
          // The worker requested the task be immediately requeued.
          $queue->releaseItem($item);
        }
        catch (SuspendQueueException $e) {
          // If the worker indicates there is a problem with the whole queue,
          // release the item and skip to the next queue.
          $queue->releaseItem($item);

          watchdog_exception('cron', $e);

          // Skip to the next queue.
          continue 2;
        }
        catch (\Exception $e) {
          // In case of any other kind of exception, log it and leave the item
          // in the queue to be processed again later.
          watchdog_exception('cron', $e);
        }
      }
    }
  }
}
