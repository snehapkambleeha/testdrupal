<?php

namespace Drupal\Tests\domain_source\Functional;

use Drupal\domain_source\HttpKernel\DomainSourceRouteMatcher;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that route cache IDs include the domain ID.
 *
 * This test verifies the complete integration: when
 * DomainSourceRouteMatcher::getRouteProvider() injects the domain ID via
 * addExtraCacheKeyPart(), the cache ID returned by
 * DomainSourceRouteProvider::getRouteCollectionCacheId() includes it.
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourceRouterProviderTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_source',
    'node',
  ];

  /**
   * The domain source route provider service.
   *
   * @var \Drupal\domain_source\HttpKernel\DomainSourceRouteProvider
   */
  protected $routeProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create two test domains.
    $this->domainCreateTestDomains(2);

    // Get the route provider service.
    $this->routeProvider = $this->container->get('domain_source.route_provider');
  }

  /**
   * Tests that cache ID includes the domain ID.
   */
  public function testCacheIdIncludesDomainId() {
    // Load the domains.
    $domains = \Drupal::entityTypeManager()
      ->getStorage('domain')
      ->loadMultiple();
    $domain_values = array_values($domains);
    $domain1 = $domain_values[0];
    $domain2 = $domain_values[1];

    // Create a request.
    $request = Request::create('/node');

    // Set the first domain as active.
    \Drupal::service('domain.negotiator')->setActiveDomain($domain1);

    // Reset and reinitialize for the first domain.
    $this->resetRouteProviderStatic();

    // Get the cache ID.
    $cache_id1 = $this->getRouteCollectionCacheId($request);

    // Verify that the cache ID contains the domain identifier.
    $this->assertStringContainsString(
      '[domain]=' . $domain1->id(),
      $cache_id1,
      'Cache ID should contain the first domain ID.'
    );

    // Now switch to the second domain.
    \Drupal::service('domain.negotiator')->setActiveDomain($domain2);

    // Reset and reinitialize for the second domain.
    $this->resetRouteProviderStatic();

    // Get the cache ID for the second domain.
    $cache_id2 = $this->getRouteCollectionCacheId($request);

    // Verify that the cache ID contains the second domain identifier.
    $this->assertStringContainsString(
      '[domain]=' . $domain2->id(),
      $cache_id2,
      'Cache ID should contain the second domain ID.'
    );
  }

  /**
   * Tests the cache ID format.
   */
  public function testCacheIdFormat() {
    // Load a domain.
    $domains = \Drupal::entityTypeManager()
      ->getStorage('domain')
      ->loadMultiple();
    $domain = reset($domains);

    // Set the domain as active.
    \Drupal::service('domain.negotiator')->setActiveDomain($domain);

    // Reset and initialize.
    $this->resetRouteProviderStatic();

    // Create a request.
    $request = Request::create('/node');

    // Get the cache ID.
    $cache_id = $this->getRouteCollectionCacheId($request);

    // Verify the format: route:[key1]=val1:[key2]=val2:...:/path.
    $this->assertMatchesRegularExpression(
      '/^route:.*\[domain\]=' . preg_quote($domain->id(), '/') . '.*:\/node$/',
      $cache_id,
      'Cache ID should have the correct format with domain information.'
    );
  }

  /**
   * Resets the static routeProvider property in DomainSourceRouteMatcher.
   *
   * This method uses reflection to reset the static route provider property
   * to NULL, forcing re-initialization of the route provider. This is necessary
   * to ensure that domain-specific cache key parts are properly injected when
   * switching between different active domains during testing.
   */
  protected function resetRouteProviderStatic(): void {
    // Reset the static route provider to force re-initialization.
    $reflection = new \ReflectionClass(DomainSourceRouteMatcher::class);
    $property = $reflection->getProperty('routeProvider');
    $property->setValue(NULL, NULL);
    // Call getRouteProvider() which injects the domain ID.
    $method = $reflection->getMethod('getRouteProvider');
    $method->invoke(NULL);
  }

  /**
   * Gets the route collection cache ID from the route provider.
   *
   * This helper method uses reflection to access the protected
   * getRouteCollectionCacheId method from the parent RouteProvider class.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return string
   *   The cache ID for the route collection.
   */
  protected function getRouteCollectionCacheId(Request $request): string {
    // Get the cache ID for the second domain.
    $reflection = new \ReflectionClass($this->routeProvider);
    $get_cache_id_method = $reflection->getMethod('getRouteCollectionCacheId');
    return $get_cache_id_method->invoke($this->routeProvider, $request);
  }

}
