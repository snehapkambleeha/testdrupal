<?php

namespace Drupal\domain_source;

use Drupal\Core\Entity\EntityInterface;
use Drupal\domain\DomainInterface;

/**
 * Provides helper methods for domain source field lookups.
 */
interface DomainSourceHelperInterface {

  /**
   * Returns the source domain for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return \Drupal\domain\DomainInterface|null
   *   The domain assigned as source, or NULL.
   */
  public function getSourceDomain(EntityInterface $entity): ?DomainInterface;

  /**
   * Returns the source domain id for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return string|null
   *   The domain id, or NULL.
   */
  public function getSourceDomainId(EntityInterface $entity): ?string;

  /**
   * Ensures the domain source field exists on a bundle.
   *
   * @param string $entity_type
   *   The entity type (e.g. 'node').
   * @param string $bundle
   *   The bundle machine name.
   */
  public function confirmFields(string $entity_type, string $bundle): void;

}
