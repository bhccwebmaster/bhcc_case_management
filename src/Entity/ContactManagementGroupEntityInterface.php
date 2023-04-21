<?php

namespace Drupal\bhcc_case_management\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Contact management group entities.
 */
interface ContactManagementGroupEntityInterface extends ConfigEntityInterface {

  /**
   * Set the Contact management post url.
   *
   * @param string $post_url
   *   Post url to set.
   *
   * @return \Drupal\bhcc_case_management\Entity\ContactManagementGroupEntityInterface
   *   Entity for method chaining.
   */
  public function setPostUrl(String $post_url) :ContactManagementGroupEntityInterface;

  /**
   * Get the Contact manaagement post url.
   *
   * @return string|null
   *   Post url to use.
   */
  public function getPostUrl();

  /**
   * Set the Contact management auth header.
   *
   * @param string $auth_header
   *   Auth header to set.
   *
   * @return \Drupal\bhcc_case_management\Entity\ContactManagementGroupEntityInterface
   *   Entity for method chaining.
   */
  public function setAuthHeader(String $auth_header) :ContactManagementGroupEntityInterface;

  /**
   * Get the auth header.
   *
   * @return string|null
   *   Auth header to use.
   */
  public function getAuthHeader();

}
