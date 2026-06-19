<?php

namespace Drupal\domain_config_ui_test\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Domain config UI test configuration form trait.
 *
 * @package Drupal\domain_config_ui_test\Form
 */
trait FormTrait {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['domain_config_ui_test.settings'];
  }

  /**
   * Get configuration.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   The domain_config_ui_test module settings.
   */
  protected function getConfig() {
    return $this->config('domain_config_ui_test.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $this->addFormElements($form, $config);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $this->processSubmit($form_state, $config);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
