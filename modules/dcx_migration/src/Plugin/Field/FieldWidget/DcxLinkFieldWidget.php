<?php

namespace Drupal\dcx_migration\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class DcxLinkFieldWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ConfigFactoryInterface $configFactory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = [];

    $dcxUrl = $this->configFactory->get('dcx_integration.jsonclientsettings')->get('url');

    $baseUrl = pathinfo($dcxUrl)['dirname'];

    $document = str_replace('dcxapi:document', 'doc', $items[$delta]->value);

    $element['value'] = $element + [
      '#title' => $this->t('View in DCX'),
      '#type' => 'link',
      '#url' => Url::fromUri($baseUrl . "/documents#/" . $document),
      '#attributes' => ['target' => '_blank'],
    ];

    return $element;
  }

}
