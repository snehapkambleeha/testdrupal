<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain\Entity\Domain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests domain negotiation survives a mid-request kernel rebuild.
 *
 * A kernel rebuild triggered during a request would replace
 * DomainNegotiationContext with a fresh, empty instance. Any
 * subsequent code path reading the active domain (URL generation,
 * config overrides, batch_process()) would then see no active
 * domain. Tagging the service with `persist` transfers the same
 * instance into the rebuilt container, preserving the negotiated
 * state. ModuleInstaller::install() and ::uninstall() are the
 * known triggers exercised here; the fix applies to any rebuild.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainContextAfterKernelRebuildTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_config',
    'language',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'language']);

    // Create a domain that matches the pushed request hostname so
    // negotiation resolves to it.
    Domain::create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Example',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ])->save();

    // Push a request with the domain hostname so the negotiator picks
    // it up from the request stack. A mock session is attached because
    // the kernel rebuild triggered mid-test dispatches kernel.request,
    // and some subscribers require a session on the current request.
    $request = Request::create('http://example.com/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Tests domain context survives install() and uninstall() rebuilds.
   *
   * Reproduces issue #3586001: a per-domain override is applied, then
   * a module is installed (and later uninstalled) mid-request. Each
   * kernel rebuild replaces DomainNegotiationContext with a fresh
   * instance unless it is tagged `persist`. Without that tag, the
   * override silently stops applying because DomainConfigFactoryOverride
   * sees a NULL domain id.
   *
   * Install and uninstall are exercised in the same method to amortize
   * the kernel boot across the two symmetric flows.
   */
  public function testActiveDomainSurvivesModuleInstallAndUninstall(): void {
    // Set up: base value on the shared config, and a domain-specific
    // override on top of it.
    $this->container->get('config.factory')
      ->getEditable('system.site')
      ->set('name', 'Base name')
      ->save();
    $this->container->get('domain.config_factory_override')
      ->getOverrideEditable('example_com', 'system.site')
      ->set('name', 'Override name')
      ->save();

    // Negotiate the active domain and confirm the override applies.
    $this->container->get('domain.negotiator')->getActiveDomain(TRUE);
    $this->assertSame(
      'example_com',
      $this->container->get(DomainNegotiationContext::class)->getDomainId()
    );
    $this->assertSame(
      'Override name',
      $this->container->get('config.factory')->get('system.site')->get('name')
    );

    // Install a module. ModuleInstaller::install() rebuilds the
    // kernel; without the `persist` tag DomainNegotiationContext
    // would be replaced by a fresh empty instance at this point.
    $this->container->get('module_installer')->install(['help']);
    $this->container = \Drupal::getContainer();

    $this->assertSame(
      'example_com',
      $this->container->get(DomainNegotiationContext::class)->getDomainId(),
      'Active domain id is preserved across the install() kernel rebuild.'
    );
    $this->assertSame(
      'Override name',
      $this->container->get('config.factory')->get('system.site')->get('name'),
      'Per-domain config override still applies after the install() rebuild.'
    );

    // Uninstall the module. ModuleInstaller::uninstall() rebuilds
    // the kernel too; the same persistence guarantee must hold.
    $this->container->get('module_installer')->uninstall(['help']);
    $this->container = \Drupal::getContainer();

    $this->assertSame(
      'example_com',
      $this->container->get(DomainNegotiationContext::class)->getDomainId(),
      'Active domain id is preserved across the uninstall() kernel rebuild.'
    );
    $this->assertSame(
      'Override name',
      $this->container->get('config.factory')->get('system.site')->get('name'),
      'Per-domain config override still applies after the uninstall() rebuild.'
    );
  }

}
