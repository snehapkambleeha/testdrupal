<?php

namespace Drupal\domain\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a global access check to ensure inactive domains are restricted.
 */
interface DomainAccessCheckInterface extends AccessCheckInterface {

  /**
   * Checks if a path should be restricted by domain access control.
   *
   * @param string $path
   *   The path to check for domain access restrictions.
   *
   * @return bool
   *   TRUE if the path should be restricted, FALSE otherwise.
   */
  public function checkPath($path);

  /**
   * Checks if the current user has access to the current domain.
   *
   * This method determines whether a user can access the currently active
   * domain based on the domain's status.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object. Returns allowed or forbidden based on the
   *   domain status and user permissions.
   */
  public function access(AccountInterface $account);

}
