<?php

namespace Drupal\domain_config;

use Drupal\Core\Asset\LibraryDiscoveryCollector;
use Drupal\domain\DomainNegotiationContext;

/**
 * Reads library information.
 *
 * @package Drupal\domain_config
 */
class DomainConfigLibraryDiscoveryCollector extends LibraryDiscoveryCollector {

  /**
   * The domain negotiation context.
   *
   * @var \Drupal\domain\DomainNegotiationContext
   */
  protected $domainNegotiationContext;

  /**
   * Set a domain.
   *
   * @param \Drupal\domain\DomainNegotiationContext $domainNegotiationContext
   *   The domain negotiator.
   */
  public function setDomainNegotiationContext(DomainNegotiationContext $domainNegotiationContext) {
    $this->domainNegotiationContext = $domainNegotiationContext;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCid() {
    if (!isset($this->cid)) {
      $domain_id = $this->domainNegotiationContext->getDomainId('null');
      $this->cid = 'library_info:' . $domain_id . ':' . $this->themeManager->getActiveTheme()->getName();
    }

    return $this->cid;
  }

}
