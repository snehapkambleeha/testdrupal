<?php

namespace Drupal\domain_source\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by published status.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('domain_source')]
class DomainSource extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * The domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->domainStorage = $entity_type_manager->getStorage('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('domain.negotiation_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Domains');
      $this->valueOptions = [
        '_active' => $this->t('Active domain'),
      ] + $this->domainStorage->loadOptionsList();
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $active_index = array_search('_active', (array) $this->value, TRUE);
    if ($active_index !== FALSE) {
      $active_id = $this->domainNegotiationContext->getDomainId();
      $this->value[$active_index] = $active_id;
    }

    parent::query();
  }

}
