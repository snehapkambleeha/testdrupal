<?php

namespace Drupal\Tests\domain_source\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_source\DomainSourceElementManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests behavior for the rewriting links using language negotiation.
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourceLanguageNegotiationTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'domain',
    'domain_source',
    'domain_config',
    'field',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create 2 domains.
    $this->domainCreateTestDomains(2);

    // Add French.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Enable content translation for the current entity type.
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);

    // Set up language negotiation.
    $config = $this->config('language.types');
    $config->set('negotiation.language_interface.enabled', [
      'language-url' => -8,
    ])->save();

    // Configure URL language negotiation to use path prefix.
    $language_negotiation_url_config = $this->config('language.negotiation');
    $language_negotiation_url_config->set('url.source', 'path_prefix')->save();

    // Ensure the French language has a path prefix set.
    $language_negotiation_url_config->set('url.prefixes.fr', 'fr')->save();

    // Rebuild the container to apply language negotiation changes.
    $this->rebuildContainer();

    // Set the current domain.
    $this->getActiveDomain(TRUE);
  }

  /**
   * Tests domain source language negotiation.
   */
  public function testDomainSourceLanguageNegotiation() {
    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $domain1 = $domains['example_com'];
    $domain2 = $domains['one_example_com'];

    // Create a node on domain 1, but set domain 2 as source.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Node',
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $domain2->id(),
    ]);

    // Create a French translation.
    $translation = $node->addTranslation('fr');
    $translation->set('title', 'Test Node FR');
    $translation->set(DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD, $domain2->id());
    $translation->save();

    $language_fr = \Drupal::languageManager()->getLanguage('fr');
    $options = ['language' => $language_fr, 'absolute' => TRUE, 'entity' => $node];

    // 1. Test with language_negotiation DISABLED (default).
    $config = $this->config('domain.settings');
    $config->set('language_negotiation', FALSE)->save();
    $this->clearCaches();

    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options)->toString();
    $this->assertStringContainsString($domain2->getPath(), $url);
    $this->assertStringContainsString('/fr/node/' . $node->id(), $url);

    // 2. Test with language_negotiation ENABLED, using path_prefix.
    $config->set('language_negotiation', TRUE)->save();
    $this->clearCaches();

    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options)->toString();
    $this->assertStringContainsString($domain2->getPath(), $url);
    $this->assertStringContainsString('/fr/node/' . $node->id(), $url);

    // 3. Test with language_negotiation ENABLED and mixed negotiation.
    // Configure one_example_com to use domain-based language negotiation.
    // Using domain_config to provide per-domain configuration.
    $domain2_hostname = parse_url($domain2->getPath(), PHP_URL_HOST);
    /** @var \Drupal\Core\Config\StorageInterface $collection_storage */
    $collection_storage = $this->container->get('config.storage')->createCollection('domain.' . $domain2->id());
    $collection_storage->write('language.negotiation', [
      'url' => [
        'source' => 'domain',
        'domains' => ['fr' => $domain2_hostname],
      ],
    ]);
    $this->clearCaches();

    // Now, when generating URL for $domain2, it should use domain negotiation
    // because DomainSourcePathProcessor switches the active domain to domain2,
    // and domain_config should then override the language negotiation config.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options)->toString();

    // The URL should be http://one.localhost/node/1 (no /fr)
    $this->assertStringContainsString($domain2->getPath(), $url);
    $this->assertStringNotContainsString('/fr/node/' . $node->id(), $url);
    $this->assertStringContainsString('/node/' . $node->id(), $url);

    // 4. Verify that the fr prefix comes back if we disable
    // language_negotiation in domain settings.
    $config->set('language_negotiation', FALSE)->save();
    $this->clearCaches();

    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options)->toString();
    // Even if domain2 has domain-based negotiation override, it should be
    // ignored now.
    // Default global negotiation (path_prefix) should be used by the core
    // processor if DomainSourcePathProcessor doesn't run its own negotiation.
    $this->assertStringContainsString($domain2->getPath(), $url);
    $this->assertStringContainsString('/fr/node/' . $node->id(), $url);

    // 5. Verify that the active domain (domain1) still uses path_prefix.
    // We can't easily test this without changing the link target to domain1,
    // but we've already shown that it works when domain2 is the target.
    // To be sure, we can check a link to a node where domain1 is the source.
    $node1 = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Node 1',
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $domain1->id(),
    ]);
    $options1 = ['language' => $language_fr, 'absolute' => TRUE, 'entity' => $node1];
    $url1 = Url::fromRoute('entity.node.canonical', ['node' => $node1->id()], $options1)->toString();
    $this->assertStringContainsString($domain1->getPath(), $url1);
    $this->assertStringContainsString('/fr/node/' . $node1->id(), $url1);
  }

  /**
   * Clears caches related to configuration, path processing, and routing.
   */
  protected function clearCaches() {
    $this->container->get('config.factory')->reset();
    $this->container->get('router.builder')->rebuild();
    $this->container->get('domain_source.path_processor')->reset();
    $this->container->get('domain.path_processor')->reset();
  }

}
