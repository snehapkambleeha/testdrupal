<?php

namespace Drupal\Tests\domain_alias\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that alias hostname is applied to the active domain.
 *
 * When a request is matched via a non-default environment alias,
 * the active domain must carry the alias hostname, not the
 * canonical one. This verifies that hostname rewriting happens
 * during negotiation (in hook_domain_request_alter) rather than
 * being deferred to hook_domain_load.
 *
 * @group domain_alias
 */
#[Group('domain_alias')]
#[RunTestsInSeparateProcesses]
class DomainAliasHostnameTest extends KernelTestBase {

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

    $this->domainStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain');
    $this->aliasStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain_alias');
  }

  /**
   * Tests active domain hostname after alias negotiation.
   *
   * Creates a domain example.com with a non-default environment
   * alias staging.example.com. Simulates a request to the alias
   * hostname and verifies that the negotiated active domain has
   * the alias hostname (staging.example.com), not the canonical
   * one (example.com).
   */
  public function testActiveDomainHasAliasHostname(): void {
    $domain = $this->domainStorage->create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Example',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ]);
    $domain->save();

    $alias = $this->aliasStorage->create([
      'id' => 'staging_example_com',
      'domain_id' => 'example_com',
      'pattern' => 'staging.example.com',
      'redirect' => 0,
      'environment' => 'local',
    ]);
    $alias->save();

    // Simulate a request to the alias hostname.
    $request = Request::create('http://staging.example.com/');
    $request->setSession(
      new Session(new MockArraySessionStorage()),
    );
    $this->container->get('request_stack')->push($request);

    // Use getActiveDomain() as real code does. This sets
    // isNegotiating=TRUE which prevents the recursive
    // negotiation that would otherwise mask the bug.
    /** @var \Drupal\domain\DomainNegotiatorInterface $negotiator */
    $negotiator = $this->container->get('domain.negotiator');
    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active, 'A domain was negotiated.');
    $this->assertEquals(
      'example_com',
      $active->id(),
      'The correct domain entity was resolved.',
    );
    $this->assertEquals(
      'staging.example.com',
      $active->getHostname(),
      'Active domain has the alias hostname, not the canonical one.',
    );
  }

}
