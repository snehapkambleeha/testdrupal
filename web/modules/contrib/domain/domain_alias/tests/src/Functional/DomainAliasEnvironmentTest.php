<?php

namespace Drupal\Tests\domain_alias\Functional;

use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests behavior for the domain alias environment handler.
 *
 * @group domain_alias
 */
#[Group('domain_alias')]
#[RunTestsInSeparateProcesses]
class DomainAliasEnvironmentTest extends DomainAliasTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_alias', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create 3 domains. These will be example.com, one.example.com,
    // two.example.com.
    $this->domainCreateTestDomains(3);
  }

  /**
   * Test for environment matching.
   */
  public function testDomainAliasEnvironments() {
    /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
    $domain_storage = \Drupal::entityTypeManager()->getStorage('domain');
    /** @var \Drupal\domain_alias\DomainAliasStorageInterface $alias_storage */
    $alias_storage = \Drupal::entityTypeManager()->getStorage('domain_alias');
    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = $domain_storage->loadMultiple();
    // Our patterns should map to example.com, one.example.com, two.example.com.
    $patterns = [
      '*.' . $this->baseHostname, 'four.' . $this->baseHostname,
      'five.' . $this->baseHostname,
    ];

    foreach ($domains as $domain) {
      $values = [
        'domain_id' => $domain->id(),
        'pattern' => array_shift($patterns),
        'redirect' => 0,
        'environment' => 'local',
      ];
      $this->createDomainAlias($values);
    }
    // Test the environment loader.
    $local = $alias_storage->loadByEnvironment('local');
    $this->assertCount(3, $local, 'Three aliases set to local');
    // Test the environment matcher. $domain here is two.example.com.
    $test_domain = end($domains);
    $matches = $alias_storage->loadByEnvironmentMatch($test_domain, 'local');
    $this->assertCount(1, $matches, 'One environment match loaded');
    $alias = current($matches);
    $this->assertEquals('five.' . $this->baseHostname, $alias->getPattern(), 'Proper pattern match loaded.');

    // Set one alias to a different environment.
    $alias->set('environment', 'testing')->save();
    $local = $alias_storage->loadByEnvironment('local');
    $this->assertCount(2, $local, 'Two aliases set to local');
    // Test the environment matcher. $domain here is two.example.com.
    $matches = $alias_storage->loadByEnvironmentMatch($test_domain, 'local');
    $this->assertCount(0, $matches, 'No environment matches loaded');

    // Test the environment matcher. $domain here is one.example.com.
    /** @var \Drupal\domain\DomainInterface $test_domain */
    $test_domain = $domain_storage->load('one_example_com');
    $matches = $alias_storage->loadByEnvironmentMatch($test_domain, 'local');
    $this->assertCount(1, $matches, 'One environment match loaded');
    $alias = current($matches);
    $this->assertEquals('four.' . $this->baseHostname, $alias->getPattern(), 'Proper pattern match loaded.');

    // Now load a page and check things.
    // Since we cannot read the service request, we place a block
    // which shows links to all domains.
    $this->drupalPlaceBlock('domain_switcher_block');

    // To get around block access, let the anon user view the block.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['administer domains']);
    // For a non-aliased request, the url list should be normal.
    $this->drupalGet($test_domain->getPath());
    foreach ($domains as $domain) {
      $this->assertSession()->assertEscaped($domain->getHostname());
      $this->assertSession()->linkByHrefExists($domain->getPath(), 0, 'Link found: ' . $domain->getPath());
    }
    // For an aliased request (four.example.com), the list should be aliased.
    $url = $test_domain->getScheme() . $alias->getPattern() . $GLOBALS['base_path'];
    $this->drupalGet($url);
    foreach ($matches as $match) {
      $this->assertSession()->assertEscaped($match->getPattern());
    }
  }

}
