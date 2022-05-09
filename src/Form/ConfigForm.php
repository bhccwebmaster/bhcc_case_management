<?php

namespace Drupal\bhcc_case_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ConfigForm.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bhcc_case_management.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bhcc_case_management_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bhcc_case_management.settings');
    $form['citizen_id_service_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Citizen ID Service URL'),
      '#description' => $this->t('The URL of the Citizen ID service to use.'),
      '#size' => 64,
      '#default_value' => $config->get('citizen_id_service_url'),
    ];
    $form['case_management_post_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Case management service post URL'),
      '#description' => $this->t('The URL to post form responses into case management.'),
      '#size' => 64,
      '#default_value' => $config->get('case_management_post_url'),
    ];
    $form['case_management_auth_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Case management service authorization header'),
      '#description' => $this->t('The header to add to authorize posts to case management.'),
      '#size' => 64,
      '#default_value' => $config->get('case_management_auth_header'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('bhcc_case_management.settings')
      ->set('citizen_id_service_url', $form_state->getValue('citizen_id_service_url'))
      ->set('case_management_post_url', $form_state->getValue('case_management_post_url'))
      ->set('case_management_auth_header', $form_state->getValue('case_management_auth_header'))
      ->save();
  }

}
