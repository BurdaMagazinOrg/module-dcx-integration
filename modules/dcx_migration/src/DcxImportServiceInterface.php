<?php

namespace Drupal\dcx_migration;

interface DcxImportServiceInterface {

  /**
   * Import the given DC-X IDs.
   *
   * @param array $ids
   *   List of DC-X IDs to import.
   */
  public function import($ids);

}
