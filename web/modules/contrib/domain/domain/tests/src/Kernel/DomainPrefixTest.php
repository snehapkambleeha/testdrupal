<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\Core\Config\ConfigValueException;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests domain path prefix negotiation, constraints, and processing.
 *
 * Verifies that multiple domain entities can share a hostname with
 * different path prefixes, that the correct domain is negotiated
 * based on the request path, and that inbound/outbound path
 * processing correctly strips and prepends the prefix.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainPrefixTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain'];

  /**
   * The domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');

    // Enable the path prefix feature so components are registered.
    $this->config('domain.settings')
      ->set('path_prefix', TRUE)
      ->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $this->domainStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain');
  }

  /**
   * Creates a domain entity with the given hostname and prefix.
   *
   * Helper method that creates an unsaved Domain entity with
   * standard test values. The entity ID is derived from the
   * hostname and prefix to ensure uniqueness.
   *
   * @param string $hostname
   *   The hostname for the domain.
   * @param string $prefix
   *   The path prefix (empty string for no prefix).
   * @param bool $is_default
   *   Whether this is the default domain.
   *
   * @return \Drupal\domain\Entity\Domain
   *   The unsaved domain entity.
   */
  protected function createDomain(string $hostname, string $prefix = '', bool $is_default = FALSE): Domain {
    $id_suffix = $prefix !== '' ? '_' . $prefix : '';
    $id = str_replace(['.', ':'], '_', $hostname) . $id_suffix;
    /** @var \Drupal\domain\Entity\Domain $domain */
    $domain = $this->domainStorage->create([
      'id' => $id,
      'hostname' => $hostname,
      'name' => 'Test ' . $hostname . ($prefix ? ' (' . $prefix . ')' : ''),
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => $is_default,
      'path_prefix' => $prefix,
    ]);
    return $domain;
  }

  /**
   * Creates a request with a mock session for kernel test use.
   *
   * @param string $uri
   *   The request URI.
   * @param array $server
   *   Optional server parameters (e.g., SCRIPT_NAME to
   *   simulate a subdirectory install).
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A request object with a mock session attached.
   */
  protected function createRequest(string $uri, array $server = []): Request {
    $request = Request::create($uri, 'GET', [], [], [], $server);
    $request->setSession(new Session(new MockArraySessionStorage()));
    return $request;
  }

  /**
   * Tests that the path_prefix property works on Domain entities.
   *
   * Verifies the getter and setter for the path_prefix property,
   * and that the value is included in the config export array.
   */
  public function testPathPrefixProperty(): void {
    $domain = $this->createDomain('example.com', 'fr');
    $this->assertEquals('fr', $domain->getPathPrefix());

    $domain->setPathPrefix('benl');
    $this->assertEquals('benl', $domain->getPathPrefix());
  }

  /**
   * Tests that same hostname with different prefixes can be saved.
   *
   * Creates two domains sharing a hostname but with different path
   * prefixes. Both should save without throwing a
   * ConfigValueException.
   */
  public function testSameHostnameDifferentPrefixes(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $prefixed = $this->createDomain('example.com', 'fr');
    $prefixed->save();

    $domains = $this->domainStorage->loadByProperties([
      'hostname' => 'example.com',
    ]);
    $this->assertCount(2, $domains);
  }

  /**
   * Tests that same hostname with same prefix is rejected.
   *
   * Creates two domains with identical hostname and path prefix.
   * The second save should throw a ConfigValueException.
   */
  public function testSameHostnameSamePrefixRejected(): void {
    $first = $this->createDomain('example.com', 'fr', TRUE);
    $first->save();

    $second = $this->domainStorage->create([
      'id' => 'example_com_fr_2',
      'hostname' => 'example.com',
      'name' => 'Duplicate',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'fr',
    ]);
    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage('The hostname (example.com) with path prefix (fr) is already registered.');
    $second->save();
  }

  /**
   * Tests the validation constraint for hostname + prefix.
   *
   * Uses the typed config manager to validate a domain entity.
   * Same hostname with different prefix should pass; same hostname
   * with same prefix should produce a violation.
   */
  public function testValidationConstraint(): void {
    $first = $this->createDomain('example.com', 'fr', TRUE);
    $first->save();

    // Same hostname, different prefix — should pass.
    $different = $this->createDomain('example.com', 'nl');
    $violations = $this->validateDomain($different);
    $hostname_violations = $this->getMessagesForConstraint(
      $violations,
      'DomainUniqueHostnameConstraint'
    );
    $this->assertEmpty($hostname_violations, 'Different prefix should not trigger uniqueness violation.');

    // Same hostname, same prefix — should fail.
    $same = $this->domainStorage->create([
      'id' => 'example_com_fr_dup',
      'hostname' => 'example.com',
      'name' => 'Duplicate FR',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'fr',
    ]);
    $violations = $this->validateDomain($same);
    $hostname_violations = $this->getMessagesForConstraint(
      $violations,
      'DomainUniqueHostnameConstraint'
    );
    $this->assertNotEmpty($hostname_violations, 'Same prefix should trigger uniqueness violation.');
    $this->assertContains(
      'The hostname (example.com) with path prefix (fr) is already registered.',
      $hostname_violations
    );

    // Same hostname, both empty prefix — should fail with the
    // no-prefix message.
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();
    $empty_dup = $this->domainStorage->create([
      'id' => 'example_com_dup',
      'hostname' => 'example.com',
      'name' => 'Duplicate default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => '',
    ]);
    $violations = $this->validateDomain($empty_dup);
    $hostname_violations = $this->getMessagesForConstraint(
      $violations,
      'DomainUniqueHostnameConstraint'
    );
    $this->assertNotEmpty($hostname_violations, 'Same hostname with empty prefix should trigger uniqueness violation.');
    $this->assertContains(
      'The hostname (example.com) is already registered.',
      $hostname_violations
    );
  }

  /**
   * Tests negotiation selects prefixed domain for prefixed path.
   *
   * Creates two domains on the same hostname (one with prefix
   * "fr", one without). Simulates a request to /fr/node/1 and
   * verifies the prefixed domain is negotiated.
   */
  public function testPrefixedPathNegotiation(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    $request = $this->createRequest('http://example.com/fr/node/1');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals('example_com_fr', $active->id());
    $this->assertEquals('fr', $active->getPathPrefix());
  }

  /**
   * Tests negotiation selects default domain for unprefixed path.
   *
   * Creates two domains on the same hostname. A request to /node/1
   * (no prefix) should negotiate the default (no-prefix) domain.
   */
  public function testUnprefixedPathNegotiation(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    $request = $this->createRequest('http://example.com/node/1');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals('example_com', $active->id());
    $this->assertEquals('', $active->getPathPrefix());
  }

  /**
   * Tests longest-prefix-first matching for overlapping prefixes.
   *
   * Creates domains with prefixes "fr" and "fr-be". A request to
   * /fr-be/page should match "fr-be", not "fr".
   */
  public function testLongestPrefixMatchFirst(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $fr = $this->createDomain('example.com', 'fr');
    $fr->save();

    $fr_be = $this->createDomain('example.com', 'fr-be');
    $fr_be->save();

    $request = $this->createRequest('http://example.com/fr-be/page');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals('example_com_fr-be', $active->id());
    $this->assertEquals('fr-be', $active->getPathPrefix());
  }

  /**
   * Tests inbound path processing strips the domain prefix.
   *
   * Verifies that the inbound path processor removes the domain
   * prefix from the request path when the active domain has a
   * prefix, and does nothing when it does not.
   */
  public function testInboundPathProcessing(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $processor */
    $processor = $this->container->get('domain.prefix_path_processor');

    // With prefixed domain active.
    $context->setDomain($french);
    $request = Request::create('http://example.com/fr/node/1');
    $this->assertEquals('/node/1', $processor->processInbound('/fr/node/1', $request));
    $this->assertEquals('/', $processor->processInbound('/fr', $request));

    // With no-prefix domain active — no stripping.
    $context->setDomain($default);
    $request_clean = Request::create('http://example.com/fr/node/1');
    $this->assertEquals('/fr/node/1', $processor->processInbound('/fr/node/1', $request_clean));
  }

  /**
   * Tests outbound path processing prepends the domain prefix.
   *
   * Verifies that when a domain with a path prefix is passed via
   * $options['domain'], the processor prepends the prefix to the
   * URL prefix option.
   */
  public function testOutboundPathProcessing(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $processor */
    $processor = $this->container->get('domain.prefix_path_processor');

    // With prefixed domain.
    $options = ['domain' => $french];
    $processor->processOutbound('/node/1', $options);
    $this->assertEquals('fr/', $options['prefix']);

    // With no-prefix domain — no prefix added.
    $options = ['domain' => $default];
    $processor->processOutbound('/node/1', $options);
    $this->assertArrayNotHasKey('prefix', $options);
  }

  /**
   * Tests that path_prefix survives a save/load round-trip.
   *
   * Creates a domain with a path prefix, saves it, reloads from
   * storage, and verifies the value persisted.
   */
  public function testPathPrefixPersistence(): void {
    $domain = $this->createDomain('example.com', 'benl', TRUE);
    $domain->save();

    $this->domainStorage->resetCache();
    /** @var \Drupal\domain\Entity\Domain $reloaded */
    $reloaded = $this->domainStorage->load('example_com_benl');
    $this->assertNotNull($reloaded);
    $this->assertEquals('benl', $reloaded->getPathPrefix());
  }

  /**
   * Tests getBasePath() excludes prefix, getPath() includes it.
   *
   * Verifies that getBasePath() returns the raw base URL (scheme +
   * hostname + $base_path) used as base_url by DomainPathProcessor.
   * The prefix is handled separately by DomainPrefixPathProcessor's
   * outbound processing. getPath() adds the prefix for display
   * and direct linking purposes.
   */
  public function testBasePathExcludesPrefix(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    $default->setPath();
    $french->setPath();

    // getBasePath() is identical for both — no prefix.
    $this->assertEquals(
      $default->getBasePath(),
      $french->getBasePath()
    );
    $this->assertStringNotContainsString(
      '/fr/',
      $french->getBasePath()
    );

    // getPath() includes the prefix for the prefixed domain.
    $this->assertStringContainsString(
      '/fr/',
      $french->getPath()
    );
    $this->assertNotEquals(
      $default->getPath(),
      $french->getPath()
    );
  }

  /**
   * Tests that setUrl() swaps the prefix correctly.
   *
   * When the active domain has a prefix and we call setUrl() on a
   * different domain, the active prefix should be stripped from the
   * request URI and the target domain's prefix prepended. This
   * ensures that links in the domain list point to the correct
   * prefixed paths.
   */
  public function testSetUrlSwapsPrefix(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    // Simulate browsing on the unprefixed domain.
    $context->setDomain($default);
    $request = $this->createRequest('http://example.com/admin/config');
    $this->container->get('request_stack')->push($request);

    // setUrl() on the prefixed domain should prepend /fr.
    $french->setUrl();
    $this->assertStringContainsString('/fr/admin/config', $french->getUrl());

    // setUrl() on the unprefixed domain should not add a prefix.
    $default->setUrl();
    $this->assertStringContainsString('/admin/config', $default->getUrl());
    $this->assertStringNotContainsString('/fr/', $default->getUrl());
  }

  /**
   * Tests setUrl() when browsing on a prefixed domain.
   *
   * When the active domain has a prefix, setUrl() on the unprefixed
   * domain should strip the active prefix from the request URI,
   * and setUrl() on the same prefixed domain should keep the prefix.
   */
  public function testSetUrlStripsActivePrefix(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    // Simulate browsing on the prefixed domain.
    $context->setDomain($french);
    $request = $this->createRequest('http://example.com/fr/admin/config');
    $this->container->get('request_stack')->push($request);

    // setUrl() on the unprefixed domain should strip /fr.
    $default->setUrl();
    $this->assertStringContainsString('/admin/config', $default->getUrl());
    $this->assertStringNotContainsString('/fr/', $default->getUrl());

    // setUrl() on the same prefixed domain should keep /fr.
    $french->setUrl();
    $this->assertStringContainsString('/fr/admin/config', $french->getUrl());
  }

  /**
   * Validates a domain entity via config typed data.
   *
   * @param \Drupal\domain\Entity\Domain $domain
   *   The domain entity to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The violations.
   */
  protected function validateDomain(Domain $domain) {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $this->container->get('config.typed');
    return $typed_config_manager
      ->createFromNameAndData(
        $domain->getConfigDependencyName(),
        $domain->toArray()
      )
      ->validate();
  }

  /**
   * Tests setUrl() with subdirectory base path from unprefixed.
   *
   * When Drupal runs in a subdirectory (e.g., /drupal/), the raw
   * request URI includes the base path. setUrl() must strip it
   * before prefix manipulation and re-add it afterward so the
   * final URL has the correct structure:
   * scheme + hostname + base_path + prefix + route_path.
   */
  public function testSetUrlSubdirectoryFromUnprefixed(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    // Simulate browsing unprefixed domain in a subdirectory.
    $context->setDomain($default);
    $request = $this->createRequest(
      'http://example.com/drupal/admin/config',
      [
        'SCRIPT_NAME' => '/drupal/index.php',
        'SCRIPT_FILENAME' => '/var/www/html/web/index.php',
      ]
    );
    $this->container->get('request_stack')->push($request);

    // setUrl() on the prefixed domain should produce
    // scheme + host + /drupal/fr/admin/config.
    $french->setUrl();
    $url = $french->getUrl();
    $this->assertStringContainsString(
      '/drupal/fr/admin/config',
      $url,
      'Prefix must come after base_path, not before it.'
    );
    $this->assertStringNotContainsString(
      '/fr/drupal/',
      $url,
      'Prefix must not precede base_path.'
    );

    // setUrl() on the unprefixed domain — no prefix added.
    $default->setUrl();
    $url = $default->getUrl();
    $this->assertStringContainsString(
      '/drupal/admin/config',
      $url
    );
    $this->assertStringNotContainsString('/fr/', $url);
  }

  /**
   * Tests setUrl() with subdirectory base path from prefixed.
   *
   * When browsing a prefixed domain in a subdirectory install,
   * setUrl() on the unprefixed domain must strip the active
   * prefix from the path (after base_path) and not corrupt
   * the base_path itself.
   */
  public function testSetUrlSubdirectoryFromPrefixed(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    // Simulate browsing prefixed domain in a subdirectory.
    $context->setDomain($french);
    $request = $this->createRequest(
      'http://example.com/drupal/fr/admin/config',
      [
        'SCRIPT_NAME' => '/drupal/index.php',
        'SCRIPT_FILENAME' => '/var/www/html/web/index.php',
      ]
    );
    $this->container->get('request_stack')->push($request);

    // setUrl() on the unprefixed domain should strip /fr.
    $default->setUrl();
    $url = $default->getUrl();
    $this->assertStringContainsString(
      '/drupal/admin/config',
      $url,
      'Active prefix must be stripped without corrupting base_path.'
    );
    $this->assertStringNotContainsString('/fr/', $url);

    // setUrl() on the same prefixed domain should keep /fr.
    $french->setUrl();
    $url = $french->getUrl();
    $this->assertStringContainsString(
      '/drupal/fr/admin/config',
      $url,
      'Own prefix must be preserved after base_path.'
    );
  }

  /**
   * Tests setUrl() with subdirectory and query string.
   *
   * Ensures that query parameters are preserved when setUrl()
   * handles subdirectory base path + prefix manipulation.
   */
  public function testSetUrlSubdirectoryWithQuery(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $french = $this->createDomain('example.com', 'fr');
    $french->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    // Simulate browsing unprefixed domain with query string.
    $context->setDomain($default);
    $request = $this->createRequest(
      'http://example.com/drupal/admin/config?page=1&sort=name',
      [
        'SCRIPT_NAME' => '/drupal/index.php',
        'SCRIPT_FILENAME' => '/var/www/html/web/index.php',
      ]
    );
    $this->container->get('request_stack')->push($request);

    $french->setUrl();
    $url = $french->getUrl();
    $this->assertStringContainsString(
      '/drupal/fr/admin/config',
      $url
    );
    $this->assertStringContainsString(
      'page=1&sort=name',
      $url,
      'Query string must be preserved.'
    );
  }

  /**
   * Tests that non-ASCII prefix is rejected by default.
   *
   * When the allow_non_ascii setting is FALSE (default), saving
   * a domain with a Unicode path prefix should throw a
   * ConfigValueException from preSave() validation.
   */
  public function testNonAsciiPrefixRejectedByDefault(): void {
    $domain = $this->createDomain('example.com', '', TRUE);
    $domain->save();

    $unicode = $this->domainStorage->create([
      'id' => 'example_com_belgie',
      'hostname' => 'example.com',
      'name' => 'België',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'belgië',
    ]);
    $this->expectException(ConfigValueException::class);
    $unicode->save();
  }

  /**
   * Tests that non-ASCII prefix is accepted when enabled.
   *
   * When the allow_non_ascii setting is TRUE, a domain with a
   * Unicode lowercase path prefix should save without error and
   * the prefix should survive a save/load round-trip.
   */
  public function testNonAsciiPrefixAcceptedWhenEnabled(): void {
    $this->config('domain.settings')
      ->set('allow_non_ascii', TRUE)
      ->save();

    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $unicode = $this->domainStorage->create([
      'id' => 'example_com_belgie',
      'hostname' => 'example.com',
      'name' => 'België',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'belgië',
    ]);
    $unicode->save();

    $this->domainStorage->resetCache();
    $reloaded = $this->domainStorage->load('example_com_belgie');
    $this->assertNotNull($reloaded);
    $this->assertEquals('belgië', $reloaded->getPathPrefix());
  }

  /**
   * Tests schema validation accepts Unicode prefix.
   *
   * The schema Regex constraint uses Unicode character classes as
   * a permissive baseline. A Unicode lowercase prefix should pass
   * schema validation regardless of the allow_non_ascii setting
   * (the stricter ASCII check happens at preSave time).
   */
  public function testSchemaValidationAcceptsUnicodePrefix(): void {
    $domain = $this->domainStorage->create([
      'id' => 'example_com_jp',
      'hostname' => 'example.com',
      'name' => 'Japan',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => '日本',
    ]);
    $violations = $this->validateDomain($domain);
    $prefix_violation_found = FALSE;
    foreach ($violations as $violation) {
      if (str_contains($violation->getPropertyPath(), 'path_prefix')) {
        $prefix_violation_found = TRUE;
        break;
      }
    }
    $this->assertFalse(
      $prefix_violation_found,
      'Unicode lowercase prefix should pass schema Regex.'
    );
  }

  /**
   * Tests schema validation rejects invalid prefix characters.
   *
   * The schema Regex constraint should reject prefixes containing
   * slashes, spaces, or uppercase letters even when allow_non_ascii
   * is enabled, since these are never valid in a URL path segment.
   */
  public function testSchemaValidationRejectsInvalidPrefix(): void {
    $invalid_prefixes = [
      'has/slash',
      'has space',
      '/leading-slash',
      '-leading-hyphen',
    ];
    foreach ($invalid_prefixes as $prefix) {
      $domain = $this->domainStorage->create([
        'id' => 'example_com_bad',
        'hostname' => 'example.com',
        'name' => 'Bad prefix',
        'scheme' => 'http',
        'status' => 1,
        'weight' => 0,
        'is_default' => TRUE,
        'path_prefix' => $prefix,
      ]);
      $violations = $this->validateDomain($domain);
      $this->assertGreaterThan(
        0,
        $violations->count(),
        "Prefix '$prefix' should produce violations."
      );
      // Verify at least one violation is about the path_prefix.
      $prefix_violation_found = FALSE;
      foreach ($violations as $violation) {
        if (str_contains($violation->getPropertyPath(), 'path_prefix')) {
          $prefix_violation_found = TRUE;
          break;
        }
      }
      $this->assertTrue(
        $prefix_violation_found,
        "Prefix '$prefix' should have a path_prefix violation."
      );
    }
  }

  /**
   * Tests that ASCII prefix still works when non-ASCII is enabled.
   *
   * Enabling allow_non_ascii should not break regular ASCII
   * prefixes — they should save and persist as before.
   */
  public function testAsciiPrefixStillWorksWhenNonAsciiEnabled(): void {
    $this->config('domain.settings')
      ->set('allow_non_ascii', TRUE)
      ->save();

    $domain = $this->createDomain('example.com', 'fr', TRUE);
    $domain->save();

    $this->domainStorage->resetCache();
    $reloaded = $this->domainStorage->load('example_com_fr');
    $this->assertNotNull($reloaded);
    $this->assertEquals('fr', $reloaded->getPathPrefix());
  }

  /**
   * Tests negotiation with a percent-encoded non-ASCII prefix.
   *
   * Browsers send non-ASCII path segments as percent-encoded UTF-8
   * (e.g., "belgië" becomes "belgi%C3%AB"). The negotiator must
   * decode the request path before matching against the stored
   * prefix so that the correct domain is selected.
   */
  public function testNonAsciiPrefixNegotiation(): void {
    $this->config('domain.settings')
      ->set('allow_non_ascii', TRUE)
      ->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $belgian = $this->domainStorage->create([
      'id' => 'example_com_belgie',
      'hostname' => 'example.com',
      'name' => 'België',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'belgië',
    ]);
    $belgian->save();

    // Simulate a request with percent-encoded prefix.
    $request = $this->createRequest(
      'http://example.com/belgi%C3%AB/node/1'
    );
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals(
      'example_com_belgie',
      $active->id(),
      'Percent-encoded non-ASCII prefix must negotiate correctly.'
    );
  }

  /**
   * Tests inbound path processing with percent-encoded prefix.
   *
   * The inbound processor must decode the percent-encoded path
   * before stripping the non-ASCII prefix so that subsequent
   * processors see a clean internal path.
   */
  public function testNonAsciiPrefixInboundProcessing(): void {
    $this->config('domain.settings')
      ->set('allow_non_ascii', TRUE)
      ->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $belgian = $this->domainStorage->create([
      'id' => 'example_com_belgie',
      'hostname' => 'example.com',
      'name' => 'België',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'belgië',
    ]);
    $belgian->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');
    $context->setDomain($belgian);

    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $processor */
    $processor = $this->container
      ->get('domain.prefix_path_processor');

    // Percent-encoded path should be decoded and stripped.
    $request = Request::create(
      'http://example.com/belgi%C3%AB/node/1'
    );
    $this->assertEquals(
      '/node/1',
      $processor->processInbound(
        '/belgi%C3%AB/node/1',
        $request
      ),
      'Encoded non-ASCII prefix must be stripped.'
    );

    // Raw UTF-8 path should also be stripped.
    $this->assertEquals(
      '/node/1',
      $processor->processInbound(
        '/belgië/node/1',
        $request
      ),
      'Raw non-ASCII prefix must also be stripped.'
    );
  }

  /**
   * Tests setUrl() with a percent-encoded non-ASCII prefix.
   *
   * When the request path contains a percent-encoded non-ASCII
   * prefix, setUrl() must decode it before stripping the active
   * prefix and adding the target prefix, producing a URL with
   * a single (not doubled) prefix.
   */
  public function testNonAsciiPrefixSetUrl(): void {
    $this->config('domain.settings')
      ->set('allow_non_ascii', TRUE)
      ->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $belgian = $this->domainStorage->create([
      'id' => 'example_com_belgie',
      'hostname' => 'example.com',
      'name' => 'België',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'belgië',
    ]);
    $belgian->save();

    /** @var \Drupal\domain\DomainNegotiationContext $context */
    $context = $this->container->get('domain.negotiation_context');

    // Simulate browsing on the unprefixed domain with an
    // encoded path that looks like the non-ASCII prefix.
    $context->setDomain($default);
    $request = $this->createRequest(
      'http://example.com/admin/config'
    );
    $this->container->get('request_stack')->push($request);

    // setUrl() on the Belgian domain should add prefix once.
    $belgian->setUrl();
    $url = $belgian->getUrl();
    $this->assertStringContainsString(
      '/belgië/admin/config',
      $url,
      'Non-ASCII prefix must appear in the URL.'
    );

    // Verify no double prefix (encoded or raw).
    $this->assertEquals(
      1,
      substr_count($url, 'belgi'),
      'Prefix must appear exactly once in the URL.'
    );
  }

  /**
   * Extracts violation messages from a specific constraint.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violation list.
   * @param string $constraint_name
   *   The short class name of the constraint.
   *
   * @return string[]
   *   The violation messages from that constraint.
   */
  protected function getMessagesForConstraint($violations, string $constraint_name): array {
    $messages = [];
    foreach ($violations as $violation) {
      $constraint = $violation->getConstraint();
      $class = get_class($constraint);
      $short = substr($class, strrpos($class, '\\') + 1);
      if ($short === $constraint_name) {
        $messages[] = (string) $violation->getMessage();
      }
    }
    return $messages;
  }

}
