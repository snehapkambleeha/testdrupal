<?php

namespace Drupal\domain_config_ui_test\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form1 configuration form.
 *
 * @package Drupal\domain_config_ui_test\Form
 */
class Form1 extends ConfigFormBase {

  use FormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_ui_test_form1';
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $this->processSubmit($form_state, $config);
    $config->save();

    $field1_value = $config->get('field1');
    $message = $this->t('Field 1 value: @value', ['@value' => $field1_value]);
    $this->messenger()->addMessage($message);

    parent::submitForm($form, $form_state);
  }

}
