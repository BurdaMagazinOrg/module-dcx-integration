<?php

namespace Drupal\dcx_migration;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dcx_migration\DcxMigrateExecutable;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service to import documents from DC-X to Drupal.
 */
class DcxImportService implements DcxImportServiceInterface {
  use StringTranslationTrait;

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
  protected $plugin_mangager;

  /**
   * Event dispatcher
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $event_dispatcher;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $plugin_manager
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   */
  public function __construct(TranslationInterface $string_translation, MigrationPluginManagerInterface $plugin_manager , EventDispatcherInterface $event_dispatcher) {
    $this->stringTranslation = $string_translation;
    $this->plugin_manager = $plugin_manager;
    $this->event_dispatcher = $event_dispatcher;
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
   * Import the given DC-X IDs.
   *
   * Technically this prepares a batch process. It's either processed by Form
   * API if we're running in context of a form, or return the batch definition
   * for further processing
   */
  public function import($ids) {
    $executable = $this->getMigrationExecutable();

    foreach($ids as $id) {
      $operations[] = [[__CLASS__, 'batchImport'], [$id, $executable]];
    }
    $batch = array(
      'title' => t('Import media from DC-X'),
      'operations' => $operations,
      'finished' => [__CLASS__, 'batchFinished'],
    );

    batch_set($batch);
  }

  /**
   * Batch operation callback.
   *
   *
   * @param string $id DC-X ID to import.
   * @param \Drupal\dcx_migration\DcxMigrateExecutable $executable
   *   The custom migratte exectuable to perform the import.
   * @param array|\ArrayAccess $context.
   * The batch context array, passed by reference.
   */
  public static function batchImport($id, $executable, &$context) {
    try {
      $executable->importItemWithUnknownStatus($id);
    }
    catch (\Exception $e) {
      $executable->display($e->getMessage());
    }
  }

  /**
   * Batch finished callback.
   *
   * @param $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinished($success, $results, $operations) {
    // A noop for now.
  }

}
