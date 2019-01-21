<?php

namespace Drupal\dcx_migration;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\dcx_integration\Exception\NoOnlinePermissionException;
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
  protected $migrateExecutable;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * DcxImportService constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   Translation service.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $plugin_manager
   *   Plugin manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   */
  public function __construct(TranslationInterface $string_translation, MigrationPluginManagerInterface $plugin_manager, EventDispatcherInterface $event_dispatcher) {
    $this->stringTranslation = $string_translation;
    $this->pluginManager = $plugin_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Returns an instance of the custom migrate executable.
   *
   * Make sure it is created if not already done.
   *
   * @return \Drupal\dcx_migration\DcxMigrateExecutable
   *   Migration executable.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getMigrationExecutable() {
    if (NULL == $this->migrateExecutable) {
      $migration = $this->pluginManager->createInstance('dcx_migration');
      $this->migrateExecutable = new DcxMigrateExecutable($migration, $this->eventDispatcher);
    }

    return $this->migrateExecutable;
  }

  /**
   * {@inheritdoc}
   */
  public function import(array $ids) {
    $executable = $this->getMigrationExecutable();

    $operations = [];
    foreach ($ids as $id) {
      $operations[] = [[__CLASS__, 'batchImport'], [$id, $executable]];
    }
    $batch = [
      'title' => $this->t('Import media from DC-X'),
      'operations' => $operations,
      'finished' => [__CLASS__, 'batchFinished'],
    ];

    batch_set($batch);
  }

  /**
   * Batch operation callback.
   *
   * @param string $id
   *   DC-X ID to import.
   * @param \Drupal\dcx_migration\DcxMigrateExecutable $executable
   *   The custom migratte exectuable to perform the import.
   * @param array|\ArrayAccess $context
   *   The batch context array, passed by reference.
   */
  public static function batchImport($id, DcxMigrateExecutable $executable, &$context) {
    if (empty($context['results'])) {
      $context['results']['count'] = 0;
      $context['results']['success'] = 0;
      $context['results']['fail'] = [];
      $context['results']['no_rights'] = [];
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
    catch (NoOnlinePermissionException $e) {
      $context['results']['no_rights'][] = $id;
    }
    catch (\Exception $e) {
      $context['results']['fail'][] = $id;
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinished($success, array $results, array $operations) {
    $t = \Drupal::translation();
    $status = 'warning';
    if ($results['success'] == $results['count']) {
      $status = 'status';
    }
    $success = $t->translate('Imported @success of @count items.', ['@success' => $results['success'], '@count' => $results['count']]);
    drupal_set_message($success, $status);

    foreach ($results['reimport'] as $dcxid => $mid) {
      $url = Url::fromRoute('entity.media.canonical', ['media' => $mid], ['attributes' => ['target' => '_blank']]);
      $link = Link::fromTextAndUrl('media/' . $mid, $url)->toString();
      drupal_set_message($t->translate('Item @dcxid was imported before as @link.', ['@dcxid' => $dcxid, '@link' => $link]), 'warning');
    }

    if (!empty($results['fail'])) {
      $fail = $t->translate('The following item(s) failed to import: @items', ['@items' => implode(', ', $results['fail'])]);
      drupal_set_message($fail, 'error');
    }
    if (!empty($results['no_rights'])) {
      $fail = $t->translate('The following item(s) failed to import, because there are no online permissions: @items', ['@items' => implode(', ', $results['no_rights'])]);
      drupal_set_message($fail, 'warning');
    }
  }

  /**
   * Helper to retrieve entity id for the give DC-X ID, if present.
   *
   * @param array $dcx_ids
   *   DC-X IDs.
   *
   * @return array|bool
   *   Array of entity id or FALSE, keyed by DC-X ID
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function getEntityIds(array $dcx_ids) {
    $executable = $this->getMigrationExecutable();
    $map = $executable->getMigration()->getIdMap();

    $entity_ids = [];

    foreach ($dcx_ids as $i) {
      $destid = $map->lookupDestinationIds([$i]);
      if (!empty($destid[0][0])) {
        $entity_ids[$i] = $destid[0][0];
      }
    };

    return $entity_ids;
  }

}
