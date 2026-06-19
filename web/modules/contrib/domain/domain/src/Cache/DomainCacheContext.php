<?php

namespace Drupal\domain\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\domain\DomainNegotiatorInterface;

/**
 * Defines the DomainCacheContext service for "per domain" caching.
 *
 * This context varies cached output by the active domain's unique
 * ID. It is used as a required render cache context so that menus,
 * toolbar, blocks, and links are cached separately for each domain.
 *
 * Unlike the core url.site context, which varies by hostname only,
 * this context also distinguishes domains that share the same
 * hostname but use different path prefixes (e.g. example.com/en
 * vs example.com/fr).
 */
class DomainCacheContext implements CalculatedCacheContextInterface {

  public function __construct(protected DomainNegotiatorInterface $domainNegotiator) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Domain');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL) {
    return $this->domainNegotiator->getActiveId();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL) {
    return new CacheableMetadata();
  }

}
