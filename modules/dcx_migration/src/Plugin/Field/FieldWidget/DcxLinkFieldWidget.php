<?php

namespace Drupal\dcx_migration\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'dcx_link_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "dcx_link_field_widget",
 *   label = @Translation("DCX-Link"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class DcxLinkFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = [];

    $dcxUrl = \Drupal::config('dcx_integration.jsonclientsettings')->get('url');

    $baseUrl = pathinfo($dcxUrl)['dirname'];

    $document = str_replace('dcxapi:document', 'doc', $items[$delta]->value);

    $element['value'] = $element + array(
      '#title' => t('View in DCX'),
      '#type' => 'link',
      '#url' => Url::fromUri($baseUrl . "/documents#/" . $document),
      '#attributes' => ['target' => '_blank'],
    );

    return $element;
  }

}
