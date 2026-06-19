<?php

namespace Drupal\Tests\domain_config\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests page caching results.
 *
 * @group domain_config
 */
#[Group('domain_config')]
#[RunTestsInSeparateProcesses]
class DomainConfigPageCacheTest extends DomainConfigTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_config',
    'domain_config_test',
    'domain_config_middleware_test',
  ];

  /**
   * Tests that a domain response is proper.
   */
  public function testDomainResponse() {
    // No domains should exist.
    $this->domainTableIsEmpty();

    // Create a new domain programmatically.
    $this->domainCreateTestDomains(5);
    $expected = [];

    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    foreach ($domains as $domain) {
      $this->drupalGet($domain->getPath());
      // The page cache includes a colon at the end.
      $expected[] = $domain->getPath() . ':';
    }

    $database = \Drupal::database();
    $query = $database->query("SELECT cid FROM {cache_page}");
    $result = $query->fetchCol();

    $this->assertEquals(sort($expected), sort($result), implode(', ', $result));

  }

}
