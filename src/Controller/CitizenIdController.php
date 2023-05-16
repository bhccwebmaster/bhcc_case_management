<?php

namespace Drupal\bhcc_case_management\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactory;

/**
 * Class MendixController.
 */
class CitizenIdController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Entity Type Manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The contrustor for the citizenIdController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The configuration factory.
   */
  public function construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $configFactory) {

    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
  }

  /**
   * Verify Citizen ID.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  public function verify(Request $request) {

    // Get drupal cookie vars from request.
    $cookieVars = $request->cookies->all();
    $queryVars = $request->query->all();

    // Verifiy the citizenID token.
    if (!empty($cookieVars['citizenidtoken']) || !empty($queryVars['citizenidtoken'])) {
      // @todo add verification that citizenID is valid.
      $has_citizenid = TRUE;
    }
    else {
      $has_citizenid = FALSE;
    }

    if (!empty($queryVars['citizenidtoken'])) {
      $citizenID = $queryVars['citizenidtoken'];
    }
    elseif (empty($queryVars['citizenidtoken']) && !empty($cookieVars['citizenidtoken'])) {
      $citizenID = $cookieVars['citizenidtoken'];
    }
    else {
      $citizenID = NULL;
    }

    new Cookie('citizenidtoken', $citizenID, '+6 Hours', '/');

    // Get the Desintation Parameter.
    $destination_str = $this->getRedirectDestination()->get();
    // Make sure destination has a prefixed /.
    $destination_str = strpos($destination_str, '/') === 0 ? $destination_str : '/' . $destination_str;
    $destination = Url::fromUserInput($destination_str);

    // Validate destination is a valid webform.
    if ($destination->isRouted()) {
      $routeName = $destination->getRouteName();
      $parameters = $destination->getRouteParameters();

      // Check that this is a webform or webform node.
      if ($routeName == 'entity.webform.canonical' && !empty($parameters['webform'])) {
        $valid_path = TRUE;
      }
      elseif ($routeName == 'entity.node.canonical' && !empty($parameters['node'])) {
        $node = $this->entityTypeManager->getStorage('node')->load($parameters['node']);
        if ($node->bundle() == 'webform') {
          $valid_path = TRUE;
        }
        else {
          $valid_path = FALSE;
        }
      }
      else {
        $valid_path = FALSE;
      }
    }
    else {
      $valid_path = FALSE;
    }

    // Pass through query string.
    $options = [];
    $options['query'] = $queryVars;
    unset($options['query']['destination']);
    unset($options['query']['citizenidtoken']);

    // Do redirects.
    if (!$has_citizenid || !$valid_path) {
      // Do direct redirect as this overrides the destination paremeter.
      $response = new RedirectResponse('/citizenid-error');
      $response->send();

      // Still have to return from the controller.
      // @see DRUP-1155
      // @see https://github.com/bhccwebmaster/bhcclocalgov/issues/1253
      return $response;
    }

    // Definately not the Drupal way of doing things!!!
    setcookie('citizenidtoken', $citizenID, time() + 60 * 60 * 3, '/');

    // Send a redirect response instead of returning so query string is passed.
    $response = $this->redirect($routeName, $parameters, $options);
    $response->send();

    // Still have to return from the controller.
    // @see DRUP-1155
    return $response;
  }

  /**
   * Generic error page.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  public function error(Request $request) {

    $output = '<p>There was an error with Citizen ID</p>';

    // Return output to browser.
    return [
      '#type' => 'markup',
      '#markup' => $output,
    ];
  }

  /**
   * Error with webform.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param Drupal\webform\Entity\WebformInterface $webform
   *   Webform object.
   */
  public function errorWebform(Request $request, WebformInterface $webform) {

    $output = '<p>' . $this->t('You cannot access the form at this time.') . '</p>';

    // Return output to browser.
    return [
      '#type' => 'markup',
      '#markup' => $output,
    ];
  }

  /**
   * Error page title.
   *
   * @param Drupal\webform\Entity\WebformInterface $webform
   *   Webform object.
   */
  public function errorWebformTitle(WebformInterface $webform) {

    return $this->t('Error with') . $webform->label() . $this->t('and Citizen ID');
  }

}
