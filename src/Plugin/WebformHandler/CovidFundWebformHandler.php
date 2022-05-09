<?php

namespace Drupal\bhcc_case_management\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\webform\Ajax\WebformRefreshCommand;
use Drupal\webform\Ajax\WebformSubmissionAjaxResponse;
use Drupal\Core\Url;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformMessageManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Webform submission Covid Fund post handler.
 *
 * @WebformHandler(
 *   id = "bhcc_covid_fund",
 *   label = @Translation("Covid Fund Webform Handler"),
 *   category = @Translation("BHCC"),
 *   description = @Translation("Outputs documents from Covid Fund form to Boomi folder."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = FALSE,
 * )
 */
class CovidFundWebformHandler extends WebformHandlerBase {

  const GRANT_FOLDER = 'private://casemanagement/covid-business-grants/';

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient =   $container->get('http_client');
    $instance->messageManager = $container->get('webform.message_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    // Call file system service
    $file_system = \Drupal::service('file_system');

    // Call the archiver service
    $archiver = \Drupal::service('plugin.manager.archiver');

    // Set Boomi holding folder
    $sftp = "private://boomi/";

    // Retrieve, process and set company name
    $company = $webform_submission->getData()['company_name_'];
    $companyName =  preg_replace('/[^a-zA-Z0-9]/','', $company);

    // Retrieve and set email
    $email = $webform_submission->getData()['your_email'];

    // Set timestamp
    $date = date("YmdHis", time());

    // Generate unique ID
    $caseKey = $webform_submission->getData()['casekey'];
    $uniqueId = $caseKey; // $companyName . $date;

    // Retrieve document FIDs
    $bankFid = $webform_submission->getData()['bank_statement_file'];
    $accountsFid = $webform_submission->getData()['accounts_file'];
    $cashflowFid = $webform_submission->getData()['cashflow_forecast_file'];

    // Add document FIDs to array
    $fid = [
      "bank" => $bankFid,
      "accounts" => $accountsFid,
      "cashflow" => $cashflowFid,
    ];

    // Generate Zip file
    $zipUri = $file_system->saveData('', $sftp . $uniqueId . '.zip', FileSystemInterface::EXISTS_RENAME);
    if (!$zipUri) {
      \Drupal::messenger()->addError('Can\'t create zip file');
      return;
    }


    // Only process files if File ID, Company and Email address are present
    if($fid && $company && $email) {

      // Get ZipArchive object
      $zip = $archiver->getInstance(['filepath' => $file_system->realpath($zipUri)])->getArchive();

      // Add files to ZipArchive - Array walk to handle multiple.
      array_walk_recursive($fid, function($value, $index) use($file_system, $zip) {

        if($value) {
          $file = \Drupal\file\Entity\File::load($value);
          $localname = $file->getFilename();
          $filename = $file_system->realpath($file->getFileUri());
          $zip->addFile($filename, $localname);
        }
      });

      $zip->close();


      // Set FLAG file destination
      $flagDestination = $sftp . $uniqueId . ".FLAG";

      // Generate flag file and populate flag file with email, unique ID and zip filename
      $fileSaveData = file_save_data( $email . PHP_EOL . $uniqueId . '.zip' . PHP_EOL . $uniqueId, $flagDestination, FileSystemInterface::EXISTS_REPLACE);

      // Copy to new location - @todo reverse these so only the copies are managed as boomi ones get deleted.
      $copiedfile = $file_system->copy($zipUri, self::GRANT_FOLDER . $uniqueId . '.zip');
      $this->save_file_to_db($uniqueId . '.zip');
      $copiedflag = $file_system->copy($flagDestination, self::GRANT_FOLDER . $uniqueId . '.flag');
      $this->save_file_to_db($uniqueId . '.flag');
    }

    // Display error if file, authority or email is missing
    else {
      \Drupal::messenger()->addError(t('There has been an issue with your upload, please try again (no files, email or company name).'));
    }

  }

  /**
   * Save file to DB helper
   * @param  string $filename
   *   Filename.
   */
  protected function save_file_to_db($filename) {
    $file = File::create([
      'uid' => \Drupal::currentUser()->id(),
      'filename' => $filename,
      'uri' => self::GRANT_FOLDER . $filename,
      'status' => 1,
    ]);
    $file->save();
  }

}
