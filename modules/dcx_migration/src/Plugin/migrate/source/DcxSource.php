<?php

namespace Drupal\dcx_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Row;

/**
 * Source for DcxImage.
 *
 * @MigrateSource(
 *   id = "dcx_asset"
 * )
 */
class DcxSource extends SourcePluginBase {

  /**
   * The DC-X Service this source plugin is retrieving from.
   *
   * @var \Drupal\dcx_integration\ClientInterface
   */
  protected $dcx_service;

  /**
   * The asset class we expect to retrieve from the DC-X Service.
   */

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    if (!isset($configuration['dcx_service'])) {
      throw new MigrateException('You must declare the "dcx_service" in your source settings.');
    }
    // @TODO I'd love to inject this service. Or even a custom instance.
    $this->dcx_service = \Drupal::service($configuration['dcx_service']);

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  protected function getDcxObject($id) {
    return $this->dcx_service->getObject($id);
  }

  public function getIDs() {
    return ['id' => ['type' => 'string']];
  }

  protected function initializeIterator() {
    $map = $this->migration->getIdMap();
    $query = $map->getDatabase()->select($map->mapTableName(), 'map')
              ->fields('map');
    $result = $query->execute();

    $rows = $result->fetchAllAssoc('sourceid1');

    $arrayObject = new \ArrayObject($rows);

    return $arrayObject->getIterator();
  }

  public function fields() {
    return ['id' => 'The unique dcx identifier of this ressource'];
  }

  public function __toString() {
    return __METHOD__;
  }

  public function getRowById($id) {
    $dcx_object = $this->getDcxObject($id);
    $row_data = $dcx_object->data();

    $row = new Row($row_data, $this->getIds(), $this->migration->get('destinationIds'));

    return $row;
  }

  /**
   * This add the id of the migrated entity to the row, if this is an update.
   *
   * We use this in process plugins to prevent some data to be overridden.
   *
   * This only works for a single valued destination id and might break badly
   * otherwise.
   *
   * @param Row $row
   *   The row to prepare.
   *
   * @return bool
   *   TRUE
   */
  public function prepareRow(Row $row) {
    $exisiting_row = $this->migration->getIdMap()->getRowBySource(['id' => $row->getSourceProperty('id')]);
    if ($exisiting_row) {
      $row->isUpdate = TRUE;
      $row->destid1 = $exisiting_row['destid1'];

      // On Update just allow to update to rights fields.
      $updateFieldWhitlist = [
        'field_dcx_id',
        'field_expires',
        'status',
        'changed',
      ];

      $process = $this->migration->getProcess();

      $this->migration
        ->setProcess(array_intersect_key($process, array_combine($updateFieldWhitlist, $updateFieldWhitlist)));
    }

    $row->setDestinationProperty('changed', time());

    return TRUE;
  }

}
