<?php

namespace Drupal\domain_source\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain_source\DomainSourceElementManagerInterface;
use Drupal\domain_source\DomainSourceHelperInterface;
use Drupal\domain_source\HttpKernel\DomainSourcePathProcessor;

/**
 * Entity hook implementations for domain_source.
 */
class DomainSourceEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected DomainSourcePathProcessor $pathProcessor,
    protected DomainSourceHelperInterface $sourceHelper,
  ) {}

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof FieldableEntityInterface
      && $entity->hasField(DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD)) {
      $this->pathProcessor->clearCache();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for nodes.
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $node) {
    $this->presaveGenerate($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node_type.
   */
  #[Hook('node_type_insert')]
  public function nodeTypeInsert(EntityInterface $entity) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    if (!$entity->isSyncing()) {
      $this->sourceHelper->confirmFields('node', $entity->id());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for domain.
   */
  #[Hook('domain_insert')]
  public function domainInsert(EntityInterface $entity) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    if (!$entity->isSyncing()) {
      $this->createDomainSourceSetAction($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for domain.
   */
  #[Hook('domain_delete')]
  public function domainDelete(EntityInterface $entity) {
    $action_storage = $this->entityTypeManager->getStorage('action');
    $actions = $action_storage->loadMultiple([
      'domain_source_set_action.' . $entity->id(),
    ]);
    foreach ($actions as $action) {
      $action->delete();
    }
  }

  /**
   * Handles presave operations for devel generate.
   */
  protected function presaveGenerate(EntityInterface $entity) {
    // Handle devel module settings if present.
    // @phpstan-ignore-next-line
    $exists = $this->moduleHandler->moduleExists('devel_generate') && $entity->hasField('devel_generate');
    $values = [];
    $selections = [];
    if ($exists && $entity instanceof FieldableEntityInterface && $entity->get('devel_generate') instanceof FieldItemListInterface) {
      // If set by the form.
      if (isset($entity->devel_generate['domain_access'])) {
        $selection = array_filter($entity->devel_generate['domain_access']);
        if (isset($selection['random-selection'])) {
          $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();
          $selections = array_rand($domains, ceil(rand(1, count($domains))));
        }
        else {
          $selections = array_keys($selection);
        }
      }
      if (isset($entity->devel_generate['domain_source'])) {
        $selection = $entity->devel_generate['domain_source'];
        if ($selection === '_derive') {
          // @phpstan-ignore-next-line
          if (!empty($selections)) {
            $values[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD] = current($selections);
          }
          else {
            $values[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD] = NULL;
          }
        }
        foreach ($values as $name => $value) {
          $entity->set($name, $value);
        }
      }
    }
  }

  /**
   * Create the source set action for a specific domain.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The domain entity.
   */
  public function createDomainSourceSetAction($entity) {
    $id = 'domain_source_set_action.' . $entity->id();
    $action_storage = $this->entityTypeManager->getStorage('action');
    if (!$action_storage->load($id)) {
      /** @var \Drupal\system\Entity\Action $action */
      $action = $action_storage->create([
        'id' => $id,
        'type' => 'node',
        'label' => $this->t('Set content domain source value to the @label domain', ['@label' => $entity->label()]),
        'configuration' => [
          'domain_id' => $entity->id(),
        ],
        'plugin' => 'domain_source_set_action',
      ]);
      $action->save();
    }
  }

}
