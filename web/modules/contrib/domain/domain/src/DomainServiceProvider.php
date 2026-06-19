<?php

namespace Drupal\domain;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\domain\EventSubscriber\DomainRedirectResponseSubscriber;
use Drupal\domain\HttpKernel\DomainPrefixPathProcessor;

/**
 * Provides services overrides for Domain.
 */
class DomainServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $config_storage = BootstrapConfigStorageFactory::get();
    $domain_settings = $config_storage->read('domain.settings');
    // Set the www_prefix parameter to the container so that we can avoid
    // reading from configuration during domain negotiation.
    // See https://www.drupal.org/i/3560725
    $container->setParameter('domain.www_prefix', $domain_settings['www_prefix'] ?? FALSE);
    $container->setParameter('domain.path_prefix', $domain_settings['path_prefix'] ?? FALSE);
    $container->setParameter('domain.allow_non_ascii', $domain_settings['allow_non_ascii'] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Add the site context to the render cache.
    if ($container->hasParameter('renderer.config')) {
      $renderer_config = $container->getParameter('renderer.config');

      // Vary all rendered output per domain so that menus,
      // toolbar, and links are cached separately for each
      // domain. This supersedes url.site which cannot
      // distinguish same-hostname prefixed domains.
      if (!in_array('domain', $renderer_config['required_cache_contexts'], TRUE)) {
        $renderer_config['required_cache_contexts'][] = 'domain';
      }

      $container->setParameter('renderer.config', $renderer_config);
    }
    // Remove the domain prefix path processor if the feature is disabled.
    if (!$container->getParameter('domain.path_prefix')) {
      $container->removeDefinition('domain.prefix_path_processor');
      $container->removeAlias(DomainPrefixPathProcessor::class);
    }
    // Overrides redirect_response_subscriber service to use our own
    // implementation.
    if ($container->hasDefinition('redirect_response_subscriber')) {
      $definition = $container->getDefinition('redirect_response_subscriber');
      $definition->setClass(DomainRedirectResponseSubscriber::class);
    }
  }

}
