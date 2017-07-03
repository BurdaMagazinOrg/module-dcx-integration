<?php

namespace Drupal\dcx_migration;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Custom MigrationExecutable.
 *
 * Which is able to take an idlist just like the drush migate-import command.
 * Thus heavily inspired by \Drupal\migrate_tools\MigrateExecutable.
 */
class DcxMigrateExecutable extends MigrateExecutable implements MigrateMessageInterface {
  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(MigrationInterface $migration, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($migration, $this, $event_dispatcher);

    $this->listeners[MigrateEvents::PRE_IMPORT] = [$this, 'onPreImport'];
    $this->listeners[MigrateEvents::POST_IMPORT] = [$this, 'onPostImport'];
    foreach ($this->listeners as $event => $listener) {
      $event_dispatcher->addListener($event, $listener);
    }
  }

  /**
   * Implements \Drupal\migrate\MigrateMessageInterface::display.
   *
   * This also act as MigrateMessage providerer for now.
   */
  public function display($message, $type = 'status') {
    drupal_set_message($message, $type);
  }

  /**
   * React to migration completion.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The map event.
   */
  public function onPreImport(MigrateImportEvent $event) {
    $event->getMigration();
  }

  /**
   * React to migration completion.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The map event.
   */
  public function onPostImport(MigrateImportEvent $event) {
    $migrate_last_imported_store = \Drupal::keyValue('migrate_last_imported');
    $migrate_last_imported_store->set($event->getMigration()->id(), round(microtime(TRUE) * 1000));
    $this->removeListeners();
  }

  /**
   * Clean up all our event listeners.
   */
  protected function removeListeners() {
    foreach ($this->listeners as $event => $listener) {
      $this->getEventDispatcher()->removeListener($event, $listener);
    }
  }

  /**
   * Performs an import operation.
   */
  public function importItemWithUnknownStatus($id) {
    $id_map = $this->migration->getIdMap();

    // This is a noop if $id is not in $id_map. So it's save to run it anyway.
    $this->prepareUpdate($id, $id_map);

    $this->getEventDispatcher()->dispatch(MigrateEvents::PRE_IMPORT, new MigrateImportEvent($this->migration, $this->message));

    $source = $this->getSource();
    $row = $source->getRowById($id);

    // prepareRow is normally called by the next method of the source's
    // iterator. As we are not iterating, we have to call it manually here.
    $source->prepareRow($row);
    $this->sourceIdValues = $row->getSourceIdValues();

    try {
      $this->processRow($row);
      $save = TRUE;
    }
    catch (MigrateException $e) {
      $this->migration->getIdMap()->saveIdMapping($row, [], $e->getStatus());
      $this->saveMessage($e->getMessage(), $e->getLevel());
      $save = FALSE;
    }
    catch (MigrateSkipRowException $e) {
      $id_map->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
      $save = FALSE;
    }

    if ($save) {
      try {
        $this->getEventDispatcher()->dispatch(MigrateEvents::PRE_ROW_SAVE, new MigratePreRowSaveEvent($this->migration, $this->message, $row));
        $destination = $this->migration->getDestinationPlugin();
        $destination_id_values = $destination->import($row, $id_map->lookupDestinationId($this->sourceIdValues));
        $this->getEventDispatcher()->dispatch(MigrateEvents::POST_ROW_SAVE, new MigratePostRowSaveEvent($this->migration, $this->message, $row, $destination_id_values));
        if ($destination_id_values) {
          // We do not save an idMap entry for config.
          if ($destination_id_values !== TRUE) {
            $id_map->saveIdMapping($row, $destination_id_values, $this->sourceRowStatus, $destination->rollbackAction());
          }
        }
        else {
          $id_map->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_FAILED);
          if (!$id_map->messageCount()) {
            $message = $this->t('New object was not saved, no error provided');
            $this->saveMessage($message);
            $this->message->display($message);
          }
        }
      }
      catch (MigrateException $e) {
        $this->migration->getIdMap()->saveIdMapping($row, [], $e->getStatus());
        $this->saveMessage($e->getMessage(), $e->getLevel());
      }
      catch (\Exception $e) {
        $this->migration->getIdMap()->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_FAILED);
        $this->handleException($e);
      }
    }
    if ($high_water_property = $this->migration->get('highWaterProperty')) {
      $this->migration->saveHighWater($row->getSourceProperty($high_water_property['name']));
    }

    // Reset row properties.
    unset($sourceValues, $destinationValues);
    $this->sourceRowStatus = MigrateIdMapInterface::STATUS_IMPORTED;

    $this->getEventDispatcher()->dispatch(MigrateEvents::POST_IMPORT, new MigrateImportEvent($this->migration, $this->message));

  }

  /**
   * Mark map entry of the map with the source id as ready to be re-imported.
   *
   * @param string $id
   *   Source id.
   * @param \Drupal\migrate\Plugin\migrate\id_map\Sql $map
   *   Migrate map.
   *
   * @TODO This depends on a single valued source id and might break badly for
   * multi-valued ones.
   */
  public function prepareUpdate($id, Sql $map) {
    $map->getDatabase()->update($map->mapTableName())
      ->fields(['source_row_status' => MigrateIdMapInterface::STATUS_NEEDS_UPDATE])
      ->condition('sourceid1', $id)
      ->execute();
  }

  /**
   * Gets the migration plugin.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration plugin.
   */
  public function getMigration() {
    return $this->migration;
  }

  /**
   * Determine if a given DCX id was already imported.
   *
   * Looks up the given source id and return either the respective (possibly
   * multivalued) destination id or NULL.
   *
   * @param string $id
   *   Source_id.
   *
   * @return array|null
   *   Destination id.
   */
  public function isReimport($id) {
    $destids = $this->migration->getIdMap()->lookupDestinationIds(['id' => $id]);
    return current($destids);
  }

}
