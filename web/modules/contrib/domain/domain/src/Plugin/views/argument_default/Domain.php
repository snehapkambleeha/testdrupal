<?php

namespace Drupal\domain\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainNegotiationContext;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to extract active domain ID.
 */
#[ViewsArgumentDefault(
  id: 'active_domain',
  title: new TranslatableMarkup('Active domain'),
)]
class Domain extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DomainNegotiationContext $domainNegotiationContext,
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
      $container->get('domain.negotiation_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    return $this->domainNegotiationContext->getDomainId();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['domain'];
  }

}
