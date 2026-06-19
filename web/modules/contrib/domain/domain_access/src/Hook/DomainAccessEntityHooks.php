<?php

namespace Drupal\domain_access\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain_access\DomainAccessHelperInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Entity hook implementations for domain_access.
 */
class DomainAccessEntityHooks {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected DomainNegotiatorInterface $negotiator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainAccessHelperInterface $accessHelper,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave() for nodes.
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $node) {
    $this->presaveGenerate($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for users.
   */
  #[Hook('user_presave')]
  public function userPresave(EntityInterface $account) {
    $this->presaveGenerate($account);
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity) {
    if ($entity instanceof FieldableEntityInterface) {
      DomainAccessManager::clearStaticCache(
        $entity->id(),
        $entity->getEntityTypeId()
      );
    }
  }

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity) {
    if ($entity instanceof FieldableEntityInterface) {
      DomainAccessManager::clearStaticCache(
        $entity->id(),
        $entity->getEntityTypeId()
      );
    }
  }

  /**
   * Implements hook_entity_prepare_form().
   */
  #[Hook('entity_prepare_form')]
  public function entityPrepareForm(EntityInterface $entity, $operation, FormStateInterface $form_state) {
    if ($entity instanceof ContentEntityInterface) {
      if ($entity->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)) {
        $field = $entity->get(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);
        $field_definition = $field->getFieldDefinition();
        if (in_array($operation, ['default', 'register']) && $entity->isNew()
          && $field_definition instanceof ThirdPartySettingsInterface
          && $field_definition->getThirdPartySetting('domain_access', 'add_current_domain')) {
          /** @var \Drupal\domain\DomainInterface $active_domain */
          $active_domain = $this->negotiator->getActiveDomain();
          if ($active_domain instanceof DomainInterface) {
            $contains_active_domain = FALSE;
            /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
            foreach ($field as $item) {
              if ($item->get('target_id')->getValue() === $active_domain->id()) {
                $contains_active_domain = TRUE;
                break;
              }
            }
            if (empty($contains_active_domain)) {
              $field[] = ['target_id' => $active_domain->id()];
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL) {
    if ($operation !== 'edit' || is_null($items)) {
      return AccessResult::neutral();
    }

    $entity = $items->getEntity();

    if ($field_definition->getName() === DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD) {
      if ($entity instanceof AccountInterface) {
        $access = AccessResult::allowedIfHasPermissions($account, [
          'assign domain editors',
          'assign editors to any domain',
        ], 'OR');
      }
      elseif ($entity instanceof NodeInterface) {
        $access = AccessResult::allowedIfHasPermissions($account, [
          'publish to any domain',
          'publish to any assigned domain',
        ], 'OR');
      }
      if (isset($access) && !$access->isAllowed()) {
        return AccessResult::forbidden();
      }
    }
    elseif ($field_definition->getName() === DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD) {
      if ($entity instanceof AccountInterface) {
        return AccessResult::forbiddenIf(!$account->hasPermission('assign editors to any domain'));
      }
      elseif ($entity instanceof NodeInterface) {
        return AccessResult::forbiddenIf(!$account->hasPermission('publish to any domain'));
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node_type.
   */
  #[Hook('node_type_insert')]
  public function nodeTypeInsert(EntityInterface $entity) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    if (!$entity->isSyncing()) {
      $this->accessHelper->confirmFields('node', $entity->id());
    }
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(&$fields, EntityTypeInterface $entity_type, $bundle) {
    if ($entity_type->id() === 'node') {
      if (isset($fields[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD])) {
        $fields[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD]->setDefaultValueCallback(
          'Drupal\domain_access\DomainAccessManager::getDefaultValue'
        );
      }
    }
  }

  /**
   * Handles presave operations for devel generate.
   */
  protected function presaveGenerate(EntityInterface $entity) {
    if (!($entity instanceof FieldableEntityInterface) ||
      !$entity->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD)) {
      return;
    }

    $value = (int) $entity->get(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD)->value;
    $entity->set(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD, $value);

    $exists = $this->moduleHandler->moduleExists('devel_generate') && $entity->hasField('devel_generate');
    $values = [];
    if ($exists && isset($entity->devel_generate)) {
      if (isset($entity->devel_generate['domain_access'])) {
        $selection = array_filter($entity->devel_generate['domain_access']);
        if (isset($selection['random-selection'])) {
          $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();
          $values[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD] = array_rand($domains, ceil(rand(1, count($domains))));
        }
        else {
          $values[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD] = array_keys($selection);
        }
      }
      if (isset($entity->devel_generate['domain_all'])) {
        $selection = $entity->devel_generate['domain_all'];
        if ($selection === 'random-selection') {
          $values[DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD] = rand(0, 1);
        }
        else {
          $values[DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD] = ($selection === 'yes' ? 1 : 0);
        }
      }
      // @phpstan-ignore-next-line
      foreach ($values as $name => $value) {
        $entity->set($name, $value);
      }
    }
  }

}
