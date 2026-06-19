<?php

namespace Drupal\Tests\domain\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests page caching results.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainPageCacheTest extends DomainTestBase {

  /**
   * Tests that a domain response is proper.
   */
  public function testDomainResponse() {
    // No domains should exist.
    $this->domainTableIsEmpty();

    // Create a new domain programmatically.
    $this->domainCreateTestDomains(5);
    $expected = [];

    $domains = $this->getDomains();
    foreach ($domains as $domain) {
      $this->drupalGet($domain->getPath());
      // The page cache includes a colon at the end.
      $expected[] = $domain->getPath() . ':';
    }

    $database = \Drupal::database();
    $query = $database->query("SELECT cid FROM {cache_page}");
    $result = $query->fetchCol();

    $this->assertEquals(sort($expected), sort($result), 'Cache returns as expected.');

  }

}
