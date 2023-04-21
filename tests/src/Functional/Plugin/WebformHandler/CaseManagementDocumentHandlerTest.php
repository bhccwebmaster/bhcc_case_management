<?php

namespace Drupal\Tests\bhcc_case_management\Functional\Plugin\WebformHandler;

use Drupal\Core\Serialization\Yaml;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Archiver\Zip;
use Drupal\bhcc_case_management\Plugin\WebformHandler\CaseManagementDocumentHandler;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Functional rests for case management document handler.
 *
 * @coversDefaultClass Drupal\bhcc_case_management\Plugin\WebformHandler\CaseManagementDocumentHandler
 * @group bhcc_case_management
 */
class CaseManagementDocumentHandlerTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['webform', 'file', 'bhcc_case_management'];

  /**
   * Theme to enable.
   *
   * @var array
   */
  protected $defaultTheme = 'stark';

  /**
   * File system service.
   *
   * @var Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Get documument upload handler settings.
   *
   * @param string $test_dir
   *   The test zip directory for config.
   * @param string $test_file
   *   The test zip filename for config.
   *
   * @return Drupal\bhcc_case_management\Plugin\WebformHandler\CaseManagementDocumentHandler
   *   Document uploader handler.
   */
  protected function getDocumentUploadHandler(string $test_dir, string $test_file) : CaseManagementDocumentHandler {
    $handler_manager = \Drupal::service('plugin.manager.webform.handler');
    $handler_configuration = [
      'id' => 'bhcc_document',
      'label' => 'Case Management Document Upload Webform Handler',
      'handler_id' => 'case_management_document_upload_webform_handler',
      'status' => TRUE,
      'conditions' => [],
      'weight' => 0,
      'settings' => [
        'zip_directory' => $test_dir,
        'zip_filename' => $test_file,
      ],
    ];

    // Make sure the directory exists, as it will be deleted between tests.
    $this->fileSystem->prepareDirectory($test_dir, FileSystemInterface::CREATE_DIRECTORY);

    // Return the handler.
    return $handler_manager->createInstance('bhcc_document', $handler_configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {

    parent::setUp();

    // @todo dependency inject file system service.
    $this->fileSystem = \Drupal::service('file_system');

  }

  /**
   * Test the document uploader webform.
   */
  public function testDocumentUploadHandler() {

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Set up test directory and file.
    $test_dir = 'private://test';
    $test_file = 'uploaded-documents';

    // Create a webform with a webform_document_file field.
    // Build a render array of elements.
    $elements = [
      'document' => [
        '#type' => 'webform_document_file',
        '#title' => 'Documents',
      ],
    ];

    $handler = $this->getDocumentUploadHandler($test_dir, $test_file);

    $settings = Webform::getDefaultSettings();

    // Create a webform.
    $webform = Webform::create([
      'id' => 'test_document_uploader_basic',
      'title' => 'Test document uploader basic',
      'elements' => Yaml::encode($elements),
      'settings' => $settings,
    ]);

    $webform->addWebformHandler($handler);
    $webform->save();

    // Create a new submission.
    $this->postSubmissionTest($webform);

    // Assert Zip file is created.
    $this->assertFileExists($this->fileSystem->realpath($test_dir) . '/' . $test_file . '.zip');

    // Assert the Flag file is created.
    $this->assertFileExists($this->fileSystem->realpath($test_dir) . '/' . $test_file . '.FLAG');

    // Assert flag file contains the filename and the key.
    $expected_flag_content = $test_file . '.zip' . PHP_EOL . $test_file;
    $flag_contents = file_get_contents($this->fileSystem->realpath($test_dir) . '/' . $test_file . '.FLAG');
    $this->assertEquals($expected_flag_content, $flag_contents);

  }

  /**
   * Test the document uploader webform using tokens for the filename.
   */
  public function testDocumentUploadHandlerWithTokens() {

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Set up test directory and file.
    $test_dir = 'private://test';
    $test_file = '[webform_submission:values:casekey]';

    $casekey = $this->randomMachineName(16);

    // Create a webform with a webform_document_file field.
    // Build a render array of elements.
    $elements = [
      'document' => [
        '#type' => 'webform_document_file',
        '#title' => 'Documents',
      ],
      'casekey' => [
        '#type' => 'value',
        '#value' => $casekey,
      ],
    ];

    $handler = $this->getDocumentUploadHandler($test_dir, $test_file);

    $settings = Webform::getDefaultSettings();

    // Create a webform.
    $webform = Webform::create([
      'id' => 'test_document_uploader_basic',
      'title' => 'Test document uploader basic',
      'elements' => Yaml::encode($elements),
      'settings' => $settings,
    ]);

    $webform->addWebformHandler($handler);
    $webform->save();

    // Create a new submission.
    $this->postSubmissionTest($webform);

    // Assert Zip file is created.
    $this->assertFileExists($this->fileSystem->realpath($test_dir) . '/' . $casekey . '.zip');

    // Assert the Flag file is created.
    $this->assertFileExists($this->fileSystem->realpath($test_dir) . '/' . $casekey . '.FLAG');

    // Assert flag file contains the filename and the key.
    $expected_flag_content = $casekey . '.zip' . PHP_EOL . $casekey;
    $flag_contents = file_get_contents($this->fileSystem->realpath($test_dir) . '/' . $casekey . '.FLAG');
    $this->assertEquals($expected_flag_content, $flag_contents);

  }

  /**
   * Test the document uploader webform.
   */
  public function testDocumentUploadHandlerWithMultiple() {

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Set up test directory and file.
    $test_dir = 'private://test';
    $test_file = 'uploaded-documents';

    // Create a webform with a webform_document_file field.
    // Build a render array of elements.
    $elements = [
      'document' => [
        '#type' => 'webform_document_file',
        '#title' => 'Document',
      ],
      'another_document' => [
        '#type' => 'webform_document_file',
        '#title' => 'Another document',
      ],
      'more_documents' => [
        '#type' => 'webform_document_file',
        '#title' => 'More documents',
        '#multiple' => TRUE,
      ],
    ];

    $handler = $this->getDocumentUploadHandler($test_dir, $test_file);

    $settings = Webform::getDefaultSettings();

    // Create a webform.
    $webform = Webform::create([
      'id' => 'test_document_uploader_basic',
      'title' => 'Test document uploader basic',
      'elements' => Yaml::encode($elements),
      'settings' => $settings,
    ]);

    $webform->addWebformHandler($handler);
    $webform->save();

    // Create a new submission.
    $sid = $this->postSubmissionTest($webform);

    // Get the submitted files.
    $submission = WebformSubmission::load($sid);
    $submission_data = $submission->getData();

    // Unzip the archive to check the documents.
    $zip_archive = $test_dir . '/' . $test_file . '.zip';
    $zip = new Zip($this->fileSystem->realpath($zip_archive));
    $extract_root = $this->fileSystem->realpath($test_dir) . '/' . $test_file;
    $zip->extract($extract_root);

    // Check the files submitted are present in the extracted zip.
    // Load the file.
    $document_file = File::load($submission_data['document']);
    $document_filename = $document_file->getFilename();

    // Check exists.
    $this->assertFileExists($extract_root . '/document/' . $document_filename);

    // Check contents is the same.
    $document_contents = file_get_contents($document_file->getFileUri());
    $document_in_zip_contents = file_get_contents($extract_root . '/document/' . $document_filename);
    $this->assertEquals($document_contents, $document_in_zip_contents);

    // Rinse and repeat.
    $another_document_file = File::load($submission_data['another_document']);
    $another_document_filename = $another_document_file->getFilename();
    $this->assertFileExists($extract_root . '/another_document/' . $another_document_filename);
    $another_document_contents = file_get_contents($another_document_file->getFileUri());
    $another_document_in_zip_contents = file_get_contents($extract_root . '/another_document/' . $another_document_filename);
    $this->assertEquals($another_document_contents, $another_document_in_zip_contents);

    // Multiple files.
    foreach ($submission_data['more_documents'] as $more_file) {
      $more_document_file = File::load($more_file);
      $more_document_filename = $more_document_file->getFilename();
      $this->assertFileExists($extract_root . '/more_documents/' . $more_document_filename);
      $more_document_contents = file_get_contents($more_document_file->getFileUri());
      $more_document_in_zip_contents = file_get_contents($extract_root . '/more_documents/' . $more_document_filename);
      $this->assertEquals($more_document_contents, $more_document_in_zip_contents);
    }

  }

}
