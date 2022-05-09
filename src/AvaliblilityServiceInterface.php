<?php

namespace Drupal\bhcc_case_management;

/**
 * Interface AvaliblilityServiceInterface.
 */
interface AvaliblilityServiceInterface {

  /**
   * Check that the case management service is up
   * @return boolean
   *   Service up or down.
   */
  public function check();

}
