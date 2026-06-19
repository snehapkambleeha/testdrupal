<?php

namespace Drupal\Tests\domain_source\Functional;

use Drupal\domain_source\HttpKernel\DomainSourceRouteMatcher;
use Drupal\Tests\domain\Functional\DomainTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that route match results are correctly cached per domain.
 *
 * Verifies that when different domains have different home page
 * configurations via system.site, the routeMatch() method returns
 * different cached results for each domain.
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourceRouteMatcherTest extends DomainTestBase {

  /**
   * Disabled config schema checking for domain config.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_source',
    'domain_config',
    'domain_config_test',
    'node',
    'system',
    'user',
    'language',
  ];

  /**
   * Test nodes for different home pages.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $testNodes;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test domains. This will create domains with IDs like
    // example_com, one_example_com, etc.
    // The domain_config_test module provides pre-configured domain configs.
    $this->domainCreateTestDomains(5);

    // Create test nodes that match the domain_config_test expectations.
    $this->testNodes[0] = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Node 1',
      'status' => 1,
    ]);
    $this->testNodes[1] = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Node 2',
      'status' => 1,
    ]);
  }

  /**
   * Tests that routeMatch returns different cached results per domain.
   */
  public function testRouteMatchReturnsDifferentResultsPerDomain() {
    // Load specific domains that have pre-configured home pages.
    // one_example_com has /node/1 configured as front page.
    // example_com has the default /node configured.
    $domain_storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domain_one = $domain_storage->load('one_example_com');
    $domain_default = $domain_storage->load('example_com');

    $this->assertNotNull($domain_one, 'Domain one_example_com should exist.');
    $this->assertNotNull($domain_default, 'Domain example_com should exist.');

    // Set one_example_com as active (has /node/1 as front page).
    $negotiator = \Drupal::service('domain.negotiator');
    $negotiator->setActiveDomain($domain_one);
    // Reset the static route provider to ensure a clean state.
    $this->resetRouteProviderStatic();

    // Call routeMatch for the home page with domain_one.
    $match1 = DomainSourceRouteMatcher::routeMatch('/');

    // Verify we got a match for domain_one that contains route information.
    $this->assertIsArray($match1, 'Route match should return an array.');
    $this->assertArrayHasKey('_route', $match1, 'Route match should contain _route.');

    // Domain_one should route to entity.node.canonical (because /node/1).
    $route1_name = $match1['_route'];
    $this->assertEquals('entity.node.canonical', $route1_name,
      'Domain one_example_com should route to node page.');

    // Now switch to example_com (has /node as front page).
    $negotiator->setActiveDomain($domain_default);
    // Reset the static route provider to force re-initialization.
    $this->resetRouteProviderStatic();

    // Call routeMatch for the home page with domain_default.
    $match2 = DomainSourceRouteMatcher::routeMatch('/');

    // Verify we got a match for domain_default.
    $this->assertIsArray($match2, 'Route match should return an array.');
    $this->assertArrayHasKey('_route', $match2, 'Route match should contain _route.');

    // Domain_default routes are different - it goes to system.404 or
    // a listing page, not a specific node.
    $route2_name = $match2['_route'];

    // CRITICAL: Verify the routes are actually different.
    // This proves that the cache is per-domain.
    $this->assertNotEquals($route1_name, $route2_name,
      'Different domains MUST return different cached routes.');
  }

  /**
   * Resets the static routeProvider property in DomainSourceRouteMatcher.
   */
  protected function resetRouteProviderStatic(): void {
    $reflection = new \ReflectionClass(DomainSourceRouteMatcher::class);
    $property = $reflection->getProperty('routeProvider');
    $property->setValue(NULL, NULL);
  }

}
