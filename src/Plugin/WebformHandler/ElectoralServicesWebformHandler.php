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
 * Webform submission electoral services post handler.
 *
 * @WebformHandler(
 *   id = "bhcc_electoral_services",
 *   label = @Translation("Electoral Services Webform Handler"),
 *   category = @Translation("BHCC"),
 *   description = @Translation("Generates a flag file once a submission has been processed."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = FALSE,
 * )
 */
class ElectoralServicesWebformHandler extends WebformHandlerBase {

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

    // Set Boomi holding folder
    $sftp = "private://boomi/";

    // Retrieve data from form submission and set timestamp
    $auth = $webform_submission->getData()['authority'];
    $email = $webform_submission->getData()['email_address'];
    $date = date("YmdHis", time());
    $fid = $webform_submission->getData()['file_upload'];

    // Only process files if File ID, Authority and Email address are present
    if($fid && $auth && $email) {

      // Retrieve file and generate new file name
      $file = \Drupal\file\Entity\File::load($fid);
      $fileUri = $file->getFileUri();
      $fileNameExt = $file->getFileName();
      $fileName = $auth . "-" . $date;
      $fileParts = pathinfo($fileUri);
      $fileExt = $fileParts['extension'];
      $fileDestination = $sftp . $fileName . ".csv";

      // Move file form original location to Boomi holding folder
      $rename = rename($fileUri, $fileDestination);

      // If move is successful, write flag file with the same name to the same location
      if ($rename) {

        $flagDestination = $sftp . $fileName . ".FLAG";
        $fileSaveData = writeData( $email . PHP_EOL . $fileNameExt, $flagDestination, FileSystemInterface::EXISTS_REPLACE);

        // Display error message if fileSaveData returns false
        if (!$fileSaveData) {
          \Drupal::messenger()->addError(t('There has been an issue with your upload, please try again (no flag object).'));
        }

      }

      // Display error message if rename returns false
      else {
        \Drupal::messenger()->addError(t('There has been an issue with your upload, please try again (no file object).'));
      }

    }

    // Display error if file, authority or email is missing
    else {
      \Drupal::messenger()->addError(t('There has been an issue with your upload, please try again (no file, email or authority).'));
    }

  }

}
