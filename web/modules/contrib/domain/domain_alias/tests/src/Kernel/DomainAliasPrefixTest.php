<?php

namespace Drupal\Tests\domain_alias\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests alias resolution combined with path prefix negotiation.
 *
 * When a hostname is resolved via a domain alias and the target
 * domain's hostname is shared by multiple domains with different
 * path prefixes, the negotiator must re-run prefix negotiation
 * after the alias resolves.
 *
 * @group domain_alias
 */
#[Group('domain_alias')]
#[RunTestsInSeparateProcesses]
class DomainAliasPrefixTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_alias'];

  /**
   * The domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * The domain alias storage handler.
   *
   * @var \Drupal\domain_alias\DomainAliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('domain_alias');
    $this->installConfig(['domain_alias']);

    // Enable the path prefix feature so components are registered.
    $this->config('domain.settings')
      ->set('path_prefix', TRUE)
      ->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $this->domainStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain');
    $this->aliasStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain_alias');
  }

  /**
   * Creates a request with a mock session.
   *
   * @param string $uri
   *   The request URI.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A request with a mock session attached.
   */
  protected function createRequest(string $uri): Request {
    $request = Request::create($uri);
    $request->setSession(
      new Session(new MockArraySessionStorage()),
    );
    return $request;
  }

  /**
   * Tests alias + prefix: prefixed path on alias hostname.
   *
   * Given:
   *   - Domain A: example.com (no prefix, default)
   *   - Domain B: example.com (prefix "myprefix")
   *   - Alias: alias.example.com → Domain A.
   *
   * A request to alias.example.com/myprefix/page should:
   *   1. Resolve alias.example.com → example.com (Domain A)
   *   2. Re-run prefix negotiation → select Domain B
   *   3. Active domain has prefix "myprefix".
   */
  public function testAliasPrefixNegotiation(): void {
    // Create two domains sharing hostname example.com.
    $domain_default = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => '',
    ]);
    $domain_default->save();

    $domain_prefixed = $this->domainStorage->create([
      'id' => 'example_com_myprefix',
      'hostname' => 'example.com',
      'name' => 'Prefixed',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'myprefix',
    ]);
    $domain_prefixed->save();

    // Create alias: alias.example.com → Domain A.
    $alias = $this->aliasStorage->create([
      'id' => 'alias_example_com',
      'domain_id' => 'example_com',
      'pattern' => 'alias.example.com',
      'redirect' => 0,
      'environment' => 'default',
    ]);
    $alias->save();

    // Request to alias.example.com/myprefix/page.
    $request = $this->createRequest('http://alias.example.com/myprefix/page');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('alias.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active, 'A domain was negotiated.');
    $this->assertEquals(
      'example_com_myprefix',
      $active->id(),
      'Prefixed domain selected after alias resolution.',
    );
    $this->assertEquals(
      'myprefix',
      $active->getPathPrefix(),
      'Active domain has the expected path prefix.',
    );
  }

  /**
   * Tests alias + no prefix: unprefixed path on alias hostname.
   *
   * Given the same setup, a request to alias.example.com/page
   * (no prefix) should resolve to Domain A (the default).
   */
  public function testAliasNoPrefixNegotiation(): void {
    $domain_default = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => '',
    ]);
    $domain_default->save();

    $domain_prefixed = $this->domainStorage->create([
      'id' => 'example_com_myprefix',
      'hostname' => 'example.com',
      'name' => 'Prefixed',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'myprefix',
    ]);
    $domain_prefixed->save();

    $alias = $this->aliasStorage->create([
      'id' => 'alias_example_com',
      'domain_id' => 'example_com',
      'pattern' => 'alias.example.com',
      'redirect' => 0,
      'environment' => 'default',
    ]);
    $alias->save();

    // Request to alias.example.com/page (no prefix).
    $request = $this->createRequest('http://alias.example.com/page');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('alias.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active, 'A domain was negotiated.');
    $this->assertEquals(
      'example_com',
      $active->id(),
      'Default domain selected when no prefix matches.',
    );
    $this->assertEquals(
      '',
      $active->getPathPrefix(),
      'Active domain has no path prefix.',
    );
  }

  /**
   * Tests wildcard alias + prefix negotiation.
   *
   * Uses a wildcard alias pattern (*.example.com) and verifies
   * that prefix negotiation works after the wildcard resolves.
   */
  public function testWildcardAliasPrefixNegotiation(): void {
    $domain_default = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => '',
    ]);
    $domain_default->save();

    $domain_prefixed = $this->domainStorage->create([
      'id' => 'example_com_fr',
      'hostname' => 'example.com',
      'name' => 'French',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'fr',
    ]);
    $domain_prefixed->save();

    // Wildcard alias on the default domain.
    $alias = $this->aliasStorage->create([
      'id' => 'wildcard_example_com',
      'domain_id' => 'example_com',
      'pattern' => '*.example.com',
      'redirect' => 0,
      'environment' => 'default',
    ]);
    $alias->save();

    // Request to anything.example.com/fr/page.
    $request = $this->createRequest('http://anything.example.com/fr/page');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('anything.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active, 'A domain was negotiated.');
    $this->assertEquals(
      'example_com_fr',
      $active->id(),
      'French domain selected via wildcard alias + prefix.',
    );
    $this->assertEquals(
      'fr',
      $active->getPathPrefix(),
      'Active domain has the expected path prefix.',
    );
  }

  /**
   * Tests alias fallback when no prefix matches any candidate.
   *
   * When all domains sharing a hostname have non-empty path prefixes
   * and the request path does not match any of them,
   * negotiateByPathPrefix() returns NULL. The alias resolution must
   * fall back to the alias target domain rather than failing with
   * an error.
   *
   * Given:
   *   - Domain A: example.com (prefix "en")
   *   - Domain B: example.com (prefix "fr")
   *   - Alias: alias.example.com → Domain A
   *
   * A request to alias.example.com/de/page should resolve to
   * Domain A (the alias target) since no prefix matches.
   */
  public function testAliasFallbackWhenNoPrefixMatches(): void {
    $domain_en = $this->domainStorage->create([
      'id' => 'example_com_en',
      'hostname' => 'example.com',
      'name' => 'English',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => 'en',
    ]);
    $domain_en->save();

    $domain_fr = $this->domainStorage->create([
      'id' => 'example_com_fr',
      'hostname' => 'example.com',
      'name' => 'French',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'fr',
    ]);
    $domain_fr->save();

    // Alias points to the English domain.
    $alias = $this->aliasStorage->create([
      'id' => 'alias_example_com',
      'domain_id' => 'example_com_en',
      'pattern' => 'alias.example.com',
      'redirect' => 0,
      'environment' => 'default',
    ]);
    $alias->save();

    // Request path /de/page matches neither "en" nor "fr" prefix.
    $request = $this->createRequest('http://alias.example.com/de/page');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('alias.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull(
      $active,
      'A domain was negotiated despite no prefix match.',
    );
    $this->assertEquals(
      'example_com_en',
      $active->id(),
      'Falls back to alias target domain when no prefix matches.',
    );
  }

  /**
   * Tests hostname rewriting for prefixed domains in non-default env.
   *
   * When a wildcard alias in a non-default environment (e.g. "local")
   * matches the request, the domainLoad() hook rewrites each domain's
   * hostname via alias resolution. Domains that share the same
   * canonical hostname but differ by path prefix should also have
   * their hostnames rewritten, since they share the active domain's
   * canonical hostname.
   */
  public function testEnvironmentRewritePrefixedDomains(): void {
    $domain_default = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => '',
    ]);
    $domain_default->save();

    $domain_prefixed = $this->domainStorage->create([
      'id' => 'example_com_fr',
      'hostname' => 'example.com',
      'name' => 'French',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => 'fr',
    ]);
    $domain_prefixed->save();

    // Wildcard alias in the "local" environment.
    $alias = $this->aliasStorage->create([
      'id' => 'wildcard_example_com',
      'domain_id' => 'example_com',
      'pattern' => '*.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ]);
    $alias->save();

    // Request to staging.example.com/page (unprefixed path).
    $request = $this->createRequest(
      'http://staging.example.com/page',
    );
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('staging.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals('example_com', $active->id());

    // Reload both domains and verify hostname rewriting.
    // domainRequestAlter called resetCache() on non-default env,
    // so these loads are fresh and go through domainLoad().
    $reloaded_default = $this->domainStorage->load('example_com');
    $this->assertEquals(
      'staging.example.com',
      $reloaded_default->getHostname(),
      'Default domain hostname rewritten to alias hostname.',
    );

    $reloaded_prefixed = $this->domainStorage->load('example_com_fr');
    $this->assertEquals(
      'staging.example.com',
      $reloaded_prefixed->getHostname(),
      'Prefixed domain hostname rewritten to alias hostname.',
    );
  }

  /**
   * Tests cross-host alias resolution via sibling fallback.
   *
   * When loading a domain from a request on a DIFFERENT hostname,
   * the domain's canonical doesn't match the active domain's
   * canonical. If the domain has no alias of its own,
   * the domainLoad() hook falls back to aliases from sibling domains
   * that share the same canonical hostname.
   *
   * Given:
   *   - Domain A: one.example.com (default)
   *   - Domain B: two.example.com
   *   - Domain C: two.example.com (prefix "fr", no alias)
   *   - Alias on A: local-one.example.com (local env)
   *   - Alias on B: local-two.example.com (local env)
   *
   * A request from local-one.example.com loading Domain C should
   * resolve its hostname to local-two.example.com via Domain B's
   * alias (sibling fallback).
   */
  public function testCrossHostAliasSiblingFallback(): void {
    $domain_one = $this->domainStorage->create([
      'id' => 'one_example_com',
      'hostname' => 'one.example.com',
      'name' => 'Site One',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
      'path_prefix' => '',
    ]);
    $domain_one->save();

    $domain_two = $this->domainStorage->create([
      'id' => 'two_example_com',
      'hostname' => 'two.example.com',
      'name' => 'Site Two',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
      'path_prefix' => '',
    ]);
    $domain_two->save();

    // Prefixed domain on the same hostname as Domain B — no alias.
    $domain_two_fr = $this->domainStorage->create([
      'id' => 'two_example_com_fr',
      'hostname' => 'two.example.com',
      'name' => 'Site Two French',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 2,
      'is_default' => FALSE,
      'path_prefix' => 'fr',
    ]);
    $domain_two_fr->save();

    // Alias for Domain A in "local" env.
    $this->aliasStorage->create([
      'id' => 'alias_one',
      'domain_id' => 'one_example_com',
      'pattern' => 'local-one.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ])->save();

    // Alias for Domain B in "local" env.
    $this->aliasStorage->create([
      'id' => 'alias_two',
      'domain_id' => 'two_example_com',
      'pattern' => 'local-two.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ])->save();

    // Request from local-one.example.com (Domain A's alias).
    $request = $this->createRequest(
      'http://local-one.example.com/page',
    );
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('local-one.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals('one_example_com', $active->id());

    // Load Domain B — should get aliased hostname via its own
    // alias.
    $loaded_two = $this->domainStorage->load('two_example_com');
    $this->assertEquals(
      'local-two.example.com',
      $loaded_two->getHostname(),
      'Domain B hostname resolved via its own alias.',
    );

    // Load Domain C (prefixed, no alias) — should get aliased
    // hostname via sibling Domain B's alias.
    $loaded_two_fr = $this->domainStorage
      ->load('two_example_com_fr');
    $this->assertEquals(
      'local-two.example.com',
      $loaded_two_fr->getHostname(),
      'Prefixed domain hostname resolved via sibling alias.',
    );
    $this->assertEquals(
      'http://local-two.example.com/fr/',
      $loaded_two_fr->getPath(),
      'Prefixed domain path includes alias hostname and prefix.',
    );
  }

  /**
   * Tests that resetCache() + reload re-applies alias resolution.
   *
   * Verifies that clearing the entity static cache and reloading
   * domains still produces aliased hostnames. After resetCache(),
   * the next load is fresh from config, so the domainLoad() hook fires
   * and re-applies alias resolution.
   */
  public function testResetCacheReappliesAliases(): void {
    $domain_a = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ]);
    $domain_a->save();

    $domain_b = $this->domainStorage->create([
      'id' => 'other_example_com',
      'hostname' => 'other.example.com',
      'name' => 'Other',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
    ]);
    $domain_b->save();

    // "local" alias for both domains.
    $this->aliasStorage->create([
      'id' => 'alias_a',
      'domain_id' => 'example_com',
      'pattern' => 'local.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ])->save();
    $this->aliasStorage->create([
      'id' => 'alias_b',
      'domain_id' => 'other_example_com',
      'pattern' => 'local-other.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ])->save();

    // Negotiate via local alias.
    $request = $this->createRequest(
      'http://local.example.com/',
    );
    $this->container->get('request_stack')->push($request);
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('local.example.com', TRUE);

    // First load — aliases applied.
    $loaded_b = $this->domainStorage->load('other_example_com');
    $this->assertEquals(
      'local-other.example.com',
      $loaded_b->getHostname(),
      'Domain B aliased on first load.',
    );

    // Reset cache and reload — aliases must be re-applied.
    $this->domainStorage->resetCache();
    $reloaded_b = $this->domainStorage->load('other_example_com');
    $this->assertEquals(
      'local-other.example.com',
      $reloaded_b->getHostname(),
      'Domain B aliased after resetCache + reload.',
    );
  }

  /**
   * Tests redirect alias on a non-default environment.
   *
   * When a redirect alias in a non-default environment matches
   * the request, domainRequestAlter() should set the redirect on
   * the active domain and rewrite its hostname to the
   * non-redirect alias for the same environment. Other domains
   * loaded afterward should also get their aliases applied by
   * the domainLoad() hook.
   */
  public function testRedirectAliasNonDefaultEnvironment(): void {
    $domain_a = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Default',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ]);
    $domain_a->save();

    $domain_b = $this->domainStorage->create([
      'id' => 'other_example_com',
      'hostname' => 'other.example.com',
      'name' => 'Other',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
    ]);
    $domain_b->save();

    // Redirect alias: old-local.example.com → example.com
    // with 301 redirect in "local" environment.
    $this->aliasStorage->create([
      'id' => 'alias_redirect',
      'domain_id' => 'example_com',
      'pattern' => 'old-local.example.com',
      'redirect' => 301,
      'environment' => 'local',
    ])->save();

    // Non-redirect alias for the same domain/environment.
    // domainRequestAlter() should rewrite to this pattern.
    $this->aliasStorage->create([
      'id' => 'alias_local',
      'domain_id' => 'example_com',
      'pattern' => 'local.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ])->save();

    // Alias for Domain B in "local" environment.
    $this->aliasStorage->create([
      'id' => 'alias_local_b',
      'domain_id' => 'other_example_com',
      'pattern' => 'local-other.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ])->save();

    // Request via the redirect alias.
    $request = $this->createRequest(
      'http://old-local.example.com/',
    );
    $this->container->get('request_stack')->push($request);

    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('old-local.example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active, 'Active domain negotiated.');
    $this->assertEquals('example_com', $active->id());

    // Redirect should be set on the active domain.
    $this->assertEquals(
      301,
      $active->getRedirect(),
      'Active domain has redirect status from alias.',
    );

    // Active domain hostname should be rewritten to the
    // non-redirect alias pattern for the "local" environment.
    $this->assertEquals(
      'local.example.com',
      $active->getHostname(),
      'Active domain hostname rewritten to non-redirect alias.',
    );

    // Domain B should also get its local alias applied.
    $loaded_b = $this->domainStorage->load('other_example_com');
    $this->assertEquals(
      'local-other.example.com',
      $loaded_b->getHostname(),
      'Domain B aliased via domainLoad().',
    );
  }

}
