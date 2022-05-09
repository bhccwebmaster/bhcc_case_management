<?php

namespace Drupal\bhcc_case_management\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\file\Entity\File;
use Drupal\Core\Controller\ControllerBase;

/**
 * Class CaseManagementCovidFileRetriveController.
 */
class CaseManagementCovidFileRetriveController extends ControllerBase {

  const GRANT_FOLDER = 'private://casemanagement/covid-business-grants/';

  /**
   * Retrive.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @return filestream
   *   If access granted, the file stream.
   */
  public function retrive(Request $request) {

    $queryVars = $request->query->all();

    if (empty($queryVars['file'])) {
      // @todo - should 404
      throw new NotFoundHttpException();
      return [];
    }

    // Load the file.
    $flagFile = $this->getFile($queryVars['file'] . '.flag');
    $zipFile = $this->getFile($queryVars['file'] . '.zip');

    if (empty($zipFile)) {
      // check if the file exits on disk...

      // Call file system service
      $fileSystem = \Drupal::service('file_system');
      $zipUrl = $fileSystem->realpath(self::GRANT_FOLDER . $queryVars['file'] . '.zip');
      if (!file_exists($zipUrl)) {
        throw new NotFoundHttpException();
      }
      $this->saveFile($queryVars['file'] . '.zip');
      $zipFile = $this->getFile($queryVars['file'] . '.zip');
    }

    // Return the zip file - @todo add extra security checks - talk to RB.
    $headers = [
      'Content-Type'        => $zipFile->get('filemime')->value,
      'Content-Disposition' => 'attachment;filename="' . $zipFile->get('filename')->value . '"',
    ];

    return new BinaryFileResponse($zipFile->get('uri')->value, 200, $headers, true);
  }

  /**
   * Get File helper
   * @param  string $filename
   *   Filename.
   * @return mixed
   *   File object if found, else FALSE.
   */
  protected function getFile($filename) {
    $fileUri = self::GRANT_FOLDER . $filename;
    $fileArr = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $fileUri]);
    return (is_array($fileArr) ? reset($fileArr) : FALSE);
  }

  /**
   * Save file to DB helper
   * @param  string $filename
   *   Filename.
   */
  protected function saveFile($filename) {
    $file = File::create([
      'uid' => \Drupal::currentUser()->id(),
      'filename' => $filename,
      'uri' => self::GRANT_FOLDER . $filename,
      'status' => 1,
    ]);
    $file->save();
  }

}
