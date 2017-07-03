<?php

namespace Drupal\dcx_migration;

/**
 * Interface DcxImportServiceInterface.
 *
 * @package Drupal\dcx_migration
 */
interface DcxImportServiceInterface {

  /**
   * Import the given DC-X IDs.
   *
   * Technically this prepares a batch process. It's either processed by Form
   * API if we're running in context of a form, or return the batch definition
   * for further processing.
   *
   * @param array $ids
   *   List of DC-X IDs to import.
   */
  public function import(array $ids);

}
