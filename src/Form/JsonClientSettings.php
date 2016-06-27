<?php

namespace Drupal\dcx_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class JsonClientSettings.
 *
 * @package Drupal\dcx_integration\Form
 */
class JsonClientSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dcx_integration.jsonclientsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'json_client_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dcx_integration.jsonclientsettings');
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('url'),
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('username'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('password'),
    ];
    $form['publication'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publication'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('publication'),
      '#description' => $this->t('Machine name of the publication (this website) in DC-X, e.g "publication-freundin".'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $password = $form_state->getValue('password');
    if (empty($password)) {
      $password = $this->config('dcx_integration.jsonclientsettings')->get('password');
    }

    $this->config('dcx_integration.jsonclientsettings')
      ->set('url', $form_state->getValue('url'))
      ->set('username', $form_state->getValue('username'))
      ->set('password', $password)
      ->set('publication', trim($form_state->getValue('publication')))
      ->save();
  }

}
