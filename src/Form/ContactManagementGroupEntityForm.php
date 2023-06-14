<?php

namespace Drupal\bhcc_case_management\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Contact management group entity add / edit form.
 */
class ContactManagementGroupEntityForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $contact_management_group = $this->entity;

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group name'),
      '#default_value' => $contact_management_group->label(),
      '#description' => $this->t('A unique name for this Contact management group.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $contact_management_group->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => [
          'Drupal\bhcc_case_management\Entity\ContactManagementGroupEntity',
          'load',
        ],
        'source' => ['name'],
      ],
      '#description' => $this->t('A unique machine-readable name for this Contact management group. It must only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$contact_management_group->isNew(),
    ];

    $form['post_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact management service post URL'),
      '#description' => $this->t('The URL to post form responses into Contact management.'),
      '#size' => 64,
      '#default_value' => $contact_management_group->getPostUrl(),
    ];
    $form['auth_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact management service authorization header'),
      '#description' => $this->t('The header to add to authorize posts to Contact management.'),
      '#size' => 64,
      '#default_value' => $contact_management_group->getAuthHeader(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $contact_management_group = $this->entity;

    // Set the ID if this is a new entity.
    if ($contact_management_group->isNew()) {
      $contact_management_group->setId($contact_management_group->id());
    }

    $status = $contact_management_group->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created Contact management group %label.', [
          '%label' => $contact_management_group->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved Contact management group %label.', [
          '%label' => $contact_management_group->label(),
        ]));
    }
    $form_state->setRedirectUrl($contact_management_group->toUrl('collection'));
  }

}
