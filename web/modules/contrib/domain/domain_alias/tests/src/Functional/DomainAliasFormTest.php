<?php

namespace Drupal\Tests\domain_alias\Functional;

use Drupal\domain_alias\Entity\DomainAlias;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests domain alias creation and editing via the UI form.
 *
 * @group domain_alias
 */
#[Group('domain_alias')]
#[RunTestsInSeparateProcesses]
class DomainAliasFormTest extends DomainAliasTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_alias'];

  /**
   * Tests alias form submission with each redirect value.
   */
  public function testAliasFormRedirect(): void {
    $admin = $this->drupalCreateUser([
      'administer domains',
      'administer domain aliases',
      'create domain aliases',
      'view domain aliases',
    ]);
    $this->drupalLogin($admin);

    $this->domainCreateTestDomains(1);
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultiple();
    $domain = reset($domains);

    $add_url = 'admin/config/domain/alias/' . $domain->id() . '/add';

    // Test creating aliases with each valid redirect value.
    $redirects = [0, 301, 302];
    foreach ($redirects as $redirect) {
      $id = 'alias_redirect_' . $redirect;
      $pattern = "r{$redirect}." . $domain->getHostname();

      $this->drupalGet($add_url);
      $this->assertSession()->statusCodeEquals(200);

      $this->submitForm([
        'pattern' => $pattern,
        'id' => $id,
        'redirect' => $redirect,
        'environment' => 'default',
      ], 'Save');

      $this->assertSession()->pageTextContains('Created new domain alias.');

      $alias = DomainAlias::load($id);
      $this->assertNotNull($alias, "Alias for redirect $redirect was saved.");
      $this->assertEquals($redirect, $alias->getRedirect());
      $this->assertEquals($pattern, $alias->getPattern());
    }

    // Test editing an existing alias redirect value.
    $this->drupalGet('admin/config/domain/alias/edit/alias_redirect_0');
    $this->submitForm(['redirect' => 302], 'Save');
    $this->assertSession()->pageTextContains('Updated domain alias.');

    $alias = DomainAlias::load('alias_redirect_0');
    $this->assertEquals(302, $alias->getRedirect());
  }

}
