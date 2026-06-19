<?php

namespace Drupal\Tests\domain\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the access rules and redirects for inactive domains.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainInactiveTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'node', 'views'];

  /**
   * Test inactive domain.
   */
  public function testInactiveDomain() {
    // Configure 'node' as front page, else the test loads the login form.
    $site_config = $this->config('system.site');
    $site_config->set('page.front', '/node')->save();

    // Create four new domains programmatically.
    $this->domainCreateTestDomains(4);
    $domains = $this->getDomains();

    // Grab a known domain for testing.
    $domain = $domains['two_example_com'];
    $this->drupalGet($domain->getPath());
    $this->assertTrue($domain->status(), 'Tested domain is set to active.');
    $this->assertEquals($domain->getPath(), $this->getUrl(), 'Loaded the active domain.');

    // Disable the domain and test for redirect.
    $domain->disable();
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $default = $storage->loadDefaultDomain();
    // Our postSave() cache tag clear should allow this to work properly.
    $this->drupalGet($domain->getPath());

    $this->assertFalse($domain->status(), 'Tested domain is set to inactive.');
    $this->assertEquals($default->getPath(), $this->getUrl(), 'Redirected an inactive domain to the default domain.');

    // Check to see if the user can login.
    $url = $domain->getPath() . 'user/login';
    $this->drupalGet($url);
    // Check if user has been redirected to another domain.
    $this->assertEquals($url, $this->getUrl(), 'No redirect occurred: requested URL is allowed.');
    // Check to see if the user can reset password.
    $url = $domain->getPath() . 'user/password';
    $this->drupalGet($url);
    // Check if user has been redirected to another domain.
    $this->assertEquals($url, $this->getUrl(), 'No redirect occurred: requested URL is allowed.');

    // Try to access with the proper permission.
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['access inactive domains']);
    // Must flush cache because we did not resave the domain.
    drupal_flush_all_caches();
    $this->assertFalse($domain->status(), 'Tested domain is set to inactive.');
    $this->drupalGet($domain->getPath());

    // Set up two additional domains.
    $domain2 = $domains['one_example_com'];
    $domain3 = $domains['three_example_com'];

    // Check against trusted host patterns.
    $settings = [];
    $settings['settings']['trusted_host_patterns'] = (object) [
      'value' => ['^' . $this->prepareTrustedHostname($domain->getHostname()) . '$',
        '^' . $this->prepareTrustedHostname($domain2->getHostname()) . '$',
      ],
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Revoke the permission change.
    user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, ['access inactive domains']);

    $domain2->saveDefault();

    // Test the trusted host, which should redirect to default.
    $this->drupalGet($domain->getPath());
    $this->assertEquals($domain2->getPath(), $this->getUrl(), 'Redirected from the inactive domain.');

    // The redirect is cached, so we must flush cache to test again.
    drupal_flush_all_caches();

    // Test another inactive domain that is not trusted.
    // Disable the domain and test for redirect.
    $domain3->saveDefault();
    $this->drupalGet($domain->getPath());
    $this->assertSession()->responseContains('The provided host name is not a valid redirect.');
  }

}
