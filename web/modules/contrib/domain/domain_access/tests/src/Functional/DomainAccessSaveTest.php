<?php

namespace Drupal\Tests\domain_access\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests behavior for saving the domain access field elements.
 *
 * @group domain_access
 */
#[Group('domain_access')]
#[RunTestsInSeparateProcesses]
class DomainAccessSaveTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_access', 'field', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create 5 domains.
    $this->domainCreateTestDomains(5);
  }

  /**
   * Basic test setup.
   */
  public function testDomainAccessSave() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    // Save a node programmatically.
    $node = $storage->create([
      'type' => 'article',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 1,
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => ['example_com'],
      DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD => 1,
    ]);
    $node->save();

    // Load the node.
    $node = $storage->load(1);

    // Check that two values are set properly.
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(1, $values, 'Node saved with one domain records.');
    $value = DomainAccessManager::getAllValue($node);
    $this->assertTrue($value, 'Node saved to all affiliates.');

    // Save a node with different values.
    $node = $storage->create([
      'type' => 'article',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 1,
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [
        'example_com',
        'one_example_com',
      ],
      DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD => 0,
    ]);
    $node->save();

    // Load and check the node.
    $node = $storage->load(2);
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(2, $values, 'Node saved with two domain records.');
    $value = DomainAccessManager::getAllValue($node);
    $this->assertFalse($value, 'Node not saved to all affiliates.');
  }

}
