<?php

namespace Drupal\domain_source;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\domain\DomainInterface;

/**
 * Provides helper methods for domain source field lookups.
 */
class DomainSourceHelper implements DomainSourceHelperInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSourceDomain(EntityInterface $entity): ?DomainInterface {
    $field = DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field)) {
      return NULL;
    }

    // Check that the entity translation has not been removed.
    // See https://www.drupal.org/i/3568699.
    if ($entity instanceof ContentEntityInterface) {
      $langcode = $entity->language()->getId();
      if (!$entity->hasTranslation($langcode)) {
        return NULL;
      }
    }

    $item = $entity->get($field)->first();
    if (!$item instanceof EntityReferenceItem) {
      return NULL;
    }

    // See https://www.drupal.org/i/3565121
    $domain = $item->get('entity')?->getValue();
    if (!$domain instanceof DomainInterface) {
      return NULL;
    }

    return $domain;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceDomainId(EntityInterface $entity): ?string {
    return $this->getSourceDomain($entity)?->id();
  }

  /**
   * {@inheritdoc}
   */
  public function confirmFields(string $entity_type, string $bundle): void {
    $field_name = DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;
    $id = $entity_type . '.' . $bundle . '.' . $field_name;
    $field_config_storage = $this->entityTypeManager->getStorage('field_config');
    $field = $field_config_storage->load($id);
    if (is_null($field)) {
      $field = [
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'label' => 'Domain Source',
        'bundle' => $bundle,
        'required' => FALSE,
        'description' => 'Select the canonical domain for this content.',
        'settings' => [
          'handler' => 'default:domain',
          // Handler_settings are deprecated but necessary.
          'handler_settings' => [
            'target_bundles' => NULL,
            'sort' => [
              'field' => 'weight',
              'direction' => 'ASC',
            ],
          ],
          'target_bundles' => NULL,
          'sort' => [
            'field' => 'weight',
            'direction' => 'ASC',
          ],
        ],
      ];
      $field_config = $field_config_storage->create($field);
      $field_config->save();
    }

    // Tell the form system how to behave.
    $display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load($entity_type . '.' . $bundle . '.default');
    if ($display instanceof EntityInterface) {
      $display->setComponent($field_name, [
        'type' => 'options_select',
        'weight' => 42,
      ])->save();
    }
  }

}
