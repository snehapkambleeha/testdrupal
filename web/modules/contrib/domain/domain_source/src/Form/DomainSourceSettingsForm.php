<?php

namespace Drupal\domain_source\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the module.
 *
 * @package Drupal\domain_source\Form
 */
class DomainSourceSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_source_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $routes = $this->entityTypeManager->getDefinition('node')->getLinkTemplates();

    $options = [];
    foreach ($routes as $route => $path) {
      // Some parts of the system prepend drupal:, which the routing
      // system doesn't use. The routing system also uses underscores instead
      // of dashes. Because Drupal.
      $route = str_replace(['-', 'drupal:'], ['_', ''], $route);
      $options[$route] = $route;
    }
    // Allow other modules to alter the list of excluded routes.
    $this->moduleHandler->alter('domain_source_exclude_routes_options', $options);
    $config = $this->config('domain_source.settings');
    $form['exclude_routes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disable link rewrites for the selected routes.'),
      '#default_value' => $config->get('exclude_routes') ?? [],
      '#config_target' => new ConfigTarget('domain_source.settings', 'exclude_routes', toConfig: fn ($value) => array_values(array_filter($value))),
      '#options' => $options,
      '#description' => $this->t('Check the routes to disable. Any entity URL with a Domain Source field will be rewritten unless its corresponding route is disabled.'),
    ];
    $form['excluded_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude the following path patterns from link rewrites.'),
      '#config_target' => new ConfigTarget(
        'domain_source.settings',
        'excluded_paths',
        fromConfig: fn($value) => is_array($value) ? implode("\n", $value) : (string) $value,
        toConfig: fn($value) => array_values(array_filter(
          array_map('trim', preg_split('/\r\n|\r|\n/', $value ?? '')),
          'strlen'
        )),
      ),
      '#description' => $this->t('Enter the path patterns for which Domain Source link rewriting should be disabled.'),
    ];
    $form['excluded_route_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude the following route names from link rewrites.'),
      '#config_target' => new ConfigTarget(
        'domain_source.settings',
        'excluded_route_names',
        fromConfig: fn($value) => is_array($value) ? implode("\n", $value) : (string) $value,
        toConfig: fn($value) => array_values(array_filter(
          array_map('trim', preg_split('/\r\n|\r|\n/', $value ?? '')),
          'strlen'
        )),
      ),
      '#description' => $this->t('Enter the route names for which Domain Source link rewriting should be disabled.'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
