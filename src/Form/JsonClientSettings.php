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
      '#required' => TRUE,
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

    $form['notification_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notification settings'),
    ];
    $form['notification_settings']['notification_access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DCX notification access key'),
      '#default_value' => $config->get('notification_access_key'),
      '#required' => TRUE,
      '#size' => 25,
    ];
    // Add a submit handler function for the key generation.
    $form['notification_settings']['create_key'][] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate new random key'),
      '#submit' => ['::generateRandomKey'],
      // No validation at all is required in the equivocate case, so
      // we include this here to make it skip the form-level validator.
      '#validate' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form submission handler for the random key generation.
   *
   * This only fires when the 'Generate new random key' button is clicked.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function generateRandomKey(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dcx_integration.jsonclientsettings');
    $config->set('notification_access_key', substr(md5(rand()), 0, 20));
    $config->save();
    parent::submitForm($form, $form_state);
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
      ->set('notification_access_key', trim($form_state->getValue('notification_access_key')))
      ->save();
  }

}
