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
class DomainConfigCacheTest extends DomainConfigTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain_access',
    'domain_config',
  ];

  /**
   * Tests that a domain response is proper.
   */
  public function testDomainResponse() {
    // No domains should exist.
    $this->domainTableIsEmpty();

    // Create a new domain programmatically.
    $this->domainCreateTestDomains(5);

    // Initialize expected with current cache entries.
    $database = \Drupal::database();
    $query = $database->query("SELECT cid FROM {cache_page}");
    $expected = $query->fetchCol();

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

    $expected = array_unique($expected);
    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result, 'Cache returns as expected.');

    // Now create a node and test the cache.
    // Create an article node assigned to two domains.
    $ids = ['example_com', 'four_example_com'];
    $node1 = $this->drupalCreateNode([
      'type' => 'article',
      'field_domain_access' => [$ids],
      'path' => '/test',
    ]);

    $domain_by_cid = [];
    foreach ($domains as $domain) {
      $this->drupalGet($domain->getPath() . 'test');
      // The page cache includes a colon at the end.
      $cid = $domain->getPath() . 'test:';
      // Add the cache ID for the node.
      $expected[] = $cid;
      // Store the domain for later use.
      $domain_by_cid[$cid] = $domain;
    }

    $query = $database->query("SELECT cid FROM {cache_page}");
    $result = $query->fetchCol();

    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result, 'Cache returns as expected.');
  }

}
