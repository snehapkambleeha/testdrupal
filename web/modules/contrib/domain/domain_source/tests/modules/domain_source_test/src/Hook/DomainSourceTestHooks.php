<?php

namespace Drupal\domain_source_test\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for domain_source_test.
 */
class DomainSourceTestHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_domain_source_alter().
   */
  #[Hook('domain_source_alter')]
  public function domainSourceAlter(&$source, $path, $options) {
    // Always make our test REST links go to the primary domain.
    $parts = explode('/', $path);
    if (isset($parts[1]) && $parts[1] === 'domain-format-test') {
      /** @var \Drupal\domain\DomainStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('domain');
      /** @var \Drupal\domain\DomainInterface $source */
      $source = $storage->loadDefaultDomain();
    }
  }

}
