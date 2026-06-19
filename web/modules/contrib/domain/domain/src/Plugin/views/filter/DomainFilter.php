<?php

namespace Drupal\domain\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides filtering by domain using a select list.
 */
#[ViewsFilter('domain_filter')]
class DomainFilter extends InOperator {

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->domainStorage = $container->get('entity_type.manager')->getStorage('domain');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Domain');
      $this->valueOptions = $this->domainStorage->loadOptionsList();
    }

    return $this->valueOptions;
  }

}
