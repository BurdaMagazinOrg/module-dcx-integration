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
        'library' => ['dcx_dropzone_ui/dropzone'],
      ],
      '#tree' => TRUE,
    ];
  }

  public static function processElement($element, FormStateInterface $form_state, $complete_form) {
    $element['#element_validate'][] = [get_called_class(), 'validateInput'];
    $element['dropvalue'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    return $element;
  }

  public static function preRenderElement($element) {
    $element['#attached']['drupalSettings']['dcx_dropzone'] = [
      'dropzone_id' => $element['#id'],
      'value_name' => $element['dropvalue']['#name'],
    ];
    return $element;
  }

  public static function validateInput(&$element, FormStateInterface $form_state, &$complete_form) {
    $user_input = NestedArray::getValue($form_state->getUserInput(), $element['#parents'] + ['dropvalue']);

    $value = $user_input['dropvalue'];
    $form_state->setValueForElement($element, $value);

    if (!empty($value)) {
      $form_state->setSubmitted();
    }

  }

}
