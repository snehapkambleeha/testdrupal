<?php

namespace Drupal\domain_config_ui_test\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * FormUnregistered configuration form.
 *
 * @package Drupal\domain_config_ui_test\Form
 */
class FormUnregistered extends ConfigFormBase {

  use FormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_ui_test_form_unregistered';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['domain_config_ui_test_unregistered.settings'];
  }

  /**
   * Get configuration.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   The domain_config_ui_test module settings.
   */
  protected function getConfig() {
    return $this->config('domain_config_ui_test_unregistered.settings');
  }

  /**
   * Add elements to form.
   *
   * @param array $form
   *   The configuration form object.
   * @param \Drupal\Core\Config\Config $config
   *   The module settings.
   */
  protected function addFormElements(array &$form, Config $config) {
    $form['field1'] = [
      '#type' => 'textfield',
      '#title' => 'Field 1',
      '#default_value' => $config->get('field1'),
    ];
  }

  /**
   * Process submitted configuration.
   */
  protected function processSubmit(FormStateInterface $form_state, Config $config) {
    $config->set('field1', $form_state->getValue('field1'));
  }

}
