<?php

namespace Drupal\domain_config_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;

/**
 * Hook implementations for domain_config_test.
 */
class DomainConfigTestHooks {

  public function __construct(
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {}

  /**
   * Implements hook_domain_request_alter().
   */
  #[Hook('domain_request_alter')]
  public function domainRequestAlter(DomainInterface $domain) {
    $domain->addProperty('config_test', 'aye');
  }

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments) {
    $domain = $this->domainNegotiationContext->getDomain();
    if (!is_null($domain) && $domain->get('config_test') === 'aye') {
      $attachments['#attached']['http_header'][] = [
        'X-Domain-Config-Test-page-attachments-hook',
        'invoked',
      ];
    }
  }

}
