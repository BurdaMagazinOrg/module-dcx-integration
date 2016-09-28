<?php

namespace Drupal\dcx_migration;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
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
    if (empty($context['results'])) {
      $context['results']['count'] = 0;
      $context['results']['success'] = 0;
      $context['results']['fail'] = [];
      $context['results']['reimport'] = [];
    }

    $context['results']['count']++;

    $re = $executable->isReimport($id);
    if ($re) {
      $context['results']['reimport'][$id] = current($re);
    }
    try {
      $executable->importItemWithUnknownStatus($id);
      $context['results']['success']++;
    }
    catch (\Exception $e) {
      $context['results']['fail'][] = $id;
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
    $t = \Drupal::translation();
    $success = $t->translate('Imported @success of @count items.', ['@success' => $results['success'], '@count' => $results['count']]);
    drupal_set_message($success);

    foreach ($results['reimport'] as $dcxid => $mid) {
      $url = Url::fromRoute('entity.media.canonical', ['media' => $mid], ['attributes' => ['target' => '_blank']]);
      $link = Link::fromTextAndUrl('media/' . $mid, $url)->toString();
      drupal_set_message($t->translate('Item @dcxid was imported before as @link.', ['@dcxid' => $dcxid, '@link' => $link]));
    }

    if (!empty($results['fail'])) {
      $fail = $t->translate('The following item(s) failed to import: @items', ['@items' => join(', ', $results['fail'])]);
      drupal_set_message($fail);
    }
  }

  /**
   * Helper to retrieve entity id for the give DC-X ID, if present.
   *
   * @param array of DC-X IDs
   *
   * @return array of entity id or FALSE, keyed by DC-X ID
   */
  public function getEntityIds(array $dcx_ids) {
    $executable = $this->getMigrationExecutable();
    $map = $executable->getMigration()->getIdMap();

    $entity_ids = [];

    foreach ($dcx_ids as $i) {
      $destid = $map->lookupDestinationIds([$i]);
      $entity_ids[$i] = $destid[0][0];
    };

    return $entity_ids;
  }
}
