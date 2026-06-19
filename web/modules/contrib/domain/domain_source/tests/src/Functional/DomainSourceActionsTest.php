<?php

namespace Drupal\Tests\domain_source\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain source actions.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainSourceActionsTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_access',
    'domain_source',
    'field',
    'node',
    'user',
  ];

  /**
   * Tests bulk actions through the content overview page.
   */
  public function testDomainSourceActions() {
    $perms = [
      'access administration pages',
      'access content overview',
      'edit any article content',
    ];
    $admin_user = $this->drupalCreateUser($perms);

    // Create test domains.
    $this->domainCreateTestDomains(2);

    // Create a test node.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test node',
    ]);

    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/content');

    // Try setting source without domain assignment.
    $action_id = 'domain_source_set_action.example_com';
    $edit = [
      'node_bulk_form[0]' => TRUE,
      'action' => $action_id,
    ];
    $this->submitForm($edit, 'Apply to selected items');

    // Check that we have the right warning on page.
    $this->assertSession()->pageTextContains('Content 1 must be assigned to domain example_com');

    // Try bulk assigning the domain to our node.
    $action_id = 'domain_access_add_action_example_com';
    $edit = [
      'node_bulk_form[0]' => TRUE,
      'action' => $action_id,
    ];
    $this->submitForm($edit, 'Apply to selected items');

    // Retry setting the domain source after the domain assignment.
    $action_id = 'domain_source_set_action.example_com';
    $edit = [
      'node_bulk_form[0]' => TRUE,
      'action' => $action_id,
    ];
    $this->submitForm($edit, 'Apply to selected items');

    // Check that we do not have the previous warning on page.
    $this->assertSession()->pageTextNotContains('Content 1 must be assigned to domain example_com');

    // Check that the domain source have been properly set.
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $node_storage->load(1);
    // Check that the value is set.
    $value = $this->container->get('domain_source.helper')->getSourceDomainId($node);
    $this->assertEquals('example_com', $value, 'Node saved with proper source record.');

  }

}
