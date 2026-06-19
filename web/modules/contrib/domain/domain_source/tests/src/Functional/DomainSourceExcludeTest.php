<?php

namespace Drupal\Tests\domain_source\Functional;

use Drupal\Core\Url;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_source\DomainSourceElementManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests behavior for excluding some links from rewriting.
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourceExcludeTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_source',
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
  }

  /**
   * Tests domain source excludes.
   */
  public function testDomainSourceExclude() {
    // Create a node, assigned to a source domain.
    $id = 'one_example_com';

    $node_values = [
      'type' => 'page',
      'title' => 'foo',
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => $id,
    ];
    $node = $this->createNode($node_values);

    // Variables for our tests.
    $path = 'node/1';
    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $source = $domains[$id];
    $expected = $source->getPath() . $path;
    $route_name = 'entity.node.canonical';
    $route_parameters = ['node' => 1];
    $uri = 'entity:' . $path;
    $uri_path = '/' . $path;
    $options = [];

    // Get the link using Url::fromRoute().
    $url = Url::fromRoute($route_name, $route_parameters, $options)->toString();
    $this->assertEquals($expected, $url, 'fromRoute');

    // Get the link using Url::fromUserInput()
    $url = Url::fromUserInput($uri_path, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUserInput');

    // Get the link using Url::fromUri()
    $url = Url::fromUri($uri, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUri');

    // Variables for our tests.
    $path = 'node/1/edit';
    $expected = base_path() . $path;
    $route_name = 'entity.node.edit_form';
    $route_parameters = ['node' => 1];
    $uri = 'internal:/' . $path;
    $uri_path = '/' . $path;
    $options = [];

    // Clear caches related to path processing and routing.
    $this->clearCaches();

    // Exclude the edit_form route suffix from rewrites.
    $config = $this->config('domain_source.settings');
    $config->set('exclude_routes', ['edit_form' => 'edit_form'])->save();

    // Get the link using Url::fromRoute().
    $url = Url::fromRoute($route_name, $route_parameters, $options)->toString();
    $this->assertEquals($expected, $url, 'fromRoute');

    // Get the link using Url::fromUserInput()
    $url = Url::fromUserInput($uri_path, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUserInput');

    // Get the link using Url::fromUri()
    $url = Url::fromUri($uri, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUri');

    // Clear caches related to path processing and routing.
    $this->clearCaches();

    // Exclude the node edit route name from rewrites.
    $config->clear('exclude_routes');
    $config->set('excluded_route_names', ['entity.node.edit_form'])->save();

    // Get the link using Url::fromRoute().
    $url = Url::fromRoute($route_name, $route_parameters, $options)->toString();
    $this->assertEquals($expected, $url, 'fromRoute');

    // Get the link using Url::fromUserInput()
    $url = Url::fromUserInput($uri_path, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUserInput');

    // Get the link using Url::fromUri()
    $url = Url::fromUri($uri, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUri');

    // Clear caches related to path processing and routing.
    $this->clearCaches();

    // Exclude the node edit path pattern from rewrites.
    $config->clear('excluded_route_names');
    $config->set('excluded_paths', ['/node/*/edit'])->save();

    // Get the link using Url::fromRoute().
    $url = Url::fromRoute($route_name, $route_parameters, $options)->toString();
    $this->assertEquals($expected, $url, 'fromRoute');

    // Get the link using Url::fromUserInput()
    $url = Url::fromUserInput($uri_path, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUserInput');

    // Get the link using Url::fromUri()
    $url = Url::fromUri($uri, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUri');

    // Clear caches related to path processing and routing.
    $this->clearCaches();

    // Remove all rewrite exclusions from the configuration.
    $config->clear('excluded_paths')->save();
    $expected = $source->getPath() . $path;

    // Get the link using Url::fromRoute().
    $url = Url::fromRoute($route_name, $route_parameters, $options)->toString();
    $this->assertEquals($expected, $url, 'fromRoute');

    // Get the link using Url::fromUserInput()
    $url = Url::fromUserInput($uri_path, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUserInput');

    // Get the link using Url::fromUri()
    $url = Url::fromUri($uri, $options)->toString();
    $this->assertEquals($expected, $url, 'fromUri');
  }

  /**
   * Clears caches related to path processing and routing.
   *
   * This method rebuilds the router and resets the domain source path processor
   * to ensure that path and route changes are properly reflected.
   */
  protected function clearCaches() {
    // Because of path cache, we have to rebuild here.
    \Drupal::service('router.builder')->rebuild();
    // Reset the path processing service.
    \Drupal::service('domain_source.path_processor')->reset();
  }

}
