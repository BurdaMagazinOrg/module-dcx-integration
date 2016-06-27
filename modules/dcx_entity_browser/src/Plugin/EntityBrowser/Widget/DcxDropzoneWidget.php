<?php

namespace Drupal\dcx_entity_browser\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\View;

/**
 * Provides an Entity Browser widget that imports files from dcx.
 *
 * @EntityBrowserWidget(
 *   id = "dcx_dropzone",
 *   label = @Translation("DCX Dropzone"),
 *   description = @Translation("Adds DCX Dropzone import integration.")
 * )
 */
class DcxDropzoneWidget extends View {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'dropzone_description' => t('Drop DCX images here to upload them'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {
    $config = $this->getConfiguration();

    $form['dcx_dropzone_widget'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => 'dcx-dropzone-widget',
      ),
    );

    $form['dcx_dropzone_widget']['dropzone'] = [
      '#title' => '',
      '#dropzone_description' => $config['settings']['dropzone_description'],
      '#type' => 'dcxdropzone',
    ];

    $form['dcx_dropzone_widget'] += parent::getForm($original_form, $form_state, $aditional_widget_parameters);

    $form['#attached']['library'][] = 'dcx_entity_browser/dcx_entity_browser';

    return $form;
  }

}
