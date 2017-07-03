<?php

namespace Drupal\dcx_dropzone_ui\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a Dropzone for DC-X import.
 *
 * @FormElement("dcxdropzone")
 */
class DcxDropzone extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#input' => TRUE,
      '#process' => [[$class, 'processElement']],
      '#pre_render' => [[$class, 'preRenderElement']],
      '#theme' => 'dcxdropzone',
      '#theme_wrappers' => ['form_element'],
      '#attached' => [
        'library' => [
          'dcx_dropzone_ui/dropzone',
          'core/drupal.batch',
          'dcx_dropzone_ui/ajax.batch',
        ],
      ],
      '#tree' => TRUE,
    ];
  }

  /**
   * Callback #process for dropvalue form element property.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processElement(array $element, FormStateInterface $form_state, array $complete_form) {
    $element['#element_validate'][] = [get_called_class(), 'validateInput'];
    $element['dropvalue'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    return $element;
  }

  /**
   * Callback #pre_render for dropvalue form element property.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   *
   * @return array
   *   The processed element.
   */
  public static function preRenderElement(array $element) {
    $element['#attached']['drupalSettings']['dcx_dropzone'] = [
      'dropzone_id' => $element['#id'],
      'value_name' => $element['dropvalue']['#name'],
    ];
    return $element;
  }

  /**
   * Validation callback for dropvalue form element property.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateInput(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $user_input = NestedArray::getValue($form_state->getUserInput(), $element['#parents'] + ['dropvalue']);

    $value = $user_input['dropvalue'];
    $form_state->setValueForElement($element, $value);
  }

}
