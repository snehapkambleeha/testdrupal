<?php

namespace Drupal\Tests\domain\Functional;

use Drupal\Tests\domain\Traits\DomainFieldTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain record entity reference field type.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainEntityReferenceTest extends DomainTestBase {

  use DomainFieldTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'field', 'field_ui'];

  /**
   * Create, edit and delete a domain field via the user interface.
   */
  public function testDomainField() {
    $admin_user = $this->drupalCreateUser([
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer domains',
    ]);
    $this->drupalLogin($admin_user);

    // Visit the article field administration page.
    $this->drupalGet('admin/structure/types/manage/article/fields');
    $this->assertSession()->statusCodeEquals(200);

    // Check for a domain field.
    $this->assertSession()->pageTextNotContains('Domain test field');

    // Visit the article field display administration page.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->statusCodeEquals(200);

    // Check for a domain field.
    $this->assertSession()->pageTextNotContains('Domain test field');

    // Create test domain field.
    $this->domainCreateTestField();

    // Visit the article field administration page.
    $this->drupalGet('admin/structure/types/manage/article/fields');

    // Check the new field.
    $this->assertSession()->pageTextContains('Domain test field');

    // Visit the article field display administration page.
    $this->drupalGet('admin/structure/types/manage/article/display');

    // Check the new field.
    $this->assertSession()->pageTextContains('Domain test field');

    // Visit the field config page.
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_domain_access');
  }

  /**
   * Create content for a domain field.
   */
  public function testDomainFieldStorage() {
    $admin_user = $this->drupalCreateUser([
      'bypass node access',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer domains',
    ]);
    $this->drupalLogin($admin_user);

    // Create test domain field.
    $this->domainCreateTestField();

    // Create 5 domains.
    $this->domainCreateTestDomains(5);

    // Visit the article field display administration page.
    $this->drupalGet('node/add/article');
    $this->assertSession()->statusCodeEquals(200);

    // Check the new field exists on the page.
    $this->assertSession()->pageTextContains('Domain test field');

    // We expect to find 5 domain options.
    $one = $two = NULL;
    $domains = $this->getDomains();
    foreach ($domains as $domain) {
      $string = 'value="' . $domain->id() . '"';
      $this->assertSession()->responseContains($string);
      if (is_null($one)) {
        $one = $domain->id();
        continue;
      }
      if (is_null($two)) {
        $two = $domain->id();
      }
    }

    // Try to post a node, assigned to the first two domains.
    $edit = [
      'title[0][value]' => 'Test node',
      "field_domain[{$one}]" => TRUE,
      "field_domain[{$two}]" => TRUE,
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $values = $node->get('field_domain');

    // Get the expected value count.
    $this->assertCount(2, $values, 'Node saved with two domain records.');

  }

  /**
   * Creates a simple field for testing on the article content type.
   *
   * Note: This code is a model for auto-creation of fields.
   */
  public function domainCreateTestField() {
    $this->domainCreateDomainReferenceFieldOnArticle();
  }

}
