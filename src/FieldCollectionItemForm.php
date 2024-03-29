<?php

namespace Drupal\field_collection;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

class FieldCollectionItemForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $field_collection_item = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em>', ['@type' => $field_collection_item->label()]);
    }

    /*
    // Basic item information.
    foreach (['revision_id', 'id', 'field_name'] as $key) {
      $form[$key] = [
        '#type' => 'value',
        '#value' => $field_collection_item->$key->value,
      ];
    }

    $language_configuration = module_invoke('language', 'get_default_configuration', 'field_collection_item', $field_collection_item->field_name->value);

    // Set the correct default language.
    if ($field_collection_item->isNew() && !empty($language_configuration['langcode'])) {
      $language_default = language($language_configuration['langcode']);
      $field_collection_item->langcode->value = $language_default->langcode;
    }

    $form['langcode'] = [
      '#title' => t('Language'),
      '#type' => 'language_select',
      '#default_value' => $field_collection_item->langcode->value,
      '#languages' => LANGUAGE_ALL,
      '#access' => isset($language_configuration['language_show']) && $language_configuration['language_show'],
    ];
    */

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Build the block object from the submitted values.
    parent::submitForm($form, $form_state);
    $field_collection_item = $this->entity;

    // TODO: Create new revision every edit?  Might be better to make it an
    // option.  In either case, it doesn't work as is.  The default
    // revision of the host isn't getting updated to point to the new
    // field collection item revision.
    // $field_collection_item->setNewRevision();

    $route_match = \Drupal::routeMatch();
    if ($route_match->getRouteName() == 'field_collection_item.add_page') {
      $host = $this->entityTypeManager->getStorage($route_match->getParameter('host_type'))->load($route_match->getParameter('host_id'));
    }
    else {
      $host = $field_collection_item->getHost();
    }

    $form_state->setRedirectUrl($host->toUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $field_collection_item = $this->getEntity();

    if ($field_collection_item->isNew()) {
      $host = $this->entityTypeManager->getStorage($this->getRequest()->get('host_type'))->load($this->getRequest()->get('host_id'));

      $field_collection_item->setHostEntity($host);
      $field_collection_item->save();
      $host->save();

      $messages = \Drupal::messenger()->all();
      if (!isset($messages['warning']) && !isset($messages['error'])) {
        \Drupal::messenger()->addMessage(t('Successfully added a @type.', ['@type' => $field_collection_item->bundle()]));
      }
    }
    else {
      $messages = \Drupal::messenger()->all();
      if (!isset($messages['warning']) && !isset($messages['error'])) {
        $field_collection_item->save();
        \Drupal::messenger()->addMessage(t('Successfully edited %label.', ['%label' => $field_collection_item->label()]));
      }
    }

    if ($field_collection_item->id()) {
      $form_state->setValue('id', $field_collection_item->id());
      $form_state->set('id', $field_collection_item->id());
    }
    else {
      // In the unlikely case something went wrong on save, the block will be
      // rebuilt and block form redisplayed.
      \Drupal::messenger()->addMessage(t('The field collection item could not be saved.'), 'error');

      $form_state->setRebuild();
    }

    /*
    $form_state->setRedirect(
      'field_collection_item.view',
      ['field_collection_item' => $field_collection_item->id()
    ]);
    */
  }

}
