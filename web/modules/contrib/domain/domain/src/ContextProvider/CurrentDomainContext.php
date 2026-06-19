<?php

namespace Drupal\domain\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;

/**
 * Provides a context handler for the block system.
 */
class CurrentDomainContext implements ContextProviderInterface {

  use StringTranslationTrait;

  public function __construct(protected DomainNegotiationContext $domainNegotiationContext) {
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $context = NULL;
    // Load the current domain.
    $current_domain = $this->domainNegotiationContext->getDomain();
    // Set the context, if we have a domain.
    if ($current_domain instanceof DomainInterface) {
      $context = EntityContext::fromEntity($current_domain, $this->t('Active domain'));
      // Allow caching.
      $cacheability = new CacheableMetadata();
      $cacheability->setCacheContexts(['domain']);
      $context->addCacheableDependency($cacheability);
    }

    // Prepare the result.
    $result = [
      'domain' => $context,
    ];

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    // See https://www.drupal.org/project/domain/issues/3201514
    if ($this->domainNegotiationContext->getDomain() instanceof DomainInterface) {
      return $this->getRuntimeContexts([]);
    }
    return [];
  }

}
