<?php

namespace Drupal\bhcc_case_management;

use Drupal\webform\WebformInterface;

/**
 * Interface AvaliblilityServiceInterface.
 */
interface AvaliblilityServiceInterface {

  /**
   * Check that the case management service is up
   *
   * @param \Drupal\webform\WebformInterface $webform;
   *   The webform that is checking avalibility.
   * @return boolean
   *   Service up or down.
   */
  public function check(WebformInterface $webform);

}
