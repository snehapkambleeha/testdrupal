<?php

namespace Drupal\Tests\domain_source\Kernel;

use Drupal\domain_source\DomainSourceElementManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests domain source path processing with translated entities.
 *
 * Verifies that DomainSourcePathProcessor correctly determines the
 * source domain for translated entities, even when
 * $options['language'] has not yet been populated by the language
 * path processor (which runs at a lower priority).
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourceTranslationTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'domain',
    'domain_source',
    'field',
    'language',
    'node',
    'system',
    'user',
  ];

  /**
   * The path processor under test.
   *
   * @var \Drupal\domain_source\HttpKernel\DomainSourcePathProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('domain');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig([
      'domain',
      'domain_source',
      'language',
      'system',
    ]);
    $this->installSchema('node', ['node_access']);

    // Create two domains.
    $this->domainCreateTestDomains(2);

    // Create a node type.
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    // Create the domain_source field instance.
    $this->container->get('domain_source.helper')
      ->confirmFields('node', 'page');

    // Add French language and make it the site default so
    // the language manager's getCurrentLanguage() returns
    // French without requiring a real HTTP request.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('system.site')
      ->set('default_langcode', 'fr')
      ->save();

    // Enable translation for nodes.
    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    $this->processor = $this->container
      ->get('domain_source.path_processor');
  }

  /**
   * Creates a Route object for the node canonical route.
   *
   * @return \Symfony\Component\Routing\Route
   *   A Route object with node entity parameter.
   */
  protected function createNodeRoute(): Route {
    $route = new Route('/node/{node}');
    $route->setOption('parameters', [
      'node' => ['type' => 'entity:node'],
    ]);
    return $route;
  }

  /**
   * Tests that the correct translation is used without $options['language'].
   *
   * When DomainSourcePathProcessor runs at priority 310, the
   * language path processor (priority 100) has not yet populated
   * $options['language']. When the entity is loaded by ID from
   * route parameters (not passed in $options), the processor
   * gets the default translation. Without $options['language'],
   * it should fall back to the language manager's current content
   * language to determine the correct translation.
   */
  public function testTranslationSourceWithoutLanguageOption(): void {
    $domains = $this->getDomains();
    $domain1 = $domains['example_com'];
    $domain2 = $domains['one_example_com'];

    $field = DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;

    // Create a node explicitly in English (not the site default).
    // Default (EN) translation: source = Domain 1.
    // French translation: source = Domain 2.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'page',
        'title' => 'Test Node',
        'langcode' => 'en',
        $field => $domain1->id(),
      ]);
    $node->save();

    $translation = $node->addTranslation('fr');
    $translation->set('title', 'Test Node FR');
    $translation->set($field, $domain2->id());
    $translation->save();

    // Simulate what the route normalizer does: generate a URL
    // via Url::fromRoute('<current>') without $options['entity']
    // or $options['language']. The processor loads the entity by
    // ID, which returns the default (EN) translation. The site
    // default language is French, so the language manager's
    // getCurrentLanguage(TYPE_CONTENT) returns French.
    $options = [
      'active_domain' => $domain2,
      'route_name' => 'entity.node.canonical',
      'route' => $this->createNodeRoute(),
      'route_parameters' => ['node' => (string) $node->id()],
    ];

    $path = '/node/' . $node->id();
    $this->processor->processOutbound($path, $options);

    // The French translation's source domain is Domain 2, which
    // matches the active domain. No cross-domain rewrite should
    // happen. Without the fix, the processor uses the default
    // (EN) translation whose source is Domain 1, incorrectly
    // triggering a cross-domain rewrite.
    $this->assertArrayNotHasKey(
      'domain',
      $options,
      'No cross-domain rewrite expected when the translation source matches the active domain.'
    );
  }

  /**
   * Tests cross-domain rewrite without $options['language'].
   *
   * When the active domain does NOT match the translation's source
   * domain, the processor should trigger a cross-domain rewrite.
   */
  public function testCrossDomainRewriteWithoutLanguageOption(): void {
    $domains = $this->getDomains();
    $domain1 = $domains['example_com'];
    $domain2 = $domains['one_example_com'];

    $field = DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;

    // EN source = Domain 1, FR source = Domain 2.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'page',
        'title' => 'Test Node',
        'langcode' => 'en',
        $field => $domain1->id(),
      ]);
    $node->save();

    $translation = $node->addTranslation('fr');
    $translation->set('title', 'Test Node FR');
    $translation->set($field, $domain2->id());
    $translation->save();

    // Active domain is Domain 1 but French translation's source
    // is Domain 2. The processor should detect the mismatch and
    // set $options['domain'] to Domain 2.
    $options = [
      'active_domain' => $domain1,
      'route_name' => 'entity.node.canonical',
      'route' => $this->createNodeRoute(),
      'route_parameters' => ['node' => (string) $node->id()],
    ];

    $path = '/node/' . $node->id();
    $this->processor->processOutbound($path, $options);

    $this->assertArrayHasKey(
      'domain',
      $options,
      'Cross-domain rewrite expected when translation source differs from active domain.'
    );
    $this->assertSame(
      $domain2->id(),
      $options['domain']->id(),
      'Rewrite target should be the French translation source domain.'
    );
  }

  /**
   * Tests that passing $options['language'] produces correct results.
   *
   * This is the baseline: when $options['language'] IS available,
   * the processor correctly gets the translation and finds the
   * right source domain.
   */
  public function testTranslationSourceWithLanguageOption(): void {
    $domains = $this->getDomains();
    $domain1 = $domains['example_com'];
    $domain2 = $domains['one_example_com'];

    $field = DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'page',
        'title' => 'Test Node',
        'langcode' => 'en',
        $field => $domain1->id(),
      ]);
    $node->save();

    $translation = $node->addTranslation('fr');
    $translation->set('title', 'Test Node FR');
    $translation->set($field, $domain2->id());
    $translation->save();

    $language_fr = $this->container->get('language_manager')
      ->getLanguage('fr');

    // With $options['language'] set, the processor picks the
    // French translation and finds Domain 2 as source.
    $options = [
      'active_domain' => $domain2,
      'language' => $language_fr,
      'route_name' => 'entity.node.canonical',
      'route' => $this->createNodeRoute(),
      'route_parameters' => ['node' => (string) $node->id()],
    ];

    $path = '/node/' . $node->id();
    $this->processor->processOutbound($path, $options);

    // Domain 2 is both source and active: no cross-domain rewrite.
    $this->assertArrayNotHasKey(
      'domain',
      $options,
      'No cross-domain rewrite when language option correctly resolves to the active domain source.'
    );
  }

  /**
   * Tests cross-domain rewrite with $options['language'].
   *
   * When the active domain does NOT match the translation's source
   * domain and $options['language'] is set, the processor should
   * trigger a cross-domain rewrite.
   */
  public function testCrossDomainRewriteWithLanguageOption(): void {
    $domains = $this->getDomains();
    $domain1 = $domains['example_com'];
    $domain2 = $domains['one_example_com'];

    $field = DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'page',
        'title' => 'Test Node',
        'langcode' => 'en',
        $field => $domain1->id(),
      ]);
    $node->save();

    $translation = $node->addTranslation('fr');
    $translation->set('title', 'Test Node FR');
    $translation->set($field, $domain2->id());
    $translation->save();

    $language_fr = $this->container->get('language_manager')
      ->getLanguage('fr');

    // Active domain is Domain 1, language is French, FR source
    // is Domain 2. Cross-domain rewrite should happen.
    $options = [
      'active_domain' => $domain1,
      'language' => $language_fr,
      'route_name' => 'entity.node.canonical',
      'route' => $this->createNodeRoute(),
      'route_parameters' => ['node' => (string) $node->id()],
    ];

    $path = '/node/' . $node->id();
    $this->processor->processOutbound($path, $options);

    $this->assertArrayHasKey(
      'domain',
      $options,
      'Cross-domain rewrite expected when translation source differs from active domain.'
    );
    $this->assertSame(
      $domain2->id(),
      $options['domain']->id(),
      'Rewrite target should be the French translation source domain.'
    );
  }

}
