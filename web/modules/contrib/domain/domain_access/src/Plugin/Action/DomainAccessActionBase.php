<?php

namespace Drupal\domain_access\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for operations to change domain assignments.
 */
abstract class DomainAccessActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigEntityTypeInterface $entityType,
    protected DomainStorageInterface $domainStorage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getDefinition('domain'),
      $container->get('entity_type.manager')->getStorage('domain'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'domain_id' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $domains = $this->domainStorage->loadOptionsList();
    $form['domain_id'] = [
      '#type' => 'checkboxes',
      '#title' => t('Domains'),
      '#options' => $domains,
      '#default_value' => $this->configuration['domain_id'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['domain_id'] = $form_state->getValue('domain_id');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if (isset($this->configuration['domain_id']) && !empty($this->configuration['domain_id'])) {
      $prefix = $this->entityType->getConfigPrefix() . '.';
      foreach ($this->configuration['domain_id'] as $id) {
        $this->addDependency('config', $prefix . $id);
      }
    }

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
