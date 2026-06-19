<?php

namespace Drupal\domain_access\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain_access\DomainAccessManager;

/**
 * Domain entity hook implementations for domain_access.
 */
class DomainAccessDomainHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_domain_references_alter().
   */
  #[Hook('domain_references_alter')]
  public function domainReferencesAlter($query, $account, $context) {
    if ($context['field_type'] !== 'editor') {
      return;
    }
    switch ($context['entity_type']) {
      case 'node':
        if ($account->hasPermission('publish to any domain')) {
          break;
        }
        elseif ($account->hasPermission('publish to any assigned domain')) {
          if (DomainAccessManager::getAllValue($account) === TRUE) {
            break;
          }
          $allowed = DomainAccessManager::getAccessValues($account);
          $query->condition('id', array_keys($allowed), 'IN');
        }
        else {
          $query->condition('id', '-no-possible-match-');
        }
        break;

      case 'user':
        if ($account->hasPermission('assign editors to any domain')) {
          // Do nothing.
        }
        elseif ($account->hasPermission('assign domain editors')) {
          if (DomainAccessManager::getAllValue($account) === TRUE) {
            break;
          }
          $allowed = DomainAccessManager::getAccessValues($account);
          $query->condition('id', array_keys($allowed), 'IN');
        }
        else {
          $query->condition('id', '-no-possible-match-');
        }
        break;

      default:
        break;
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for domain.
   */
  #[Hook('domain_insert')]
  public function domainInsert($entity) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    if ($entity->isSyncing()) {
      return;
    }
    $controller = $this->entityTypeManager->getStorage('action');
    $id = 'domain_access_add_action_' . $entity->id();
    $check = $controller->load($id);
    if (is_null($check)) {
      /** @var \Drupal\system\Entity\Action $action */
      $action = $controller->create([
        'id' => $id,
        'type' => 'node',
        'label' => $this->t('Add selected content to the @label domain', ['@label' => $entity->label()]),
        'configuration' => [
          'domain_id' => [$entity->id()],
        ],
        'plugin' => 'domain_access_add_action',
      ]);
      $action->save();
    }
    $remove_id = 'domain_access_remove_action_' . $entity->id();
    $check = $controller->load($remove_id);
    if (is_null($check)) {
      /** @var \Drupal\system\Entity\Action $action */
      $action = $controller->create([
        'id' => $remove_id,
        'type' => 'node',
        'label' => $this->t('Remove selected content from the @label domain', ['@label' => $entity->label()]),
        'configuration' => [
          'domain_id' => [$entity->id()],
        ],
        'plugin' => 'domain_access_remove_action',
      ]);
      $action->save();
    }
    $id = 'domain_access_add_editor_action_' . $entity->id();
    $check = $controller->load($id);
    if (is_null($check)) {
      /** @var \Drupal\system\Entity\Action $action */
      $action = $controller->create([
        'id' => $id,
        'type' => 'user',
        'label' => $this->t('Add editors to the @label domain', ['@label' => $entity->label()]),
        'configuration' => [
          'domain_id' => [$entity->id()],
        ],
        'plugin' => 'domain_access_add_editor_action',
      ]);
      $action->save();
    }
    $remove_id = 'domain_access_remove_editor_action_' . $entity->id();
    $check = $controller->load($remove_id);
    if (is_null($check)) {
      /** @var \Drupal\system\Entity\Action $action */
      $action = $controller->create([
        'id' => $remove_id,
        'type' => 'user',
        'label' => $this->t('Remove editors from the @label domain', ['@label' => $entity->label()]),
        'configuration' => [
          'domain_id' => [$entity->id()],
        ],
        'plugin' => 'domain_access_remove_editor_action',
      ]);
      $action->save();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for domain.
   */
  #[Hook('domain_delete')]
  public function domainDelete(EntityInterface $entity) {
    $controller = $this->entityTypeManager->getStorage('action');
    $actions = $controller->loadMultiple([
      'domain_access_add_action.' . $entity->id(),
      'domain_access_remove_action.' . $entity->id(),
      'domain_access_add_editor_action.' . $entity->id(),
      'domain_access_remove_editor_action.' . $entity->id(),
    ]);
    foreach ($actions as $action) {
      $action->delete();
    }
  }

}
