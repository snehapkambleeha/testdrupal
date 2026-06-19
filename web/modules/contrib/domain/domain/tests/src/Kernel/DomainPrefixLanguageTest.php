<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\domain\Entity\Domain;
use Drupal\domain\Plugin\LanguageNegotiation\LanguageNegotiationDomainUrl;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests domain path prefix interaction with language URL prefixes.
 *
 * Verifies that domain prefix (inbound priority 350, outbound 50)
 * and language prefix (inbound 300, outbound 100) path processors
 * compose correctly. A URL like /benl/fr/node/1 should be
 * decomposed as: domain prefix "benl", then language prefix "fr",
 * then internal path /node/1. Outbound generation should reverse
 * this: language adds "fr/", then domain prefix prepends "benl/".
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainPrefixLanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
    'domain',
    'user',
  ];

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
    $this->installEntitySchema('configurable_language');
    $this->installConfig(['system', 'language']);

    // Create French language with URL prefix.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Configure language negotiation to use URL path prefixes.
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fr' => 'fr'])
      ->save();

    // Enable the path prefix feature so components are registered.
    $this->config('domain.settings')
      ->set('path_prefix', TRUE)
      ->save();

    // Rebuild the container so language processors register.
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $this->domainStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain');
  }

  /**
   * Creates a domain entity with the given hostname and prefix.
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
   * Creates a request with a mock session.
   *
   * @param string $uri
   *   The request URI.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A request object with a mock session attached.
   */
  protected function createRequest(string $uri): Request {
    $request = Request::create($uri);
    $request->setSession(new Session(new MockArraySessionStorage()));
    return $request;
  }

  /**
   * Tests inbound processing strips domain prefix before language.
   *
   * Simulates a request to /benl/fr/node/1 and verifies that:
   * 1. The domain negotiator selects the "benl" domain.
   * 2. The domain prefix processor (priority 350) strips "benl",
   *    leaving /fr/node/1.
   * 3. The language processor (priority 300) then strips "fr",
   *    leaving /node/1.
   *
   * This confirms the correct inbound processing order.
   */
  public function testInboundDomainPrefixBeforeLanguage(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $benl = $this->createDomain('example.com', 'benl');
    $benl->save();

    // Simulate request to /benl/fr/node/1.
    $request = $this->createRequest('http://example.com/benl/fr/node/1');
    $this->container->get('request_stack')->push($request);

    // Negotiate the domain — should select the "benl" domain.
    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active);
    $this->assertEquals('example_com_benl', $active->id());
    $this->assertEquals('benl', $active->getPathPrefix());

    // Domain prefix processor strips "benl" prefix.
    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $domain_processor */
    $domain_processor = $this->container->get('domain.prefix_path_processor');
    $after_domain = $domain_processor->processInbound('/benl/fr/node/1', $request);
    $this->assertEquals('/fr/node/1', $after_domain);

    // Language processor strips "fr" prefix.
    /** @var \Drupal\language\HttpKernel\PathProcessorLanguage $language_processor */
    $language_processor = $this->container->get('path_processor_language');
    $after_language = $language_processor->processInbound($after_domain, $request);
    $this->assertEquals('/node/1', $after_language);
  }

  /**
   * Tests outbound processing adds language prefix then domain prefix.
   *
   * Verifies that for a domain with prefix "benl" and language "fr":
   * 1. The language processor (priority 100) sets
   *    $options['prefix'] = 'fr/'.
   * 2. The domain prefix processor (priority 50) prepends "benl/"
   *    yielding $options['prefix'] = 'benl/fr/'.
   *
   * This confirms the correct outbound processing order.
   */
  public function testOutboundLanguageThenDomainPrefix(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $benl = $this->createDomain('example.com', 'benl');
    $benl->save();

    $fr = $this->container->get('language_manager')->getLanguage('fr');
    $this->assertNotNull($fr, 'French language should be available.');

    // Simulate language outbound: sets prefix to 'fr/'.
    /** @var \Drupal\language\HttpKernel\PathProcessorLanguage $language_processor */
    $language_processor = $this->container->get('path_processor_language');
    $options = ['language' => $fr];
    $language_processor->processOutbound('/node/1', $options);
    $this->assertEquals('fr/', $options['prefix']);

    // Domain prefix outbound: prepends 'benl/' to existing prefix.
    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $domain_processor */
    $domain_processor = $this->container->get('domain.prefix_path_processor');
    $options['domain'] = $benl;
    $domain_processor->processOutbound('/node/1', $options);
    $this->assertEquals('benl/fr/', $options['prefix']);
  }

  /**
   * Tests that unprefixed domain does not alter language prefix.
   *
   * When the active domain has no path prefix, the domain prefix
   * processor should leave the language prefix untouched. A URL
   * for the French language on the default (no-prefix) domain
   * should have prefix "fr/" only.
   */
  public function testNoPrefixDomainPreservesLanguage(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $benl = $this->createDomain('example.com', 'benl');
    $benl->save();

    $fr = $this->container->get('language_manager')->getLanguage('fr');

    // Language processor sets 'fr/'.
    /** @var \Drupal\language\HttpKernel\PathProcessorLanguage $language_processor */
    $language_processor = $this->container->get('path_processor_language');
    $options = ['language' => $fr];
    $language_processor->processOutbound('/node/1', $options);
    $this->assertEquals('fr/', $options['prefix']);

    // Domain prefix processor with no-prefix domain — no change.
    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $domain_processor */
    $domain_processor = $this->container->get('domain.prefix_path_processor');
    $options['domain'] = $default;
    $domain_processor->processOutbound('/node/1', $options);
    $this->assertEquals('fr/', $options['prefix']);
  }

  /**
   * Tests inbound with domain prefix but no language prefix.
   *
   * A request to /benl/node/1 (domain prefix, no language prefix)
   * should strip only the domain prefix, leaving /node/1. The
   * language processor should not strip anything since "node" is
   * not a language prefix.
   */
  public function testDomainPrefixWithoutLanguagePrefix(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $benl = $this->createDomain('example.com', 'benl');
    $benl->save();

    $request = $this->createRequest('http://example.com/benl/node/1');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertEquals('example_com_benl', $active->id());

    // Domain prefix strips "benl".
    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $domain_processor */
    $domain_processor = $this->container->get('domain.prefix_path_processor');
    $after_domain = $domain_processor->processInbound('/benl/node/1', $request);
    $this->assertEquals('/node/1', $after_domain);

    // Language processor does nothing — "node" is not a prefix.
    /** @var \Drupal\language\HttpKernel\PathProcessorLanguage $language_processor */
    $language_processor = $this->container->get('path_processor_language');
    $after_language = $language_processor->processInbound($after_domain, $request);
    $this->assertEquals('/node/1', $after_language);
  }

  /**
   * Tests that language prefix alone works on unprefixed domain.
   *
   * A request to /fr/node/1 on the default domain (no domain
   * prefix) should: domain prefix processor does nothing, then
   * language processor strips "fr", yielding /node/1.
   */
  public function testLanguagePrefixOnUnprefixedDomain(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $request = $this->createRequest('http://example.com/fr/node/1');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    $active = $negotiator->getActiveDomain();
    $this->assertEquals('example_com', $active->id());
    $this->assertEquals('', $active->getPathPrefix());

    // Domain prefix processor does nothing (no prefix on domain).
    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $domain_processor */
    $domain_processor = $this->container->get('domain.prefix_path_processor');
    $after_domain = $domain_processor->processInbound('/fr/node/1', $request);
    $this->assertEquals('/fr/node/1', $after_domain);

    // Language processor strips "fr".
    /** @var \Drupal\language\HttpKernel\PathProcessorLanguage $language_processor */
    $language_processor = $this->container->get('path_processor_language');
    $after_language = $language_processor->processInbound($after_domain, $request);
    $this->assertEquals('/node/1', $after_language);
  }

  /**
   * Tests language negotiation on a prefixed domain.
   *
   * The domain module swaps core's LanguageNegotiationUrl plugin
   * with LanguageNegotiationDomainUrl via
   * hook_language_negotiation_info_alter(). The custom plugin
   * strips the domain path prefix from $request->getPathInfo()
   * internally before checking for the language prefix. This
   * avoids modifying the request object while still detecting
   * the correct language. For /benl/fr/node/1, the raw pathInfo
   * remains unchanged but getLangcode() detects French.
   */
  public function testLanguageNegotiationOnPrefixedDomain(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $benl = $this->createDomain('example.com', 'benl');
    $benl->save();

    $request = $this->createRequest('http://example.com/benl/fr/node/1');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    // pathInfo is NOT modified — the custom plugin handles
    // domain prefix stripping internally.
    $this->assertEquals(
      '/benl/fr/node/1',
      $request->getPathInfo(),
      'pathInfo remains unchanged (no request mutation).',
    );

    // The custom LanguageNegotiationDomainUrl plugin detects
    // French despite the domain prefix in pathInfo.
    /** @var \Drupal\language\LanguageNegotiatorInterface $language_negotiator */
    $language_negotiator = $this->container->get('language_negotiator');
    $url_method = $language_negotiator->getNegotiationMethodInstance(
      LanguageNegotiationUrl::METHOD_ID,
    );
    $this->assertInstanceOf(
      LanguageNegotiationDomainUrl::class,
      $url_method,
      'Plugin class is swapped to LanguageNegotiationDomainUrl.',
    );
    $langcode = $url_method->getLangcode($request);
    $this->assertEquals(
      'fr',
      $langcode,
      'Language negotiation detects French on prefixed domain.',
    );
  }

  /**
   * Tests language negotiation on an unprefixed domain.
   *
   * When the active domain has no path prefix, the custom
   * LanguageNegotiationDomainUrl plugin has no prefix to strip
   * and behaves identically to core's LanguageNegotiationUrl.
   * For /fr/node/1 on the default domain, getLangcode() detects
   * French directly from the first path segment.
   */
  public function testLanguageNegotiationOnUnprefixedDomain(): void {
    $default = $this->createDomain('example.com', '', TRUE);
    $default->save();

    $request = $this->createRequest('http://example.com/fr/node/1');
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain('example.com', TRUE);

    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $domain_processor */
    $domain_processor = $this->container->get('domain.prefix_path_processor');
    $domain_processor->processInbound('/fr/node/1', $request);

    // pathInfo should be unchanged.
    $this->assertEquals(
      '/fr/node/1',
      $request->getPathInfo(),
      'pathInfo unchanged for unprefixed domain.',
    );

    // Language negotiation still detects French.
    /** @var \Drupal\language\LanguageNegotiatorInterface $language_negotiator */
    $language_negotiator = $this->container->get('language_negotiator');
    $url_method = $language_negotiator->getNegotiationMethodInstance(
      LanguageNegotiationUrl::METHOD_ID,
    );
    $this->assertEquals('fr', $url_method->getLangcode($request));
  }

}
