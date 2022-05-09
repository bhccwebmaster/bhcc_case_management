<?php

namespace Drupal\bhcc_case_management;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class AvaliblilityService.
 */
class AvaliblilityService implements AvaliblilityServiceInterface {

  protected $config;

  protected $httpClient;

  /**
   * Constructs a new AvaliblilityService object.
   */
  public function __construct() {

    // @TODO proper dependency injection
    $this->config = \Drupal::config('bhcc_case_management.settings');
    $this->httpClient = \Drupal::service('http_client');
  }

  /**
   * {@inheritdoc}
   */
  public function check() {

    // Get case management service details
    $caseManagementURL = $this->config->get('case_management_post_url');
    $authHeader = $this->config->get('case_management_auth_header');

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
