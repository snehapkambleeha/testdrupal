<?php

namespace Drupal\domain\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Component\Routing\Route;

/**
 * Provides a global access check to ensure inactive domains are restricted.
 */
class DomainAccessCheck implements DomainAccessCheckInterface {

  public function __construct(
    protected DomainNegotiationContext $domainNegotiationContext,
    protected ConfigFactoryInterface $configFactory,
    protected PathMatcherInterface $pathMatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return $this->checkPath($route->getPath());
  }

  /**
   * {@inheritdoc}
   */
  public function checkPath($path) {
    // Do not directly send null value to preg_quote().
    $allowed_paths = $this->configFactory->get('domain.settings')->get('login_paths') ?? '';
    return !$this->pathMatcher->matchPath($path, $allowed_paths);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $domain = $this->domainNegotiationContext->getDomain();
    // No domain, let it pass.
    if (is_null($domain)) {
      return AccessResult::allowed()->addCacheContexts(['domain']);
    }
    // Active domain, let it pass.
    if ($domain->status()) {
      return AccessResult::allowed()->addCacheContexts(['domain']);
    }
    // Inactive domain, require permissions.
    else {
      $permissions = ['administer domains', 'access inactive domains'];
      $operator = 'OR';
      return AccessResult::allowedIfHasPermissions($account, $permissions, $operator)->addCacheContexts(['domain']);
    }
  }

}
