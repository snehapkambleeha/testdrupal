<?php

namespace Drupal\domain;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access controller for the domain entity type.
 *
 * Note that this is not a node access check.
 */
class DomainAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected DomainElementManagerInterface $domainElementManager,
    protected UserStorageInterface $userStorage,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('domain.element_manager'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, ?AccountInterface $account = NULL) {
    if (!($entity instanceof DomainInterface)) {
      return AccessResult::neutral();
    }
    $account = $this->prepareUser($account);
    // Check the global permission.
    if ($account->hasPermission('administer domains')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    // For view, we allow admins unless the domain is inactive.
    $is_admin = $this->isDomainAdmin($entity, $account);
    if ($operation === 'view' && ($entity->status() || $account->hasPermission('access inactive domains')) && ($is_admin || $account->hasPermission('view domain list') ||
     $account->hasPermission('view domain entity'))) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    // For other operations, check that the user is a domain admin.
    if ($operation === 'update' && $account->hasPermission('edit assigned domains') && $is_admin) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ($operation === 'delete' && $account->hasPermission('delete assigned domains') && $is_admin) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($account->hasPermission('administer domains') || $account->hasPermission('create domains')) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

  /**
   * Checks if a user can administer a specific domain.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to retrieve field data from.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if a user can administer a specific domain, or FALSE.
   */
  public function isDomainAdmin(EntityInterface $entity, AccountInterface $account) {
    $user = $this->userStorage->load($account->id());
    $user_domains = $this->domainElementManager->getFieldValues($user, DomainInterface::DOMAIN_ADMIN_FIELD);
    return isset($user_domains[$entity->id()]);
  }

}
