<?php

namespace Drupal\dcx_integration\Exception;

/**
 * Class NoOnlinePermissionException.
 */
class NoOnlinePermissionException extends \Exception {

  /**
   * Constructs IllegalAssetTypeException.
   */
  public function __construct($id) {
    $message = sprintf("DC-X document '%s' has no permission to be used online.", $id);
    parent::__construct($message);
  }

}
