<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that www_prefix is stored in the container to avoid config loading.
 *
 * This test verifies that the www_prefix setting is read from bootstrap config
 * and stored as a container parameter, avoiding premature configuration loading
 * during domain negotiation which can cause reentrancy issues.
 *
 * @group domain
 * @see https://www.drupal.org/project/domain/issues/3560725
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainWwwPrefixContainerTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'system', 'user', 'field'];

  /**
   * The domain storage service.
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
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['field', 'domain']);

    $this->domainStorage = $this->container->get('entity_type.manager')->getStorage('domain');
  }

  /**
   * Tests that www_prefix is stored as a container parameter.
   */
  public function testWwwPrefixContainerParameter(): void {
    // Verify the container parameter exists.
    $this->assertTrue(
      $this->container->hasParameter('domain.www_prefix'),
      'The domain.www_prefix parameter should exist in the container.'
    );

    // Get the parameter value.
    $www_prefix = $this->container->getParameter('domain.www_prefix');

    // It should be a boolean.
    $this->assertIsBool(
      $www_prefix,
      'The domain.www_prefix parameter should be a boolean.'
    );

    // Get the configuration value.
    $config_value = $this->config('domain.settings')->get('www_prefix');

    // The parameter should match the configuration.
    $this->assertEquals(
      $config_value,
      $www_prefix,
      'The container parameter should match the configuration value.'
    );
  }

  /**
   * Tests that ConfigSubscriber exists to rebuild container on config change.
   */
  public function testConfigSubscriberExists(): void {
    // Verify the ConfigSubscriber service is defined.
    $this->assertTrue(
      $this->container->has('Drupal\domain\EventSubscriber\ConfigSubscriber'),
      'The ConfigSubscriber should be registered as a service.'
    );

    // Verify the subscriber is registered for the correct event.
    $subscriber = $this->container->get('Drupal\domain\EventSubscriber\ConfigSubscriber');
    $this->assertNotNull($subscriber, 'ConfigSubscriber should be instantiable.');

    $events = $subscriber::getSubscribedEvents();
    $this->assertArrayHasKey(
      'config.save',
      $events,
      'ConfigSubscriber should subscribe to config.save event.'
    );
  }

  /**
   * Tests that updating www_prefix config triggers container parameter update.
   */
  public function testWwwPrefixConfigChangeUpdatesContainerParameter(): void {
    // Get initial values.
    $initial_config = $this->config('domain.settings')->get('www_prefix');
    $initial_parameter = $this->container->getParameter('domain.www_prefix');

    // They should match initially.
    $this->assertEquals($initial_config, $initial_parameter, 'Initial config and parameter should match.');

    // Change the config value.
    $new_value = !$initial_config;
    $this->config('domain.settings')
      ->set('www_prefix', $new_value)
      ->save();

    // Check ConfigSubscriber marked the container as needing rebuild.
    $kernel = $this->container->get('kernel');
    $reflection = new \ReflectionProperty($kernel, 'containerNeedsRebuild');
    $this->assertTrue($reflection->getValue($kernel));

    // Simulate container rebuild (as would happen on next request).
    $kernel->rebuildContainer();

    // The parameter in the new container should reflect the config change.
    $updated_parameter = $this->container->getParameter('domain.www_prefix');

    $this->assertEquals(
      $new_value,
      $updated_parameter,
      'After config change and container rebuild, the parameter should be updated.'
    );
  }

  /**
   * Tests prepareHostname behavior with the www_prefix setting.
   */
  public function testPrepareHostnameStripsWwwBasedOnSetting(): void {
    // Get the current www_prefix setting from the container parameter.
    $www_prefix = $this->container->getParameter('domain.www_prefix');

    // Test hostname with www prefix.
    $hostname_with_www = 'www.example.com';
    $result = $this->domainStorage->prepareHostname($hostname_with_www);

    if ($www_prefix) {
      $this->assertEquals('example.com', $result, 'When www_prefix is TRUE, www. should be stripped.');
    }
    else {
      $this->assertEquals('www.example.com', $result, 'When www_prefix is FALSE, www. should remain.');
    }

    // Test hostname without a www prefix.
    $hostname_without_www = 'example.com';
    $result = $this->domainStorage->prepareHostname($hostname_without_www);
    $this->assertEquals('example.com', $result, 'Hostname without www should remain unchanged.');

    // Test subdomain with a www prefix.
    $subdomain_with_www = 'www.sub.example.com';
    $result = $this->domainStorage->prepareHostname($subdomain_with_www);

    if ($www_prefix) {
      $this->assertEquals('sub.example.com', $result, 'When www_prefix is TRUE, www. should be stripped from subdomain.');
    }
    else {
      $this->assertEquals('www.sub.example.com', $result, 'When www_prefix is FALSE, www. should remain in subdomain.');
    }
  }

}
