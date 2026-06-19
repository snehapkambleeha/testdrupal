<?php

namespace Drupal\domain\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain\DomainToken;

/**
 * Token hook implementations for domain.
 */
class DomainTokenHooks {

  public function __construct(
    protected DomainToken $domainToken,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    return $this->domainToken->getTokenInfo();
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    return $this->domainToken->getTokens($type, $tokens, $data, $options, $bubbleable_metadata);
  }

}
