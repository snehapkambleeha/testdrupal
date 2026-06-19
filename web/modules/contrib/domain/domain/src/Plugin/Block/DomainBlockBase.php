<?php

namespace Drupal\domain\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a common block pattern for caching.
 */
abstract class DomainBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DomainNegotiationContext $domainNegotiationContext,
    EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->domainStorage = $entityTypeManager->getStorage('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('domain.negotiation_context'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // By default, all domain blocks are per-url.
    return ['url'];
  }

}
