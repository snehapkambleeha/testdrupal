<?php

namespace Drupal\domain\HttpKernel;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes inbound and outbound paths for domain path prefixes.
 *
 * Inbound: strips the domain path prefix from the request path so
 * that subsequent processors (language, alias) see the unprefixed
 * path. Outbound: prepends the active domain's path prefix to
 * generated URLs.
 */
class DomainPrefixPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * Constructs a DomainPrefixPathProcessor.
   *
   * @param \Drupal\domain\DomainNegotiationContext $negotiationContext
   *   The domain negotiation context.
   */
  public function __construct(
    protected DomainNegotiationContext $negotiationContext,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $domain = $this->negotiationContext->getDomain();
    if (!$domain instanceof DomainInterface) {
      return $path;
    }

    $prefix_len = $domain->matchPathPrefix($path);
    if ($prefix_len === FALSE) {
      return $path;
    }

    return substr($path, $prefix_len) ?: '/';
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!empty($options['external'])) {
      return $path;
    }

    $domain = $options['domain'] ?? $this->negotiationContext->getDomain();
    if (!$domain instanceof DomainInterface) {
      return $path;
    }

    $prefix = $domain->getPathPrefix();
    if ($prefix === '') {
      return $path;
    }

    $options['prefix'] = $prefix . '/' . ($options['prefix'] ?? '');

    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheContexts(['domain']);
    }

    return $path;
  }

}
