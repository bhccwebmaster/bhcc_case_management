<?php

namespace Drupal\bhcc_case_management\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Contact management group entity.
 *
 * @ConfigEntityType(
 *   id = "bhcc_contact_management_group",
 *   label = @Translation("Contact management group"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\bhcc_case_management\ContactManagementGroupEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\bhcc_case_management\Form\ContactManagementGroupEntityForm",
 *       "edit" = "Drupal\bhcc_case_management\Form\ContactManagementGroupEntityForm",
 *       "delete" = "Drupal\bhcc_case_management\Form\ContactManagementGroupEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\bhcc_case_management\ContactManagementGroupEntityHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "bhcc_contact_management_group",
 *   admin_permission = "manage contact management groups",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "uuid",
 *     "post_url",
 *     "auth_header"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/bhcc-bhcc_case_management/groups/group/{bhcc_contact_management_group}",
 *     "add-form" = "/admin/config/services/bhcc-bhcc_case_management/groups/group/add",
 *     "edit-form" = "/admin/config/services/bhcc-bhcc_case_management/groups/group/{bhcc_contact_management_group}/edit",
 *     "delete-form" = "/admin/config/services/bhcc-bhcc_case_management/groups/group/{bhcc_contact_management_group}/delete",
 *     "collection" = "/admin/config/services/bhcc-bhcc_case_management/groups"
 *   }
 * )
 */
class ContactManagementGroupEntity extends ConfigEntityBase implements ContactManagementGroupEntityInterface {

  /**
   * Contact management group ID.
   *
   * @var string
   */
  protected $id;

  /**
   * Contact management group name.
   *
   * @var string
   */
  protected $name;

  /**
   * Contact management post url.
   *
   * @var string
   */
  protected $post_url;

  /**
   * Contact management auth header.
   *
   * @var string
   */
  protected $auth_header;

  /**
   * {@inheritdoc}
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(String $name) {
    $this->name = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setPostUrl(String $post_url) :ContactManagementGroupEntityInterface {
    $this->post_url = $post_url;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPostUrl() {
    return $this->post_url;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthHeader(String $auth_header) :ContactManagementGroupEntityInterface {
    $this->auth_header = $auth_header;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthHeader() {
    return $this->auth_header;
  }

}
