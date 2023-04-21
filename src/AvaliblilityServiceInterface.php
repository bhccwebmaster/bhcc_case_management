<?php

namespace Drupal\bhcc_case_management;

use Drupal\webform\WebformInterface;

/**
 * Class to provide the Interface AvaliblilityServiceInterface.
 */
interface AvaliblilityServiceInterface {

  /**
   * Check that the case management service is up.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform that is checking availability.
   *
   * @return bool
   *   Service up or down.
   */
  public function check(WebformInterface $webform);

}
