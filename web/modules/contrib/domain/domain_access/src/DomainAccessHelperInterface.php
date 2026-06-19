<?php

namespace Drupal\domain_access;

/**
 * Provides helper methods for domain access field management.
 */
interface DomainAccessHelperInterface {

  /**
   * Ensures domain access fields exist on a bundle.
   *
   * @param string $entity_type
   *   The entity type (e.g. 'node', 'user').
   * @param string $bundle
   *   The bundle machine name.
   * @param array $text
   *   Optional text overrides keyed by entity type.
   */
  public function confirmFields(string $entity_type, string $bundle, array $text = []): void;

}
