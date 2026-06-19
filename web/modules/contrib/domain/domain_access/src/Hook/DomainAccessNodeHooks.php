<?php

namespace Drupal\domain_access\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\domain_access\UserDomainAccess;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Node access hook implementations for domain_access.
 */
class DomainAccessNodeHooks {

  public function __construct(
    protected DomainNegotiatorInterface $negotiator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected DomainAccessManagerInterface $accessManager,
  ) {}

  /**
   * Implements hook_node_grants().
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, $op) {
    $grants = [];
    /** @var \Drupal\domain\Entity\Domain $active_domain */
    $active_domain = $this->negotiator->getActiveDomain();
    if (is_null($active_domain)) {
      /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
      $domain_storage = $this->entityTypeManager->getStorage('domain');
      $active_domain = $domain_storage->loadDefaultDomain();
    }

    if (is_null($active_domain)) {
      return $grants;
    }

    $active_domain_id = $active_domain->getDomainId();
    $user_domain_access = DomainAccessManager::userCanAccessDomain($account, $active_domain_id);
    $config = $this->configFactory->get('domain_access.settings');

    if ($op === 'view') {
      $grants['domain_id'][] = $active_domain_id;
      $grants['domain_site'][] = 0;
      if ($account->hasPermission('view unpublished domain content')) {
        if ($user_domain_access !== UserDomainAccess::None) {
          $grants['domain_unpublished'][] = $active_domain_id;
          if ($user_domain_access === UserDomainAccess::All
            && $config->get('access_fields_removal')) {
            $grants['domain_unpublished'][] = 0;
          }
        }
      }
    }
    elseif ($op === 'update') {
      if ($account->hasPermission('edit domain content')) {
        if ($user_domain_access !== UserDomainAccess::None) {
          $grants['domain_id'][] = $active_domain_id;
          $grants['domain_unpublished'][] = $active_domain_id;
          if ($user_domain_access === UserDomainAccess::All
            && $config->get('access_fields_removal')) {
            $grants['domain_id'][] = 0;
            $grants['domain_unpublished'][] = 0;
          }
        }
        else {
          if ($config->get('per_bundle_grants')) {
            foreach (array_keys(NodeType::loadMultiple()) as $type_id) {
              if ($account->hasPermission("update {$type_id} content on assigned domains")) {
                $grants['domain_id:' . $type_id][] = $active_domain_id;
                $grants['domain_unpublished:' . $type_id][] = $active_domain_id;
                break;
              }
            }
          }
        }
      }
    }
    elseif ($op === 'delete') {
      if ($account->hasPermission('delete domain content')) {
        if ($user_domain_access !== UserDomainAccess::None) {
          $grants['domain_id'][] = $active_domain_id;
          $grants['domain_unpublished'][] = $active_domain_id;
          if ($user_domain_access === UserDomainAccess::All
            && $config->get('access_fields_removal')) {
            $grants['domain_id'][] = 0;
            $grants['domain_unpublished'][] = 0;
          }
        }
        else {
          if ($config->get('per_bundle_grants')) {
            foreach (array_keys(NodeType::loadMultiple()) as $type_id) {
              if ($account->hasPermission("delete {$type_id} content on assigned domains")) {
                $grants['domain_id:' . $type_id][] = $active_domain_id;
                $grants['domain_unpublished:' . $type_id][] = $active_domain_id;
                break;
              }
            }
          }
        }
      }
    }

    return $grants;
  }

  /**
   * Implements hook_node_access_records().
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node) {
    $grants = [];
    $config = $this->configFactory->get('domain_access.settings');
    $access_field_removal = $config->get('access_fields_removal');
    /** @var \Drupal\domain\DomainInterface $active_domain */
    $active_domain = $this->negotiator->getActiveDomain();
    $per_bundle_grants = $config->get('per_bundle_grants');
    $translations = $node->getTranslationLanguages();
    foreach ($translations as $langcode => $language) {
      $translation = $node->getTranslation($langcode);
      if ($access_field_removal
        && !$translation->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)
        && !$translation->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD)) {
        $grants[] = [
          'realm' => $translation->isPublished() ? 'domain_id' : 'domain_unpublished',
          'gid' => 0,
          'grant_view' => 1,
          'grant_update' => 1,
          'grant_delete' => 1,
          'langcode' => $langcode,
        ];
        if ($translation->isPublished()) {
          $grants[] = [
            'realm' => 'domain_site',
            'gid' => 0,
            'grant_view' => 1,
            'grant_update' => 0,
            'grant_delete' => 0,
            'langcode' => $langcode,
          ];
        }
        continue;
      }
      $domains = DomainAccessManager::getAccessValues($translation);
      if ($domains === [] && $active_domain instanceof DomainInterface) {
        $domains[$active_domain->id()] = $active_domain->getDomainId();
      }
      foreach ($domains as $domain_id) {
        $domain_realm = ($translation->isPublished()) ? 'domain_id' : 'domain_unpublished';
        $grants[] = [
          'realm' => $domain_realm,
          'gid' => $domain_id,
          'grant_view' => 1,
          'grant_update' => 1,
          'grant_delete' => 1,
          'langcode' => $langcode,
        ];
        if ($per_bundle_grants) {
          $grants[] = [
            'realm' => $domain_realm . ':' . $node->bundle(),
            'gid' => $domain_id,
            'grant_view' => 1,
            'grant_update' => 1,
            'grant_delete' => 1,
            'langcode' => $langcode,
          ];
        }
      }
      if (DomainAccessManager::getAllValue($translation) && $translation->isPublished()) {
        $grants[] = [
          'realm' => 'domain_site',
          'gid' => 0,
          'grant_view' => 1,
          'grant_update' => 0,
          'grant_delete' => 0,
          'langcode' => $langcode,
        ];
      }
    }
    return $grants;
  }

  /**
   * Implements hook_node_access().
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, $op, AccountInterface $account) {
    $active_domain = $this->negotiator->getActiveDomain();
    if (is_null($active_domain) || !is_numeric($active_domain->getDomainId())) {
      return AccessResult::neutral()->addCacheableDependency($node);
    }

    $type = $node->bundle();
    $allowed = FALSE;

    if ($op === 'view') {
      if ($node->isPublished()) {
        $allowed = FALSE;
      }
      else {
        if ($account->hasPermission('view unpublished domain content')) {
          $allowed = $this->accessManager->checkEntityAccess($node, $account);
        }
      }
    }
    elseif ($op === 'update') {
      if ($account->hasPermission('edit domain content')
        || $account->hasPermission('update ' . $type . ' content on assigned domains')) {
        $allowed = $this->accessManager->checkEntityAccess($node, $account);
      }
    }
    elseif ($op === 'delete') {
      if ($account->hasPermission('delete domain content')
        || $account->hasPermission('delete ' . $type . ' content on assigned domains')) {
        $allowed = $this->accessManager->checkEntityAccess($node, $account);
      }
    }

    if ($allowed) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($node);
    }

    return AccessResult::neutral()->addCacheableDependency($node);
  }

  /**
   * Implements hook_node_create_access().
   */
  #[Hook('node_create_access')]
  public function nodeCreateAccess(AccountInterface $account, $context, $entity_bundle) {
    $active_domain = $this->negotiator->getActiveDomain();
    if (!$active_domain instanceof DomainInterface || !is_numeric($active_domain->getDomainId())) {
      return AccessResult::neutral()->addCacheContexts(['domain']);
    }

    if ($account->hasPermission('create ' . $entity_bundle . ' content on assigned domains')
        || $account->hasPermission('create domain content')) {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->entityTypeManager->getStorage('user')->load($account->id());
      $user_domains = DomainAccessManager::getAccessValues($user);
      $user_access_all = DomainAccessManager::getAllValue($user);
      if ($user_access_all || in_array($active_domain->getDomainId(), $user_domains, TRUE)) {
        $result = AccessResult::allowed();
      }
      else {
        $result = AccessResult::neutral();
      }
      return $result
        ->addCacheableDependency($user)
        ->addCacheContexts(['domain', 'user.permissions']);
    }
    else {
      return AccessResult::neutral()->cachePerPermissions();
    }
  }

}
