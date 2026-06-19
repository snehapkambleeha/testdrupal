<?php

namespace Drupal\domain_source\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for operations to change domain source.
 */
abstract class DomainSourceActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;
  use MessengerTrait;

  /**
   * The domain entity type.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface
   */
  protected $domainType;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ModuleHandlerInterface $moduleHandler, protected EntityTypeManagerInterface $entityTypeManager, protected AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->domainType = $entityTypeManager->getDefinition('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'domain_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $domains = $this->entityTypeManager->getStorage('domain')->loadOptionsList();
    $form['domain_id'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Domain'),
      '#options' => $domains,
      '#default_value' => $this->configuration['id'],
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
    if (!empty($this->configuration['domain_id'])) {
      $prefix = $this->domainType->getConfigPrefix() . '.';
      $this->addDependency('config', $prefix . $this->configuration['domain_id']);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    if (!$object instanceof ContentEntityInterface) {
      $access = AccessResult::forbidden('No content entity provided');
    }
    else {
      // @todo fix this logic.
      $access = $object->access('update', $account, TRUE);
    }

    return $return_as_object ? $access : $access->isAllowed();
  }

}
