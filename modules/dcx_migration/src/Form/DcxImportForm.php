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

  /**
   * The DCX Import Service actually processing the input.
   *
   * @var \Drupal\dcx_migration\DcxImportServiceInterface
   */
  protected $importService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setDcxImportService($container->get('dcx_migration.import'));
    return $form;
  }

  /**
   * Set the import service.
   *
   * @param \Drupal\dcx_migration\DcxImportServiceInterface $importService
   *   The DCX Import Service actually processing the input.
   */
  protected function setDcxImportService(DcxImportServiceInterface $importService) {
    $this->importService = $importService;
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
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
      ],
    ];

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
