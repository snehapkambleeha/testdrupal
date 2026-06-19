<?php

namespace Drupal\Tests\domain_source\Functional;

use Drupal\Core\Url;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_source\DomainSourceElementManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests behavior for the domain source path processor with path aliases.
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourcePathAliasTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_source',
    'field',
    'node',
    'user',
    'path',
    'path_alias',
    'menu_link_content',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create 3 domains.
    $this->domainCreateTestDomains(3);
  }

  /**
   * Tests domain source path processor with path aliases.
   */
  public function testDomainSourcePathAlias() {
    // Create a node assigned to a source domain.
    $source_domain_id = 'two_example_com';
    $node_values = [
      'type' => 'page',
      'title' => 'Test Node with Alias',
      'status' => 1,
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $source_domain_id,
    ];
    $node = $this->createNode($node_values);

    // Create a path alias for the node.
    $alias_path = '/test-page-alias';
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias_path,
    ]);
    $path_alias->save();

    // Get domains.
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    /** @var \Drupal\domain\DomainInterface $source_domain */
    $source_domain = $domains[$source_domain_id];

    // Variables for our tests.
    $node_path = 'node/' . $node->id();
    $expected_alias = $source_domain->getPath() . ltrim($alias_path, '/');

    // Test canonical route URL generation.
    $route_name = 'entity.node.canonical';
    $route_parameters = ['node' => $node->id()];
    $options = [];

    // Get the link using Url::fromRoute() - should use source domain.
    $url = Url::fromRoute($route_name, $route_parameters, $options)->toString();
    $this->assertEquals($expected_alias, $url, 'fromRoute uses source domain for node route');

    // Get the link using Url::fromUserInput() with node path.
    $url = Url::fromUserInput('/' . $node_path, $options)->toString();
    $this->assertEquals($expected_alias, $url, 'fromUserInput uses source domain for node path');

    // Get the link using Url::fromUserInput() with alias.
    $url = Url::fromUserInput($alias_path, $options)->toString();
    $this->assertEquals($expected_alias, $url, 'fromUserInput uses source domain for alias path');

    // Get the link using Url::fromUri() with entity URI.
    $entity_uri = 'entity:' . $node_path;
    $url = Url::fromUri($entity_uri, $options)->toString();
    $this->assertEquals($expected_alias, $url, 'fromUri uses source domain for entity URI');

    // Test with internal URI using alias.
    $internal_uri = 'internal:' . $alias_path;
    $url = Url::fromUri($internal_uri, $options)->toString();
    $this->assertEquals($expected_alias, $url, 'fromUri uses source domain for internal alias URI');

    // Test that a node without domain source uses the active domain.
    $node_no_source = $this->createNode([
      'type' => 'page',
      'title' => 'Node without source',
      'status' => 1,
    ]);

    // Create alias for the second node.
    $alias_path_2 = '/node-without-source';
    $path_alias_2 = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node_no_source->id(),
      'alias' => $alias_path_2,
    ]);
    $path_alias_2->save();

    // The URL should not be rewritten to use a different domain.
    $expected_alias_path_2 = base_path() . ltrim($alias_path_2, '/');
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node_no_source->id()])->toString();
    $this->assertEquals($expected_alias_path_2, $url, 'Node without domain source does not get absolute URL');

    $url = Url::fromUserInput($alias_path_2)->toString();
    $this->assertEquals($expected_alias_path_2, $url, 'Alias for node without domain source does not get absolute URL');
  }

  /**
   * Tests domain source path processor with alias updates.
   */
  public function testDomainSourcePathAliasUpdate() {
    // Create a node assigned to a source domain.
    $source_domain_id = 'two_example_com';
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Test Cache Node',
      'status' => 1,
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $source_domain_id,
    ]);

    // Create initial alias.
    $initial_alias = '/initial-alias';
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node->id(),
      'alias' => $initial_alias,
    ]);
    $path_alias->save();

    // Get the initial URL.
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $source_domain = $domains[$source_domain_id];
    $expected_initial = $source_domain->getPath() . ltrim($initial_alias, '/');

    $url = Url::fromUserInput($initial_alias)->toString();
    $this->assertEquals($expected_initial, $url, 'Initial alias URL uses source domain');

    // Update the alias.
    $updated_alias = '/updated-alias';
    $path_alias->set('alias', $updated_alias);
    $path_alias->save();

    // The URL should now reflect the updated alias.
    $expected_updated = $source_domain->getPath() . ltrim($updated_alias, '/');
    $url = Url::fromUserInput($updated_alias)->toString();
    $this->assertEquals($expected_updated, $url, 'Updated alias URL uses source domain after cache clear');
  }

  /**
   * Tests domain source path processor with menu links using path aliases.
   */
  public function testDomainSourceMenuLinkWithPathAlias() {
    // Create a node assigned to a source domain.
    $source_domain_id = 'one_example_com';
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Menu Link Test Node',
      'status' => 1,
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $source_domain_id,
    ]);

    // Create a path alias for the node.
    $alias_path = '/menu-test-alias';
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias_path,
    ]);
    $path_alias->save();

    // Create a menu link content entity using the alias.
    $menu_link = \Drupal::entityTypeManager()->getStorage('menu_link_content')->create([
      'title' => 'Test Menu Link',
      'link' => ['uri' => 'internal:' . $alias_path],
      'menu_name' => 'main',
      'expanded' => TRUE,
    ]);
    $menu_link->save();

    // Get domains.
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    /** @var \Drupal\domain\DomainInterface $source_domain */
    $source_domain = $domains[$source_domain_id];

    // Expected URL should use the source domain.
    $expected = $source_domain->getPath() . ltrim($alias_path, '/');

    // Test menu link URL generation.
    $menu_link_url = $menu_link->getUrlObject()->toString();
    $this->assertEquals($expected, $menu_link_url, 'Menu link URL uses source domain with alias');

    // Test URL generation from the menu link's URI.
    $uri = $menu_link->get('link')->getValue()[0]['uri'];
    $url = Url::fromUri($uri)->toString();
    $this->assertEquals($expected, $url, 'URL from menu link URI uses source domain');

    // Create another menu link using the canonical node path instead of alias.
    $canonical_menu_link = \Drupal::entityTypeManager()->getStorage('menu_link_content')->create([
      'title' => 'Canonical Menu Link',
      'link' => ['uri' => 'entity:node/' . $node->id()],
      'menu_name' => 'main',
    ]);
    $canonical_menu_link->save();

    // The canonical menu link should also use the source domain.
    $url = $canonical_menu_link->getUrlObject()->toString();
    $this->assertEquals($expected, $url, 'Canonical menu link URL uses source domain');

    // Test that menu links for nodes without domain source don't get rewritten.
    $node_no_source = $this->createNode([
      'type' => 'page',
      'title' => 'Node without source for menu',
      'status' => 1,
    ]);

    $menu_link_no_source = \Drupal::entityTypeManager()->getStorage('menu_link_content')->create([
      'title' => 'Menu Link No Source',
      'link' => ['uri' => 'entity:node/' . $node_no_source->id()],
      'menu_name' => 'main',
    ]);
    $menu_link_no_source->save();

    $expected = base_path() . 'node/' . $node_no_source->id();
    $url = $menu_link_no_source->getUrlObject()->toString();
    $this->assertEquals($expected, $url, 'Menu link without domain source does not get absolute URL');
  }

  /**
   * Tests domain source menu links rendered on home page.
   */
  public function testDomainSourceMenuLinkRendering() {
    // Create a node assigned to a source domain.
    $source_domain_id = 'two_example_com';
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Rendered Menu Test Node',
      'status' => 1,
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $source_domain_id,
    ]);

    // Create a path alias for the node.
    $alias_path = '/rendered-menu-alias';
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias_path,
    ]);
    $path_alias->save();

    // Create a menu link content entity using the entity URI.
    $menu_link = \Drupal::entityTypeManager()->getStorage('menu_link_content')->create([
      'title' => 'Rendered Test Link',
      'link' => ['uri' => 'internal:' . $alias_path],
      'menu_name' => 'main',
      'expanded' => TRUE,
      'weight' => 0,
    ]);
    $menu_link->save();

    // Required: Clear the path alias prefix list cache.
    $prefix_list = $this->container->has('path_alias.prefix_list')
      ? $this->container->get('path_alias.prefix_list')
      // @phpstan-ignore-next-line cspell:disable-next-line
      : $this->container->get('path_alias.whitelist');
    $prefix_list->destruct();

    // Get domains.
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    /** @var \Drupal\domain\DomainInterface $source_domain */
    $source_domain = $domains[$source_domain_id];

    // Expected URL should use the source domain.
    $expected_url = $source_domain->getPath() . ltrim($alias_path, '/');

    // Visit the home page to check the rendered menu.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the menu link is present with the correct URL.
    $this->assertSession()->linkExists('Rendered Test Link');
    $this->assertSession()->linkByHrefExists($expected_url);

    // Verify the specific menu link points to the source domain.
    $page = $this->getSession()->getPage();
    $link = $page->findLink('Rendered Test Link');
    $this->assertNotNull($link, 'Menu link found on page');
    $href = $link->getAttribute('href');
    $this->assertEquals($expected_url, $href, 'Rendered menu link href uses source domain');

    // Create a second menu link for a node without domain source as control.
    $node_no_source = $this->createNode([
      'type' => 'page',
      'title' => 'Control Node',
      'status' => 1,
    ]);

    $control_alias = '/control-alias';
    $control_path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node_no_source->id(),
      'alias' => $control_alias,
    ]);
    $control_path_alias->save();

    $control_menu_link = \Drupal::entityTypeManager()->getStorage('menu_link_content')->create([
      'title' => 'Control Link',
      'link' => ['uri' => 'entity:node/' . $node_no_source->id()],
      'menu_name' => 'main',
      'weight' => 1,
    ]);
    $control_menu_link->save();

    // Visit the home page again to check both links.
    $this->drupalGet('<front>');

    // Check that the control link uses the expected relative URL.
    $expected_control_url = base_path() . ltrim($control_alias, '/');
    $this->assertSession()->linkExists('Control Link');
    $this->assertSession()->linkByHrefExists($expected_control_url);

    $control_link = $page->findLink('Control Link');
    $this->assertNotNull($control_link, 'Control menu link found on page');
    $control_href = $control_link->getAttribute('href');
    $this->assertEquals($expected_control_url, $control_href, 'Control menu link uses relative URL');

    // Verify that the source domain link is absolute while control is relative.
    $this->assertStringContainsString('http', $href, 'Source domain link is absolute');
    $this->assertStringNotContainsString('http', $control_href, 'Control link is relative');
  }

}
