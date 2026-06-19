<?php

namespace Drupal\domain_access\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_access\DomainAccessManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Sets access to routes based on domain access rules.
 *
 * @package Drupal\domain_access\Access
 */
class DomainAccessViewsAccess implements AccessCheckInterface {

  /**
   * The key used by the routing requirement.
   *
   * @var string
   */
  protected $requirementsKey = '_domain_access_views';

  /**
   * The Domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * User storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainAccessManagerInterface $manager,
  ) {
    $this->domainStorage = $this->entityTypeManager->getStorage('domain');
    $this->userStorage = $this->entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, AccountInterface $account, $arg_0 = NULL) {
    // Permissions are stored on the route defaults.
    $permission = $route->getDefault('domain_permission');
    $allPermission = $route->getDefault('domain_all_permission');

    // Users with this permission can see any domain content lists, and it is
    // required to view all affiliates.
    if ($account->hasPermission($allPermission)) {
      return AccessResult::allowed();
    }

    // Load the domain from the passed argument. In testing, this passed NULL
    // in some instances.
    $domain = NULL;
    if (!is_null($arg_0)) {
      $domain = $this->domainStorage->load($arg_0);
    }

    // Domain found, check user permissions.
    if ($domain instanceof DomainInterface) {
      if ($this->manager->hasDomainPermissions($account, $domain, [$permission])) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return $route->hasRequirement($this->requirementsKey);
  }

}
