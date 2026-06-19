<?php

namespace Drupal\Tests\domain_source\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests behavior for the destination query parameter with domain source.
 *
 * @group domain_source
 */
#[Group('domain_source')]
#[RunTestsInSeparateProcesses]
class DomainSourceDestinationKernelTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_source',
    'field',
    'node',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('domain');
    $this->installConfig(['domain', 'domain_source', 'system']);

    // Create 2 domains.
    $this->domainCreateTestDomains(2);
  }

  /**
   * Tests domain source destination parameter.
   */
  public function testDomainSourceDestination() {
    // Enable the destination domain parameter for this test.
    $this->config('domain.settings')
      ->set('allow_destination_domain', TRUE)
      ->save();

    // Get the domains.
    $domains = $this->getDomains();
    $default_domain = $domains['example_com'];

    // Mock a request on the default domain.
    $request = Request::create('http://example.com/user/login');

    // 1. Test outbound path processor: destination -> domain_destination.
    $options = [
      'query' => ['destination' => '/user/login'],
      'active_domain' => $default_domain,
    ];

    // Call the processor directly to verify the logic.
    $processor = $this->container->get('domain.path_processor');
    $reflection = new \ReflectionClass($processor);
    $method = $reflection->getMethod('processDestinationParameter');
    $method->invokeArgs($processor, [&$options, $request]);

    $this->assertArrayHasKey('destination_domain', $options['query']);
    $this->assertEquals('http://example.com', $options['query']['destination_domain']);
    $this->assertEquals('/user/login', $options['query']['destination']);

    $destination_host = $options['query']['destination_domain'];
    $destination = $options['query']['destination'];

    // 2. Test inbound: destination_host + destination -> absolute destination.
    // We test this by calling the event subscriber directly.
    $request = Request::create('http://example.com/', 'GET', [
      'destination_domain' => $destination_host,
      'destination' => $destination,
    ]);
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);

    $this->container->get('domain.subscriber')->onKernelRequestDomain($event);

    $expected_absolute_destination = $destination_host . base_path() . ltrim($destination, '/');
    $this->assertEquals($expected_absolute_destination, $request->query->get('destination'));
    $this->assertFalse($request->query->has('destination_domain'));
  }

}
