<?php

namespace Drupal\dcx_unpublish_media\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class JsonClientSettings.
 *
 * @package Drupal\dcx_integration\Form
 */
class UnpublishMediaSettings extends ConfigFormBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setEntityTypeManager($container->get('entity_type.manager'));
    return $form;
  }

  /**
   * Set the entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  protected function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

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

    /** @var \Drupal\media\MediaTypeInterface[] $bundles */
    $bundles = $this->entityTypeManager
      ->getStorage('media_type')
      ->loadMultiple();
    $imageBundles = [];
    foreach ($bundles as $bundle) {
      if ($bundle->get('source') == 'image') {
        $imageBundles[] = $bundle->id();
      }
    }
    $defaultValue = NULL;
    if ($config->get('default_image')) {
      $defaultValue = $this->entityTypeManager->getStorage('media')->load($config->get('default_image'));
    }

    $form['default_image'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Default image'),
      '#default_value' => $defaultValue,
      '#target_type' => 'media',
      '#selection_settings' => ['target_bundles' => $imageBundles],
    ];

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
