<?php

namespace Drupal\Tests\domain_config\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config system.
 *
 * @group domain_config
 */
#[Group('domain_config')]
#[RunTestsInSeparateProcesses]
class DomainConfigHookProblemTest extends DomainConfigHookTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Adds the domain_config module.
    'domain_config',
    'language',
  ];

}
