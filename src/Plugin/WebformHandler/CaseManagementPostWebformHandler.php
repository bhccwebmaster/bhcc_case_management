<?php

namespace Drupal\bhcc_case_management\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\webform\Ajax\WebformRefreshCommand;
use Drupal\webform\Ajax\WebformSubmissionAjaxResponse;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformMessageManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform submission case management post handler.
 *
 * @WebformHandler(
 *   id = "bhcc_case_management",
 *   label = @Translation("Case Management"),
 *   category = @Translation("BHCC"),
 *   description = @Translation("Posts webform submissions to case management service."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = FALSE,
 * )
 */
class CaseManagementPostWebformHandler extends WebformHandlerBase {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The webform message manager.
   *
   * @var \Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    $instance->messageManager = $container->get('webform.message_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    // Excluded fields for future use.
    $field_names = array_keys(\Drupal::service('entity_field.manager')->getBaseFieldDefinitions('webform_submission'));
    $excluded_data = array_combine($field_names, $field_names);

    // Default values for overrides (blank)
    return [
      'contact_management_group' => '',
      'enable_override' => 0,
      'override_case_management_post_url'    => '',
      'override_case_management_auth_header' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $webform = $this->getWebform();

    // Get Contact management groups.
    $cm_group_storage = $this->entityTypeManager
      ->getStorage('bhcc_contact_management_group');
    $cm_group_ids = $cm_group_storage->getQuery()
      ->execute();
    $cm_groups = array_map(function ($id) use ($cm_group_storage) {
      return $cm_group_storage->load($id)->label();
    }, $cm_group_ids);

    // Contact management web service settings.
    $form['case_management'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact management settings'),
    ];

    // Contact management group select.
    $form['case_management']['contact_management_group'] = [
      '#type' => 'select',
      '#title' => $this->t('Contact management group'),
      '#options' => $cm_groups,
      '#default_value' => $this->configuration['contact_management_group'],
      '#empty_option' => $this->t('- No group -'),
      '#empty_value' => '',
    ];

    // Override contact management.
    $form['case_management']['enable_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override Contact management endpoints.'),
      '#default_value' => $this->configuration['enable_override'],
    ];
    $form['case_management']['override_case_management_post_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact management service post URL'),
      '#description' => $this->t('The URL to post form responses into Contact management.'),
      '#size' => 64,
      '#default_value' => $this->configuration['override_case_management_post_url'],
      '#states' => [
        'required' => [
          ':input[name="settings[enable_override]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['case_management']['override_case_management_auth_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact management service authorization header'),
      '#description' => $this->t('The header to add to authorize posts to Contact management.'),
      '#size' => 64,
      '#default_value' => $this->configuration['override_case_management_auth_header'],
      '#states' => [
        'required' => [
          ':input[name="settings[enable_override]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // $this->elementTokenValidate($form);
    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Copied from RemotePostWebformHandler.
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $state = $webform_submission->getWebform()->getSetting('results_disabled') ? WebformSubmissionInterface::STATE_COMPLETED : $webform_submission->getState();
    $postData = $this->caseManagementPrepare($this->webform, $webform_submission);
    $this->caseManagementPost($state, $postData, $webform_submission);
  }

  /**
   * Prepare webform submission for case management.
   *
   * @param \Drupal\webform\WebformInterfaceWebformInterface $webform
   *   The original webform.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform Submission.
   *
   * @return array
   *   Post data array.
   */
  protected function caseManagementPrepare(WebformInterface $webform, WebformSubmissionInterface $webform_submission) {

    $submission_data = $webform_submission->getData();
    $payload = [];
    if (!empty($submission_data)) {

      // Check if there are files and rationalise case key.
      $this->hasDocumentFiles($submission_data, $webform, $webform_submission);

      // Loop through submission data.
      foreach ($submission_data as $key => $value) {
        if ($key == 'citizenidtoken') {
          continue;
        }

        // Get the webform element.
        $webformElement = $webform->getElement($key);

        // Prepare the payload data structure.
        $question = !empty($webformElement['#title']) ? $webformElement['#title'] : '';
        $payload[] = $this->prepareResponsePayload($question, $value, $key, $webformElement);
      }
    }

    // Get service from selector.
    $service = $submission_data['serviceselector'] ?? NULL;

    // Get form title, assign the service label if present.
    $title = $webform->label() . ($service ? ' - ' . $service : '');

    $postData = [
      'id'        => $webform->id(),
      'title'     => $title,
      'url'       => $webform->toUrl()->toString(),
      'citizenId' => $submission_data['citizenidtoken'] ?? NULL,
      'category'  => $webform->get('category'),
      'service'   => $service,
      'payload'   => $payload,
    ];

    // Output debug payload.
    if (function_exists('dpm')) {
      // dpm(json_encode($postData));
    }

    return $postData;
  }

  /**
   * Has document files.
   *
   * @param array $submission_data
   *   Submission data.
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform entity.
   *
   * @return bool
   *   TRUE if there are files, FALSE otherwise.
   */
  protected function hasDocumentFiles(Array &$submission_data, WebformInterface $webform) {

    $has_files = FALSE;

    // Iterate through webform submission fields.
    foreach ($submission_data as $key => $value) {

      // Get the webform element.
      $webform_element = $webform->getElement($key);

      // If this is a document uploader field.
      if ($webform_element['#type'] == 'webform_document_file') {
        if (!empty($value)) {
          $has_files = TRUE;
        }
      }
    }

    // Unset the casekey here if no files.
    if (!$has_files) {
      unset($submission_data['casekey']);
    }

    return $has_files;
  }

  /**
   * Prepare element submission as response payload.
   *
   * @param string $question
   *   The webform element question.
   * @param string|array $value
   *   The user entered webform value,
   *   could be an array if form element is complex.
   * @param string $machine_name
   *   The webform element machine name.
   * @param array $webformElement
   *   The webform element details, gathered from $webform->getElement($key).
   *
   * @return array
   *   Payload array for case management, with keys question, machine name and
   *   either answer for simple answers,
   *   or composite_answer for multifield / multivalue answers.
   */
  protected function prepareResponsePayload(string $question, $value, string $machine_name, Array $webformElement) {

    $payload = [
      'question'         => $question,
      'answer'           => $value,
      'machine_name'     => $machine_name,
    ];

    // Handle the cases where the answer is an array.
    if (is_array($payload['answer'])) {
      if (!$this->handleElementSimpleCase($payload['answer']) && !$this->handleElementSpecialCase($payload['answer'], $webformElement)) {
        $payload['composite_answer'] = $this->prepareCompositePayload($value, $webformElement);
        unset($payload['answer']);
      }
    }

    return $payload;
  }

  /**
   * Handle simple multivalue elements (checkboxes).
   *
   * @param array $value
   *   (By reference) the form element value.
   *   Will be altered to newline seperated string if meets simple case.
   *
   * @return bool
   *   True if element meets simple case.
   */
  protected function handleElementSimpleCase(Array &$value) {
    $existing_value = $value;

    if (isset($value[0]) && !is_array($value[0])) {
      $value = implode("\n", $value);
    }

    return $existing_value == $value ? FALSE : TRUE;
  }

  /**
   * Handle special cases of multivalue elements.
   *
   * @param array $value
   *   (By reference) the form element value.
   *   Will be converted to a string if meets a special case.
   * @param array $webformElement
   *   The webform element details, gathered from $webform->getElement($key).
   *
   * @return bool
   *   True if element meets special case.
   */
  protected function handleElementSpecialCase(Array &$value, Array $webformElement) {
    $existing_value = $value;

    // Special case date of birth, seperate by / per element.
    // If multiple, seperate by newline.
    if ($webformElement['#type'] == 'bhcc_webform_date' || $webformElement['#type'] == 'bhcc_webform_date_of_birth') {
      if (isset($value[0])) {
        $value = implode("\n", array_map(function ($indv_value) {
          return $this->handleElementSpecialCaseDate($indv_value);
        }, $value));
      }
      else {
        $value = $this->handleElementSpecialCaseDate($value);
      }
    }

    return $existing_value == $value ? FALSE : TRUE;
  }

  /**
   * Handle element special case - BHCC Date.
   *
   * @param array $originalValue
   *   The original value array.
   *
   * @return Mixed
   *   Formatted date string as YYYY-MM-DD.
   *   Null if no dates entered - @see DRUP-1187
   */
  protected function handleElementSpecialCaseDate($originalValue) {

    // If all values are empty, return.
    if (empty($originalValue['year']) && empty($originalValue['month']) && empty($originalValue['day'])) {
      return NULL;
    }

    // Format the date field for contact management.
    $processValue['year'] = (!empty($originalValue['year']) ? $originalValue['year'] : date('Y'));
    $processValue['month'] = substr((!empty($originalValue['month']) ? '0' . $originalValue['month'] : date('m')), -2);
    $processValue['day'] = substr((!empty($originalValue['day']) ? '0' . $originalValue['day'] : date('d')), -2);
    return $processValue['year'] . '-' . $processValue['month'] . '-' . $processValue['day'];
  }

  /**
   * Handle special cases of multivalue composite elements.
   *
   * This is for where the answers need to appear under composite_answers.
   *
   * @todo this should really be handled in the custom element where possible.
   *
   * @param array $value
   *   (By reference) the form element value.
   * @param array $webformElement
   *   The webform element details, gathered from $webform->getElement($key).
   *
   * @return bool
   *   True if element meets special case.
   */
  protected function handleCompositeElementSpecialCase(Array &$value, Array $webformElement) {
    // If no type defined, then not a special case (simple composite).
    if (!isset($webformElement['#type'])) {
      return FALSE;
    }

    $existing_value = $value;
    // Address Lookup.
    if ($webformElement['#type'] == 'bhcc_central_hub_webform_uk_address') {
      if (isset($value[0])) {
        $value = array_map(function ($indv_value) use ($webformElement) {
          return $this->handleCompositeElementSpecialCaseAddress($indv_value, $webformElement);
        }, $value);
      }
      else {
        $value = $this->handleCompositeElementSpecialCaseAddress($value, $webformElement);
      }
    }

    return $existing_value == $value ? FALSE : TRUE;
  }

  /**
   * Handle composite element special case address.
   *
   * @param array $originalValue
   *   The original array value.
   * @param array $webformElement
   *   The webform element details, gathered from $webform->getElement($key).
   *
   * @return array
   *   Formatted composite value.
   */
  protected function handleCompositeElementSpecialCaseAddress($originalValue, $webformElement) {
    $processValue = [];
    foreach ($originalValue as $key => $value) {
      $question = $webformElement['#webform_composite_elements']['address_entry'][$key]['#title'] ?? '';

      // Only process if it is a question, that way the extra elements
      // can be ignored.
      // @See DRUP-1287.
      if (!empty($question)) {
        $processValue[] = [
          'question'     => $question,
          'answer'       => $value,
          'machine_name' => $key,
        ];
      }
    }
    return $processValue;
  }

  /**
   * Prepare the composite submission element payload.
   *
   * @param array $values
   *   User entered webform submission responses from composite webform element.
   * @param array $webformElement
   *   The webform element details, gathered from $webform->getElement($key).
   *
   * @return array
   *   Multidimensional array containing the payload for each part of the
   *   composite using $this->prepareResponsePayload, grouped by the
   *   submission delta.
   */
  protected function prepareCompositePayload(Array $values, Array $webformElement) {

    // If only a single composite field, wrap it into an array.
    if (!isset($values[0])) {
      $existing_values = $values;
      unset($values);
      $values[] = $existing_values;
    }

    $composite_value = [];

    // Assemble composite field.
    foreach ($values as $index => $composite_values) {

      // Loop through each delta of the submission to gather the payload.
      $group_value = [];

      // Check if special case and assign group value.
      if ($this->handleCompositeElementSpecialCase($composite_values, $webformElement)) {
        $group_value = $composite_values;
        // Else loop through and contruct composite.
      }
      else {
        foreach ($composite_values as $indv_key => $indv_value) {
          if (isset($webformElement['#webform_composite_elements'][$indv_key]['#title'])) {
            $indv_title = (string) $webformElement['#webform_composite_elements'][$indv_key]['#title'];
          }
          else {
            $indv_title = '';
          }
          $group_value[] = $this->prepareResponsePayload($indv_title, $indv_value, $indv_key, $webformElement);
        }
      }

      $composite_value[] = $group_value;
    }

    return $composite_value;
  }

  /**
   * Post form submission to case management.
   *
   * @param string $state
   *   (For future use):
   *   The state of the webform submission.
   *   Either STATE_NEW, STATE_DRAFT_CREATED, STATE_DRAFT_UPDATED,
   *   STATE_COMPLETED, STATE_UPDATED, or STATE_CONVERTED
   *   depending on the last save operation performed.
   * @param array $postData
   *   Post data from caseManagementPrepare.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform Submission.
   */
  protected function caseManagementPost($state, $postData, WebformSubmissionInterface $webform_submission) {

    $caseManagementURL = $this->getCaseManagementUrl();
    $authHeader = $this->getCaseManagementAuthHeader();

    $requestOptions = [
      'headers' => [
        'Authorization' => $authHeader,
      ],
      'json' => $postData,
    ];

    try {
      $response = $this->httpClient->post($caseManagementURL, $requestOptions);
    }
    catch (RequestException $request_exception) {
      $response = $request_exception->getResponse();

      // Due to response sometimes being null, set a default status code.
      // @see DRUP-1196.
      $status_code = (!empty($response) ? $response->getStatusCode() : 0);

      // Log the error.
      $context_options = [
        '@citizenidtoken' => $postData['citizenId'],
        '@statuscode' => $status_code,
        'operation' => 'error posting to case management',
      ];
      $this->logHandler($context_options, $webform_submission, "Error posting to case management.<br>Citizen ID token: @citizenidtoken.<br>Status code: @statuscode", 'error');

      // Encode HTML entities to prevent broken markup from breaking the page.
      $message = $request_exception->getMessage();
      $message = nl2br(htmlentities($message));

      $this->handleError($state, $message, $caseManagementURL, 'POST', 'json', $postData, $response);
      return;
    }

    // Get the status code.
    $status_code = $response->getStatusCode();

    // Log the submission.
    $context_options = [
      '@citizenidtoken' => $postData['citizenId'],
      '@statuscode' => $status_code,
      'operation' => 'posted to case management',
    ];
    $this->logHandler($context_options, $webform_submission, "Posted to case management.<br>Citizen ID token: @citizenidtoken.<br>Status code: @statuscode", 'notice');

    // Display submission exception if response code is not 2xx.
    // (from remotePostHandler)
    if ($status_code < 200 || $status_code >= 300) {
      $message = $this->t('Remote post request return @status_code status code.', ['@status_code' => $status_code]);
      $this->handleError($state, $message, $caseManagementURL, 'POST', 'json', $requestOptions, $response);
      return;
    }

    // Not the drupal way - clear down cookie.
    setcookie('citizenidtoken', '', time(), '/');
  }

  /**
   * Log Handler - Helper to log to webform submission log.
   *
   * @param array $context_options
   *   Array of additional context options.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission.
   * @param string $message
   *   Message to log, include @placeholder to add placeholders.
   * @param string $type
   *   Log message type, (info, notice, warning, error), default to info.
   */
  protected function logHandler($context_options, $webform_submission, $message, $type = 'info') {
    if ($webform_submission->getWebform()->hasSubmissionLog()) {
      $context = [
        'link' => ($webform_submission->id()) ? $webform_submission->toLink($this->t('View'))->toString() : NULL,
        'webform_submission' => $webform_submission,
        'handler_id' => $this->getHandlerId(),
      ] + $context_options;

      $logger = $this->getLogger('webform_submission');
      switch ($type) {
        case 'notice':
          $logger->notice($message, $context);
          break;

        case 'warning':
          $logger->warning($message, $context);
          break;

        case 'error':
          $logger->error($message, $context);
          break;

        default:
          $logger->info($message, $context);
      }
    }
  }

  /**
   * Handle error by logging and display debugging and/or exception message.
   *
   * Taken from \Drupal\webform\Plugin\WebformHandler\RemotePostWebformHandler.
   *
   * @param string $state
   *   The state of the webform submission.
   *   Either STATE_NEW, STATE_DRAFT_CREATED, STATE_DRAFT_UPDATED,
   *   STATE_COMPLETED, STATE_UPDATED, or STATE_CONVERTED
   *   depending on the last save operation performed.
   * @param string $message
   *   Message to be displayed.
   * @param string $request_url
   *   The remote URL the request is being posted to.
   * @param string $request_method
   *   The method of remote post.
   * @param string $request_type
   *   The type of remote post.
   * @param string $request_options
   *   The requests options including the submission data..
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The response returned by the remote server.
   */
  protected function handleError($state, $message, $request_url, $request_method, $request_type, $request_options, $response) {
    global $base_url, $base_path;

    // If debugging is enabled, display the error message on screen.
    // $this->debug($message, $state, $request_url, $request_method,
    // $request_type, $request_options, $response, 'error');.
    // Log error message.
    $context = [
      '@form' => $this->getWebform()->label(),
      '@state' => $state,
      '@type' => $request_type,
      '@url' => $request_url,
      '@message' => $message,
      'webform_submission' => $this->getWebformSubmission(),
      'handler_id' => $this->getHandlerId(),
      'operation' => 'error',
      'link' => $this->getWebform()
        ->toLink($this->t('Edit'), 'handlers')
        ->toString(),
    ];
    $this->getLogger('webform_submission')
      ->error('@form webform case management @type post (@state) to @url failed. @message', $context);

    $this->messageManager->display(WebformMessageManagerInterface::SUBMISSION_EXCEPTION_MESSAGE, 'error');

    // Redirect the current request to the error url.
    $error_url = '/citizenid-error/' . $this->getWebform()->id();
    // dpm($response);
    if ($error_url && PHP_SAPI !== 'cli') {
      // Convert error path to URL.
      if (strpos($error_url, '/') === 0) {
        $error_url = $base_url . preg_replace('#^' . $base_path . '#', '/', $error_url);
      }

      // Check if the form is ajax, as different redirects required.
      $request = \Drupal::request();
      if ($request->isXmlHttpRequest()) {
        // $url = Url::fromRoute('page_route');
        $ajaxResponse = new WebformSubmissionAjaxResponse();
        $command = new WebformRefreshCommand($error_url);
        $ajaxResponse->addCommand($command);
        // @todo fiqure out how to actully send this redirect.
        // $ajaxResponse->send();
      }
      else {
        $redirect = new TrustedRedirectResponse($error_url);
        $redirect->send();
      }

    }
  }

  /**
   * Get the Contact management group entity.
   *
   * @return \Drupal\bhcc_case_management\Entity\ContactManagementGroupEntityInterface|null
   *   Contact management group entity, or NULL if not set.
   */
  protected function getContactManagementGroup() {
    $cm_group_id = $this->configuration['contact_management_group'];
    if ($cm_group_id) {
      $cm_group = $this->entityTypeManager
        ->getStorage('bhcc_contact_management_group')
        ->load($cm_group_id);
    }
    return $cm_group ?? NULL;
  }

  /**
   * Get default case management url.
   *
   * @return string
   *   Default URL used for posting into case management.
   */
  protected function getDefaultCaseManagementUrl() {
    $config = $this->configFactory->get('bhcc_case_management.settings');
    return $config->get('case_management_post_url');
  }

  /**
   * Get case management url.
   *
   * @return string
   *   URL to post submission into case management.
   *   Will either be the overridden value, or if blank the default.
   */
  protected function getCaseManagementUrl() {
    $default = $this->getDefaultCaseManagementUrl();

    // Get the Contact management group post URL if set.
    $cm_group = $this->getContactManagementGroup();
    if ($cm_group) {
      $cm = $cm_group->getPostUrl();
    }

    $enable_override = $this->configuration['enable_override'];
    $override = $this->configuration['override_case_management_post_url'];

    // If override is enabled, return the override,
    // else the cm group, else the default.
    return (!empty($enable_override) ? $override : (!empty($cm) ? $cm : $default));
  }

  /**
   * Get default case management auth header.
   *
   * @return string
   *   The default auth header for posting into case management,
   */
  protected function getDefaultCaseManagementAuthHeader() {
    $config = $this->configFactory->get('bhcc_case_management.settings');
    return $config->get('case_management_auth_header');
  }

  /**
   * Get case management auth header.
   *
   * @return string
   *   Auth header to post into case management.
   *   Will either be overridden value, or if blank the default.
   */
  protected function getCaseManagementAuthHeader() {
    $default = $this->getDefaultCaseManagementAuthHeader();

    // Get the Contact management group auth header.
    $cm_group = $this->getContactManagementGroup();
    if ($cm_group) {
      $cm = $cm_group->getAuthHeader();
    }

    $enable_override = $this->configuration['enable_override'];
    $override = $this->configuration['override_case_management_auth_header'];

    // If override is enabled, return the override,
    // else the cm group, else the default.
    // Helper functions copied from RemotePostWebformHandler.
    return (!empty($enable_override) ? $override : (!empty($cm) ? $cm : $default));
  }

  /**
   * Determine if saving of results is enabled.
   *
   * @return bool
   *   TRUE if saving of results is enabled.
   */
  protected function isResultsEnabled() {
    return ($this->getWebform()->getSetting('results_disabled') === FALSE);
  }

  /**
   * Determine if saving of draft is enabled.
   *
   * @return bool
   *   TRUE if saving of draft is enabled.
   */
  protected function isDraftEnabled() {
    return $this->isResultsEnabled() && ($this->getWebform()->getSetting('draft') != WebformInterface::DRAFT_NONE);
  }

  /**
   * Determine if converting anonymous submissions to authenticated is enabled.
   *
   * @return bool
   *   TRUE if converting anonymous submissions to authenticated is enabled.
   */
  protected function isConvertEnabled() {
    return $this->isDraftEnabled() && ($this->getWebform()->getSetting('form_convert_anonymous') === TRUE);
  }

}
