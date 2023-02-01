<?php

namespace Drupal\bhcc_case_management;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\webform\WebformInterface;

/**
 * Class AvaliblilityService.
 */
class AvaliblilityService implements AvaliblilityServiceInterface {

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AvaliblilityService object.
   */
  public function __construct() {

    // @TODO proper dependency injection
    $this->config = \Drupal::config('bhcc_case_management.settings');
    $this->httpClient = \Drupal::service('http_client');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function check(WebformInterface $webform) {

    // Get case management service details
    $caseManagementURL = $this->config->get('case_management_post_url');
    $authHeader = $this->config->get('case_management_auth_header');

    // Check the webform configuration to see if they have a different
    // Contact management configured.
    $handlers = $webform->getHandlers();
    $handler_ids = $handlers->getInstanceIds();
    foreach ($handler_ids as $handler_id) {
      $handler = $handlers->get($handler_id);
      $plugin_id = $handler->getPluginId();

      // If this is a CM handler, and the post_url and auth_header are set,
      // Check if it is overridden or part of a CM group.
      if ($plugin_id == 'bhcc_case_management') {
        $settings = $handler->getSettings();

        // Check if overridden.
        if ($settings['enable_override']) {
          $caseManagementURL = $settings['override_case_management_post_url'];
          $authHeader = $settings['override_case_management_auth_header'];
        }

        // Check CM group.
        elseif ($settings['contact_management_group']) {
          $cm_group_id = $settings['contact_management_group'];
          if ($cm_group_id) {
            $cm_group = $this->entityTypeManager
              ->getStorage('bhcc_contact_management_group')
              ->load($cm_group_id);
            if ($cm_group) {
              $caseManagementURL = $cm_group->getPostUrl();
              $authHeader = $cm_group->getAuthHeader();
            }
          }
        }
      }
    }

    $requestOptions = [
      'headers' => [
        'Authorization' => $authHeader,
      ],
    ];

    // Make get request
    try {
      $response = $this->httpClient->get($caseManagementURL, $requestOptions);
    }
    catch (RequestException $request_exception) {
      $response = $request_exception->getResponse();

      // Encode HTML entities to prevent broken markup from breaking the page.
      $message = $request_exception->getMessage();
      $message = nl2br(htmlentities($message));
      return FALSE;
    }

    // Return based on status code
    $status_code = $response->getStatusCode();
    return ($status_code >= 200 || $status_code < 300) ? TRUE : FALSE;
  }

}
