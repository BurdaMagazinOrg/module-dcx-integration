<?php

/**
 * @file
 * Contains \Drupal\dcx_article_import\Form\ArticleImportForm.
 */

namespace Drupal\dcx_article_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dcx_integration\ClientInterface;

/**
 * Class ArticleImportForm.
 *
 * @package Drupal\dcx_article_import\Form
 */
class ArticleImportForm extends FormBase {

  /**
   * Drupal\dcx_integration\ClientInterface definition.
   *
   * @var Drupal\dcx_integration\ClientInterface
   */
  protected $dcx_integration_client;

  public function __construct(ClientInterface $dcx_integration_client) {
    $this->dcx_integration_client = $dcx_integration_client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcx_integration.client')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'article_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['dcx_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('DC-X ID'),
      '#description' => 'Please give a DC-X story document id. Something like "document/doc6p9gtwruht4gze9boxi".',
      '#maxlength' => 64,
      '#size' => 64,
    );
    $form['actions'] = array(
      '#type' => 'actions',
      'submit' => array(
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $dcx_id = $form_state->getValue('dcx_id');
    try {
      $object = $this->dcx_integration_client->getObject('dcxapi:' . $dcx_id);
    }
    catch (\Exception $e) {
      dpm($e->getMessage());
    }
    dpm($object);
  }

}
