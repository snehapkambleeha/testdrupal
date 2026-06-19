<?php

namespace Drupal\Tests\domain_source\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\PathProcessorManager;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain_source\HttpKernel\DomainSourceRouteProvider;
use Drupal\domain_source\HttpKernel\DomainSourceRouteMatcher;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that cache IDs include the domain ID.
 *
 * Verifies that when DomainSourceRouteMatcher::getRouteProvider() injects
 * the domain ID via addExtraCacheKeyPart(), the cache ID returned by
 * DomainSourceRouteProvider::getRouteCollectionCacheId() includes it.
 *
 * @coversDefaultClass \Drupal\domain_source\HttpKernel\DomainSourceRouteProvider
 * @group domain_source
 */
#[Group('domain_source')]
class DomainSourceRouterProviderTest extends UnitTestCase {

  /**
   * The domain source route provider.
   *
   * @var \Drupal\domain_source\HttpKernel\DomainSourceRouteProvider
   */
  protected $routeProvider;

  /**
   * The mocked domain negotiation context.
   *
   * @var \Drupal\domain\DomainNegotiationContext|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $domainNegotiationContext;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock all the dependencies required by RouteProvider.
    $database = $this->createMock(Connection::class);
    $state = $this->createMock(StateInterface::class);
    $current_path = $this->createMock(CurrentPathStack::class);
    $cache = $this->createMock(CacheBackendInterface::class);
    $path_processor = $this->createMock(PathProcessorManager::class);
    $cache_tags_invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);

    // Mock language manager to return a language.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($language);

    // Configure path processor to return the path as-is.
    $path_processor->method('processInbound')
      ->willReturnCallback(function ($path) {
        return $path;
      });

    // Create the actual DomainSourceRouteProvider with all dependencies.
    $this->routeProvider = new DomainSourceRouteProvider(
      $database,
      $state,
      $current_path,
      $cache,
      $path_processor,
      $cache_tags_invalidator,
      'router',
      $language_manager
    );

    // Mock domain negotiation context.
    $this->domainNegotiationContext = $this->createMock(DomainNegotiationContext::class);

    // Set up the container.
    $this->setupContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();

    // Reset the static properties.
    $reflection = new \ReflectionClass(DomainSourceRouteMatcher::class);
    $property = $reflection->getProperty('routeProvider');
    $property->setValue(NULL, NULL);
  }

  /**
   * Tests that cache ID includes the domain ID.
   *
   * @covers ::getRouteCollectionCacheId
   */
  public function testCacheIdIncludesDomainId() {
    $domain_id = 'example_com';

    // Configure the domain negotiation context to return a specific domain ID.
    $this->domainNegotiationContext->method('getDomainId')->willReturn($domain_id);

    // Reset the static route provider.
    $reflection = new \ReflectionClass(DomainSourceRouteMatcher::class);
    $property = $reflection->getProperty('routeProvider');
    $property->setValue(NULL, NULL);

    // Call DomainSourceRouteMatcher::getRouteProvider()
    // which injects the domain.
    $method = $reflection->getMethod('getRouteProvider');
    $method->invoke(NULL);

    // Create a request.
    $request = Request::create('/test-path');

    // Get the cache ID using reflection.
    $reflection = new \ReflectionClass($this->routeProvider);
    $get_cache_id_method = $reflection->getMethod('getRouteCollectionCacheId');
    $cache_id = $get_cache_id_method->invoke($this->routeProvider, $request);

    // Verify that the cache ID contains the domain identifier.
    $this->assertStringContainsString('[domain]=' . $domain_id, $cache_id, 'Cache ID should contain the domain ID.');
    $this->assertStringContainsString('/test-path', $cache_id, 'Cache ID should contain the path.');
  }

  /**
   * Tests that different domains produce different cache IDs.
   *
   * @covers ::getRouteCollectionCacheId
   */
  public function testDifferentDomainsProduceDifferentCacheIds() {
    $domain_id1 = 'example_com';
    $domain_id2 = 'test_com';

    // Configure for first domain.
    $this->domainNegotiationContext->method('getDomainId')->willReturn($domain_id1);

    // Reset and call getRouteProvider for first domain.
    $this->resetRouteProviderStatic();
    $this->callGetRouteProvider();

    // Get cache ID for first domain.
    $request = Request::create('/test-path');
    $cache_id1 = $this->getRouteCollectionCacheId($request);

    // Now configure for second domain.
    $this->domainNegotiationContext = $this->createMock(DomainNegotiationContext::class);
    $this->domainNegotiationContext->method('getDomainId')->willReturn($domain_id2);

    // Update container with new negotiator.
    $this->setupContainer();

    // Reset and call getRouteProvider for second domain.
    $this->resetRouteProviderStatic();
    $this->callGetRouteProvider();

    // Get cache ID for second domain.
    $cache_id2 = $this->getRouteCollectionCacheId($request);

    // Verify both cache IDs contain their respective domain IDs.
    $this->assertStringContainsString('[domain]=' . $domain_id1, $cache_id1);
    $this->assertStringContainsString('[domain]=' . $domain_id2, $cache_id2);

    // Verify the cache IDs are different.
    $this->assertNotEquals($cache_id1, $cache_id2, 'Cache IDs should be different for different domains.');
  }

  /**
   * Helper method to reset the static route provider property.
   */
  protected function resetRouteProviderStatic(): void {
    $reflection = new \ReflectionClass(DomainSourceRouteMatcher::class);
    $property = $reflection->getProperty('routeProvider');
    $property->setValue(NULL, NULL);
  }

  /**
   * Helper method to call getRouteProvider.
   */
  protected function callGetRouteProvider(): void {
    $reflection = new \ReflectionClass(DomainSourceRouteMatcher::class);
    $method = $reflection->getMethod('getRouteProvider');
    $method->invoke(NULL);
  }

  /**
   * Helper method to get the route collection cache ID.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The cache ID.
   */
  protected function getRouteCollectionCacheId(Request $request): string {
    $reflection = new \ReflectionClass($this->routeProvider);
    $method = $reflection->getMethod('getRouteCollectionCacheId');
    return $method->invoke($this->routeProvider, $request);
  }

  /**
   * Sets up a mock container with required services for the test.
   *
   * Creates a mock container with the domain negotiation context and domain
   * source route provider services. This allows the test to run without a full
   * Drupal bootstrap while still providing access to necessary dependencies.
   *
   * The following services are mocked:
   * - domain.negotiation_context: Handles domain context negotiation.
   * - domain_source.route_provider: Provides domain-aware routing.
   *
   * @see \Drupal\domain\DomainNegotiationContextInterface
   * @see \Drupal\domain_source\HttpKernel\DomainSourceRouteProvider
   */
  protected function setupContainer() {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        [
          'domain.negotiation_context',
          ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
          $this->domainNegotiationContext,
        ],
        [
          'domain_source.route_provider',
          ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
          $this->routeProvider,
        ],
      ]);

    \Drupal::setContainer($container);
  }

}
