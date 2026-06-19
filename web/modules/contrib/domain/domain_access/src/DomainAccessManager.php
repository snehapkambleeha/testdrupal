<?php

namespace Drupal\domain_access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;

/**
 * Checks the access status of entities based on domain settings.
 */
class DomainAccessManager implements DomainAccessManagerInterface {

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Static cache for domain access values.
   *
   * @var array
   */
  protected static $staticCache = [];

  public function __construct(
    protected DomainNegotiatorInterface $negotiator,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->domainStorage = $entityTypeManager->getStorage('domain');
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function getAccessValues(FieldableEntityInterface $entity, $field_name = DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD) {
    // @todo In tests, $entity is returning NULL.
    if (is_null($entity)) {
      return [];
    }
    $entity_id = $entity->id();
    $langcode = $entity->language()->getId();
    $entity_type_id = $entity->getEntityTypeId();
    if (isset(self::$staticCache[$entity_type_id][$entity_id][$langcode][$field_name])) {
      return self::$staticCache[$entity_type_id][$entity_id][$langcode][$field_name];
    }
    $list = [];
    // Get the values of an entity.
    $values = $entity->hasField($field_name) ? $entity->get($field_name) : [];
    // Must be at least one item.
    if (!empty($values)) {
      $domain_storage = \Drupal::entityTypeManager()->getStorage('domain');
      foreach ($values as $item) {
        $target = $item->getValue();
        if (isset($target['target_id'])) {
          $domain = $domain_storage->load($target['target_id']);
          if ($domain instanceof DomainInterface) {
            $list[$domain->id()] = $domain->getDomainId();
          }
        }
      }
    }
    self::$staticCache[$entity_type_id][$entity_id][$langcode][$field_name] = $list;
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAllValue(FieldableEntityInterface $entity) {
    return $entity->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD) ? (bool) $entity->get(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD)->value : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkEntityAccess(FieldableEntityInterface $entity, AccountInterface $account) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($account->id());
    if ($user) {
      if ($entity->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)) {
        $entity_domains = self::getAccessValues($entity);
        if (self::getAllValue($user) === TRUE && count($entity_domains) > 0) {
          return TRUE;
        }
        $user_domains = self::getAccessValues($user);
        return count(array_intersect($entity_domains, $user_domains)) > 0;
      }
      else {
        return self::getAllValue($user);
      }
    }
    else {
      return FALSE;
    }
  }

  /**
   * Callback to provide the default value for the domain access field.
   *
   * This function determines the default value for a domain access field,
   * applying necessary filtering based on the current user's permissions and
   * access domains.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to which the field belongs.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field definition, used to determine the default value.
   *
   * @return array
   *   An array of filtered default values. If the user has global access
   *   permissions or no filtering is required, the original default value is
   *   returned. Otherwise, the list is filtered to include only items the
   *   user has access to.
   */
  public static function getDefaultValue(FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    // Get the default value from field configuration.
    $default_value = $definition->getDefaultValueLiteral();

    if (empty($default_value)) {
      // Nothing to do.
      return $default_value;
    }

    $current_user = \Drupal::currentUser();
    if ($current_user->hasPermission('publish to any domain')) {
      // No filtering needed.
      return $default_value;
    }

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user = $user_storage->load($current_user->id());

    if (DomainAccessManager::getAllValue($user)) {
      // No filtering needed.
      return $default_value;
    }

    $user_access_domains = DomainAccessManager::getAccessValues($user);
    if (empty($user_access_domains)) {
      // No domains available to user.
      return [];
    }

    $filtered_default_value = [];

    foreach ($default_value as $item) {
      if (isset($item['target_id'])) {
        $target_id = $item['target_id'];
        if (isset($user_access_domains[$target_id])) {
          $filtered_default_value[] = ['target_id' => $target_id];
        }
      }
      elseif (isset($item['target_uuid'])) {
        $target_uuid = $item['target_uuid'];
        $domain_storage = \Drupal::entityTypeManager()->getStorage('domain');
        $domains = $domain_storage->loadByProperties(['uuid' => $target_uuid]);
        $domain = reset($domains);
        if ($domain && isset($user_access_domains[$domain->id()])) {
          $filtered_default_value[] = ['target_uuid' => $target_uuid];
        }
      }
    }

    return $filtered_default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDomainPermissions(AccountInterface $account, DomainInterface $domain, array $permissions, $conjunction = 'AND') {
    // Assume no access.
    $access = FALSE;

    // In the case of multiple AND permissions, assume access and then deny if
    // any check fails.
    if ($conjunction === 'AND' && $permissions !== []) {
      $access = TRUE;
      foreach ($permissions as $permission) {
        if (!($permission_access = $account->hasPermission($permission))) {
          $access = FALSE;
          break;
        }
      }
    }
    // In the case of multiple OR permissions, assume deny and then allow if any
    // check passes.
    else {
      foreach ($permissions as $permission) {
        if ($permission_access = $account->hasPermission($permission)) {
          $access = TRUE;
          break;
        }
      }
    }
    // Validate that the user is assigned to the domain. If not, deny.
    $user = $this->userStorage->load($account->id());
    $allowed = self::getAccessValues($user);
    if (!isset($allowed[$domain->id()]) && self::getAllValue($user) !== TRUE) {
      $access = FALSE;
    }

    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentUrls(FieldableEntityInterface $entity) {
    $domains = self::getAccessValues($entity);
    $source_id = NULL;

    if ($this->moduleHandler->moduleExists('domain_source')) {
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $source_id = \Drupal::service('domain_source.helper')->getSourceDomainId($entity);
    }

    // Put the source domain first if it exists.
    $ids = [];
    if ($source_id !== NULL) {
      $ids[] = $source_id;
    }
    $ids = array_unique(array_merge($ids, array_keys($domains)));

    $urls = [];
    /** @var \Drupal\domain\Entity\Domain $domain */
    foreach ($this->domainStorage->loadMultiple($ids) as $domain) {
      $url = $entity->toUrl('canonical', [
        'absolute' => TRUE,
        'domain' => $domain,
      ])->toString();
      $urls[$domain->id()] = $url;
    }
    return $urls;
  }

  /**
   * Clear cache when entity is updated.
   */
  public static function clearStaticCache($entity_id = NULL, $entity_type_id = NULL) {
    if ($entity_id && $entity_type_id) {
      unset(static::$staticCache[$entity_type_id][$entity_id]);
    }
    else {
      static::$staticCache = [];
    }
  }

  /**
   * Checks if a user account can access a specific domain.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   * @param int $domain_id
   *   The domain ID to check access for.
   *
   * @return UserDomainAccess
   *   The user's access to the domain.
   */
  public static function userCanAccessDomain(AccountInterface $account, $domain_id): UserDomainAccess {
    // Users with global permission can access any domain.
    if ($account->hasPermission('publish to any domain')) {
      return UserDomainAccess::All;
    }

    // Load the full user entity to check domain assignments.
    /** @var \Drupal\user\UserInterface $user */
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($account->id());
    if (!$user) {
      return UserDomainAccess::None;
    }

    // Check if the user has access to all domains.
    if (self::getAllValue($user)) {
      return UserDomainAccess::All;
    }

    // Check if the user has access to this specific domain.
    $user_domains = self::getAccessValues($user);
    if (in_array($domain_id, $user_domains, TRUE)) {
      return UserDomainAccess::Domain;
    }
    else {
      return UserDomainAccess::None;
    }
  }

}
