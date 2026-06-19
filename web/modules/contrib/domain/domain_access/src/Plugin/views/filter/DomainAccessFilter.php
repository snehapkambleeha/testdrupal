<?php

namespace Drupal\domain_access\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides filtering by assigned domain.
 */
#[ViewsFilter('domain_access_filter')]
class DomainAccessFilter extends ManyToOne {

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
    // @todo filter this list.
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Domains');
      $this->valueOptions = $this->domainStorage->loadOptionsList();
    }

    return $this->valueOptions;
  }

}
