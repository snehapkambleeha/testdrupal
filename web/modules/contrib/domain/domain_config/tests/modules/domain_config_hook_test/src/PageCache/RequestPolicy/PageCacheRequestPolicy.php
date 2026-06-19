<?php

namespace Drupal\domain_config_hook_test\PageCache\RequestPolicy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A page cache request policy.
 *
 * This service is not meant to DO anything, it's just meant to represent
 * a service that might be present in the Drupal community. For example,
 * persistent_login module has this same structure.
 */
class PageCacheRequestPolicy implements RequestPolicyInterface {

  public function __construct(protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    // This line is important. You have to use this service for it to fail.
    $this->configFactory
      ->get('system.site');

    return NULL;
  }

}
