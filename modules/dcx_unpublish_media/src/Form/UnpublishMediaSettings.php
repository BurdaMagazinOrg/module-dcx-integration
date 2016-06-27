<?php

namespace Drupal\dcx_unpublish_media\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class JsonClientSettings.
 *
 * @package Drupal\dcx_integration\Form
 */
class UnpublishMediaSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dcx_unpublish_media.unpublishmediasettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dcx_unpublish_media';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('dcx_unpublish_media.unpublishmediasettings');

    /** @var MediaBundle[] $bundles */
    $bundles = \Drupal::entityTypeManager()
      ->getStorage('media_bundle')
      ->loadMultiple();
    $imageBundles = array();
    foreach ($bundles as $bundle) {
      if ($bundle->get('type') == 'image') {
        $imageBundles[] = $bundle->id();
      }
    }

    $form['default_image'] = array(
      '#type' => 'entity_autocomplete',
      '#title' => t('Default image'),
      '#default_value' => ($config->get('default_image')) ? entity_load('media', $config->get('default_image')) : NULL,
      '#target_type' => 'media',
      '#selection_settings' => ['target_bundles' => $imageBundles],
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('dcx_unpublish_media.unpublishmediasettings')
      ->set('default_image', $form_state->getValue('default_image'))
      ->save();
  }

}
