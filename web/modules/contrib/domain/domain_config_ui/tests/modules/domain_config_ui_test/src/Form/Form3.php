<?php

namespace Drupal\domain_config_ui_test\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form3 configuration form.
 *
 * @package Drupal\domain_config_ui_test\Form
 */
class Form3 extends ConfigFormBase {

  use FormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_ui_test_form3';
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
    $form['field3'] = [
      '#type' => 'checkbox',
      '#title' => 'Field 3',
      '#default_value' => $config->get('field3'),
    ];
  }

  /**
   * Process submitted configuration.
   */
  protected function processSubmit(FormStateInterface $form_state, Config $config) {
    $config->set('field3', $form_state->getValue('field3'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $this->processSubmit($form_state, $config);
    $config->save();

    $field3_value = $config->get('field3');
    if (is_bool($field3_value)) {
      $field3_value_string = $field3_value ? 'true' : 'false';
    }
    else {
      $field3_value_string = (string) $field3_value;
    }
    $message = $this->t('Field 3 value: @value', ['@value' => $field3_value_string]);
    $this->messenger()->addMessage($message);

    parent::submitForm($form, $form_state);
  }

}
