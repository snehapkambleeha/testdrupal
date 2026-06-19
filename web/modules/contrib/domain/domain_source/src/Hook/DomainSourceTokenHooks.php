<?php

namespace Drupal\domain_source\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain_source\DomainSourceToken;

/**
 * Token hook implementations for domain_source.
 */
class DomainSourceTokenHooks {

  public function __construct(
    protected DomainSourceToken $domainSourceToken,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    return $this->domainSourceToken->getTokenInfo();
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    return $this->domainSourceToken->getTokens($type, $tokens, $data, $options, $bubbleable_metadata);
  }

}
