<?php

namespace Drupal\Tests\domain\Functional;

use Drupal\Tests\domain\Traits\DomainFieldTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests access to domain entities.
 *
 * @link https://www.drupal.org/project/domain/issues/3128421
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainEntityAccessTest extends DomainTestBase {

  use DomainFieldTestTrait;

  /**
   * Tests initial domain creation.
   */
  public function testDomainCreate() {
    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer domains',
    ]);
    $this->drupalLogin($admin);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');

    // No domains should exist.
    $this->domainTableIsEmpty();

    // Visit the main domain administration page.
    $this->drupalGet('admin/config/domain');

    // Check for the add message.
    $this->assertSession()->pageTextContains('There are no domain record entities yet.');

    // Visit the add domain administration page.
    $this->drupalGet('admin/config/domain/add');

    // Make a POST request on admin/config/domain/add.
    $edit = $this->domainPostValues();
    // Use hostname with dot (.) to avoid validation error.
    $edit['hostname'] = 'example.com';
    $this->drupalGet('admin/config/domain/add');
    $this->submitForm($edit, 'Save');

    // Did it save correctly?
    $default_id = $storage->loadDefaultId();
    $this->assertNotEmpty($default_id, 'Domain record saved via form.');

    // Does it load correctly?
    $storage->resetCache([$default_id]);
    $new_domain = $storage->load($default_id);
    $this->assertEquals($default_id, $new_domain->id(), 'Domain loaded properly.');

    $this->drupalLogout();
    $editor = $this->drupalCreateUser([
      'access administration pages',
      'create domains',
      'view domain list',
    ]);
    $this->drupalLogin($editor);

    // Visit the add domain add page.
    $this->drupalGet('admin/config/domain/add');
    $this->assertSession()->statusCodeEquals(200);
    // Make a POST request on admin/config/domain/add.
    $edit = $this->domainPostValues();
    // Use hostname with dot (.) to avoid validation error.
    $edit['hostname'] = 'one.example.com';
    $edit['id'] = $storage->createMachineName($edit['hostname']);
    $this->drupalGet('admin/config/domain/add');
    $this->submitForm($edit, 'Save');

    // Does it load correctly?
    $storage->resetCache([$edit['id']]);
    $new_domain = $storage->load($edit['id']);
    $this->assertEquals($edit['id'], $new_domain->id(), 'Domain loaded properly.');

    $this->drupalLogout();
    $noneditor = $this->drupalCreateUser([
      'access administration pages',
    ]);
    $this->drupalLogin($noneditor);
    // Visit the add domain administration page.
    $this->drupalGet('admin/config/domain/add');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the "view domain entity" permission.
   */
  public function testDomainViewEntityPermission() {
    $admin_user = $this->drupalCreateUser([
      'bypass node access',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer domains',
    ]);
    $this->drupalLogin($admin_user);

    // Create test domain field.
    $this->domainCreateDomainReferenceFieldOnArticle();

    // Create 2 domains.
    $this->domainCreateTestDomains(2);
    $domains = $this->getDomains();
    $domain_ids = array_keys($domains);
    $one = $domains[$domain_ids[0]];
    $two = $domains[$domain_ids[1]];

    // Try to post a node, assigned to our two domains.
    $edit = [
      'title[0][value]' => 'Test node',
      "field_domain[{$one->id()}]" => TRUE,
      "field_domain[{$two->id()}]" => TRUE,
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    // Logout to switch to anonymous user.
    $this->drupalLogout();

    // Visit the node page.
    $this->drupalGet('node/1');
    // Check that the domain labels are not visible on page.
    $this->assertSession()->pageTextNotContains($one->label());
    $this->assertSession()->pageTextNotContains($two->label());

    // Create a user with the "view domain entity" permission.
    $viewer = $this->drupalCreateUser([
      'view domain entity',
    ]);
    // Log in as the user with domain entity view permission.
    $this->drupalLogin($viewer);

    // Visit the node page.
    $this->drupalGet('node/1');
    // Check that the domain labels are visible on page.
    $this->assertSession()->pageTextContains($one->label());
    $this->assertSession()->pageTextContains($two->label());
  }

}
