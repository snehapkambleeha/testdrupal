<?php

namespace Drupal\domain_config_ui\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;

/**
 * Form for module settings.
 */
class SettingsForm extends ConfigFormBase {

  use DomainConfigUITrait;
  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_ui_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('domain_config_ui.settings');
    $form['overridable_configurations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enabled configuration forms'),
      '#rows' => 5,
      '#columns' => 40,
      '#description' => $this->t("Specify a list of config names that can be overridden per domain. Enter one config name per line. Example: 'system.site: domain1_id, domain2_id'."),
      '#default_value' => $this->buildOverridableConfigurationText($config),
      '#config_target' => 'domain_config_ui.settings:overridable_configurations',
      '#element_validate' => ['::validateOverridableConfigurationText'],
    ];
    $form['disallowed_configurations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration objects to exclude'),
      '#rows' => 5,
      '#columns' => 40,
      '#description' => $this->t("Enter one configuration object name per line for which the 'Enable domain configuration' button should be hidden on the corresponding configuration forms."),
      '#config_target' => new ConfigTarget(
        'domain_config_ui.settings',
        'disallowed_configurations',
        fromConfig: fn($value) => is_array($value) ? implode("\n", $value) : (string) $value,
        toConfig: function ($value) {
          $items = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $value ?? '')),
            'strlen'
          ));
          sort($items, SORT_STRING);
          return $items;
        },
      ),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds a string representation of overridable configuration names.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The configuration object.
   *
   * @return string
   *   A newline-separated list of configuration names.
   */
  public static function buildOverridableConfigurationText(Config $config) {
    $lines = [];
    $configurations = $config->get('overridable_configurations');
    if ($configurations) {
      foreach ($configurations as $configuration) {
        $key = $configuration['name'];
        $domains = $configuration['domains'];
        $lines[] = $key . ': ' . implode(', ', $domains);
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Validates the configuration names text field.
   *
   * @param array $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateOverridableConfigurationText($element, FormStateInterface $form_state) {
    $result = self::buildOverridableConfigurationFromText($element['#value']);
    $form_state->setValueForElement($element, $result);
  }

  /**
   * Build configuration from text.
   *
   * @param string $text
   *   The form element being validated.
   *
   * @return array
   *   The configuration.
   */
  public static function buildOverridableConfigurationFromText($text) {
    $lines = array_map('trim', explode("\n", $text));
    $result = [];
    foreach ($lines as $line) {
      $line = trim($line);
      // Skip empty or invalid lines.
      if ($line === '' || strpos($line, ':') === FALSE) {
        continue;
      }
      [$key, $values] = explode(':', $line, 2);
      // Trim key and values.
      $key = trim($key);
      $values = array_filter(array_map('trim', explode(',', $values)));
      $result[] = [
        'name' => $key,
        'domains' => $values,
      ];
    }
    return $result;
  }

}
