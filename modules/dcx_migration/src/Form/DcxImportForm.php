<?php

namespace Drupal\dcx_migration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcx_migration\DcxImportServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DcxImportForm.
 *
 * @package Drupal\dcx_migration\Form
 */
class DcxImportForm extends FormBase {

  protected $importService;

  /**
   * Constructor.
   *
   * @param \Drupal\dcx_migration\DcxImportServiceInterface $importService
   *   The DCX Import Service actually processing the input.
   */
  public function __construct(DcxImportServiceInterface $importService) {
    $this->importService = $importService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('dcx_migration.import'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dcx_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['id'] = [
      '#title' => $this->t('DC-X ID'),
      '#description' => $this->t('Please give a DC-X image document id. Something like "document/doc6p9gtwruht4gze9boxi". You may enter multiple document ids separated by comma.'),
      '#type' => 'textfield',
      '#required' => TRUE,
    ];
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
    $input = $form_state->getValue('id');

    $ids = [];
    foreach (explode(',', $input) as $id) {
      $ids[] = "dcxapi:" . trim($id);
    }

    $this->importService->import($ids);
  }

}
