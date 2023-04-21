<?php

namespace Drupal\bhcc_case_management\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Webform submission Covid Fund post handler.
 *
 * @WebformHandler(
 *   id = "bhcc_document",
 *   label = @Translation("Case Management Document Upload Webform Handler"),
 *   category = @Translation("BHCC"),
 *   description = @Translation("Outputs uploaded documents to Boomi directory."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class CaseManagementDocumentHandler extends WebformHandlerBase {

  // @todo make configurable.
  const UPLOAD_DIR = 'private://boomi/';

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
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The webform.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $webform;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    $instance->messageManager = $container->get('webform.message_manager');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    // Default values for overrides (blank)
    return [
      'zip_directory' => self::UPLOAD_DIR,
      'zip_filename'  => '[webform_submission:values:casekey]',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $webform = $this->getWebform();

    $msg_1 = 'Must begin with either private:// or public://';

    // Case Management web service overrides.
    $form['zip'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Zip file settings'),
    ];
    $form['zip']['zip_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory'),
      '#description' => $this->t('Location to upload zip archive files to.') . '<br>' . ($msg_1 . '<br>' . $this->t('Do not include the trailing slash.')),
      '#size' => 64,
      '#default_value' => $this->configuration['zip_directory'],
      '#required' => TRUE,
    ];

    $form['zip']['zip_filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filename'),
      '#description' => $this->t('Filename for the zip archive.') . '<br>' . $this->t('Use tokens from the webform submission to make it unique.') . '<br>' . $this->t('Do not include the .zip extension.'),
      '#size' => 64,
      '#default_value' => $this->configuration['zip_filename'],
      '#required' => TRUE,
    ];

    $this->elementTokenValidate($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    // Validate zip_directory starts with public:// or private://.
    if (strpos($form_values['zip_directory'], 'public://') !== 0 && strpos($form_values['zip_directory'], 'private://') !== 0) {
      $form_state->setErrorByName('settings[zip_directory]', $this->t('Zip directory must begin with either private:// or public://'));
    }

    // Strip out the trailing slash on the directory, no need to flag as error.
    $form_state->setValue('zip_directory', rtrim($form_values['zip_directory'], '/'));

    // Strip out the trailing .zip extension on the filename,
    // no need to flag as error.
    $form_state->setValue('zip_filename', rtrim($form_values['zip_filename'], '.zip'));
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

    // Get submission data.
    $submission_data = $webform_submission->getData();

    // Set filenames.
    $temporary_file = 'temporary://' . uniqid();
    $zip_directory = $this->replaceTokens($this->configuration['zip_directory'], $webform_submission);
    $zip_filename = $this->replaceTokens($this->configuration['zip_filename'], $webform_submission);

    // Generate Zip file as temporary file for coping.
    $zip_uri = $zip_directory . '/' . $zip_filename . '.zip';
    $zip = new \ZipArchive();
    $zip->open($this->fileSystem->realpath($temporary_file), \ZipArchive::CREATE);

    // Iterate through webform submission fields.
    foreach ($submission_data as $key => $value) {
      // Get the webform element.
      $webformElement = $this->webform->getElement($key);

      // If this is a document uploader field.
      if ($webformElement['#type'] == 'webform_document_file') {
        // Add files to ZipArchive.
        if (is_int($value)) {
          $this->addFiletoZip($value, $zip, $key);
        }
        elseif (is_array($value)) {
          // Array walk to handle multiple.
          array_walk_recursive($value, function ($fid, $index) use ($zip, $key) {
            if (is_int($fid)) {
              $this->addFiletoZip($fid, $zip, $key);
            }
          });
        }
      }
    }

    $zip->close();

    // If there is no tempoary file, then just return.
    // This is to avoid error when no documents uploaded.
    if (!file_exists($this->fileSystem->realpath($temporary_file))) {
      return;
    }

    // If destination directory does not exist, create it.
    $this->fileSystem->prepareDirectory($zip_directory, FileSystemInterface::CREATE_DIRECTORY);

    // Copy the temporary zip to the correct location,
    // renaming the file if required.
    $copied_file = $this->fileSystem->copy($temporary_file, $zip_uri, FileSystemInterface::EXISTS_RENAME);
    $copied_file = $this->fileSystem->basename($copied_file, '.zip');

    // Set FLAG file destination.
    $flag_destination = $zip_directory . '/' . $copied_file . ".FLAG";

    // Generate flag file and populate flag file with zip filename.
    $fileSaveData = $this->fileSystem->saveData($copied_file . '.zip' . PHP_EOL . $zip_filename, $flag_destination, FileSystemInterface::EXISTS_REPLACE);

  }

  /**
   * Add file to a zip archive.
   *
   * @param int $fid
   *   File ID to add.
   * @param \ZipArchive $zip
   *   Zip archive to add files to.
   * @param string $field_key
   *   Key of the webform field, used to add a directory.
   */
  protected function addFiletoZip(int $fid, \ZipArchive $zip, string $field_key) {
    $file = File::load($fid);
    $localname = $field_key . '/' . $file->getFilename();
    $filename = $this->fileSystem->realpath($file->getFileUri());
    $zip->addFile($filename, $localname);
  }

}
