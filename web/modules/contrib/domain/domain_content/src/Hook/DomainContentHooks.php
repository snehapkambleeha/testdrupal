<?php

namespace Drupal\domain_content\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;

/**
 * Hook implementations for domain_content.
 */
class DomainContentHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_domain_operations().
   */
  #[Hook('domain_operations')]
  public function domainOperations(DomainInterface $domain, AccountInterface $account) {
    $operations = [];

    // Advanced grants for edit/delete require permissions.
    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $allowed = DomainAccessManager::getAccessValues($user);
    $id = $domain->id();
    if ($account->hasPermission('publish to any domain') || ($account->hasPermission('publish to any assigned domain') && isset($allowed[$domain->id()]))) {
      $operations['domain_content'] = [
        'title' => $this->t('Content'),
        'url' => Url::fromRoute('view.affiliated_content.page_1', ['arg_0' => $id]),
        // Core operations start at 0 and increment by 10.
        'weight' => 120,
      ];
    }
    if ($account->hasPermission('assign editors to any domain') || ($account->hasPermission('assign domain editors') && isset($allowed[$domain->id()]))) {
      $operations['domain_users'] = [
        'title' => $this->t('Editors'),
        'url' => Url::fromRoute('view.affiliated_editors.page_1', ['arg_0' => $id]),
        // Core operations start at 0 and increment by 10.
        'weight' => 120,
      ];
    }

    return $operations;
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    if ($this->moduleHandler->moduleExists('domain_access')
      && !$this->configFactory
        ->get('domain_access.settings')
        ->get('access_fields_removal')) {
      $allow = TRUE;
      $list = ['user' => 'user'];
      $node_types = $this->entityTypeManager
        ->getStorage('node_type')->loadMultiple();
      foreach ($node_types as $type => $info) {
        $list[$type] = 'node';
      }
      foreach ($list as $bundle => $entity_type) {
        $id = $entity_type . '.' . $bundle . '.'
          . DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD;
        $field = $this->entityTypeManager
          ->getStorage('field_config')->load($id);
        if (is_null($field)) {
          $allow = FALSE;
          break;
        }
        $id = $entity_type . '.' . $bundle . '.'
          . DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD;
        $field = $this->entityTypeManager
          ->getStorage('field_config')->load($id);
        if (is_null($field)) {
          $allow = FALSE;
          break;
        }
      }
      if (!$allow) {
        $requirements['domain_content'] = [
          'title' => $this->t('Domain content'),
          'description' => $this->t(
            "Some of Domain Access's required fields are missing.  Please reinstall the Domain Access module."
          ),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }

    return $requirements;
  }

}
