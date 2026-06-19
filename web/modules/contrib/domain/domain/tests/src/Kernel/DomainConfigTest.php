<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests domain config elements.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_config_schema_test'];

  /**
   * Test setup.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('domain');
    // Install the test domain record & views config.
    $this->installConfig('domain_config_schema_test');
  }

  /**
   * Dummy test method to ensure config gets installed.
   */
  public function testRun() {
    $this->assertTrue(TRUE);
  }

}
