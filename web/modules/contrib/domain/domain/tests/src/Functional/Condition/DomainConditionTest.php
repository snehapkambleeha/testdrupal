<?php

namespace Drupal\Tests\domain\Functional\Condition;

use Drupal\Tests\domain\Functional\DomainTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain condition.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainConditionTest extends DomainTestBase {

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $manager;

  /**
   * A test domain.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $testDomain;

  /**
   * A test domain that never matches $test_domain.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $notDomain;

  /**
   * An array of all testing domains.
   *
   * @var \Drupal\domain\DomainInterface[]
   */
  protected $domains;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the condition manager.
    $this->manager = $this->container->get('plugin.manager.condition');

    // Create test domains.
    $this->domainCreateTestDomains(5);

    // Get two sample domains.
    $this->domains = $this->getDomains();
    $this->testDomain = array_shift($this->domains);
    $this->notDomain = array_shift($this->domains);
  }

  /**
   * Test the domain condition.
   */
  public function testConditions() {
    // Grab the domain condition and configure it to check against one domain.
    $configuration = [
      'domains' => [$this->testDomain->id() => $this->testDomain->id()],
    ];
    /** @var \Drupal\domain\Plugin\Condition\Domain $condition */
    $condition = $this->manager->createInstance('domain', $configuration);
    $condition->setContextValue('domain', $this->notDomain);
    $this->assertFalse($condition->execute(), 'Domain request condition fails on wrong domain.');

    // Grab the domain condition and configure it to check against itself.
    $configuration = [
      'domains' => [$this->testDomain->id() => $this->testDomain->id()],
    ];
    /** @var \Drupal\domain\Plugin\Condition\Domain $condition */
    $condition = $this->manager->createInstance('domain', $configuration);
    $condition->setContextValue('domain', $this->testDomain);
    $this->assertTrue($condition->execute(), 'Domain request condition succeeds on matching domain.');

    // Check for the proper summary.
    // Summaries require an extra space due to negate handling in summary().
    $this->assertEquals('Active domain is ' . $this->testDomain->label(), $condition->summary());

    // Check the negated summary.
    $condition->setConfig('negate', TRUE);
    $this->assertEquals('Active domain is not ' . $this->testDomain->label(), $condition->summary());

    // Check the negated condition.
    $this->assertFalse($condition->execute(), 'Domain request condition fails when condition negated.');
  }

}
