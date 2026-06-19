<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ability to set a variable scheme on a domain.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainVariableSchemeTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain'];

  /**
   * Domain id key.
   *
   * @var string
   */
  public $key = 'example_com';

  /**
   * The Domain storage handler service.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  public $domainStorage;

  /**
   * Test setup.
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a domain.
    $this->domainCreateTestDomains();

    // Get the services.
    $this->domainStorage = \Drupal::entityTypeManager()->getStorage('domain');
  }

  /**
   * Tests domain loading.
   */
  public function testDomainScheme() {
    // Set our testing parameters.
    $default_scheme = \Drupal::request()->getScheme();
    $alt_scheme = ($default_scheme === 'https') ? 'http' : 'https';
    $add_suffix = FALSE;

    // Our created domain should have a scheme that matches the request.
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->domainStorage->load($this->key);
    $this->assertEquals($default_scheme, $domain->getScheme($add_suffix));

    // Switch the scheme and see if that works.
    $domain->set('scheme', $alt_scheme);
    $domain->save();
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->domainStorage->load($this->key);
    $this->assertEquals($alt_scheme, $domain->getScheme($add_suffix));

    // Set the scheme to variable, and that should match the default.
    $domain->set('scheme', 'variable');
    $domain->save();
    $this->assertEquals($default_scheme, $domain->getScheme($add_suffix));
  }

}
