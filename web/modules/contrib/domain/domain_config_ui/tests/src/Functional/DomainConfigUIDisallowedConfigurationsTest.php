<?php

namespace Drupal\Tests\domain_config_ui\Functional;

use Drupal\Tests\domain\Traits\DomainLoginTestTrait;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config\Functional\DomainConfigTestBase;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that disallowed configurations hide the enable toggle on forms.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUIDisallowedConfigurationsTest extends DomainConfigTestBase {

  use DomainTestTrait;
  use DomainLoginTestTrait;
  use DomainConfigUITestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'user',
    'domain_config_ui',
  ];

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAdminUser();

    $this->setBaseHostname();
    $this->domainCreateTestDomains(5);
  }

  /**
   * Verifies that disallowed_configurations hides the toggle button.
   */
  public function testDisallowedConfigurationsHideToggle(): void {
    // Use a specific domain to ensure an active domain context.
    $domains = $this->getDomains();
    $one = $domains['one_example_com'];

    // Login as an admin user on the domain host.
    $this->drupalLoginOnHost($this->adminUser, rtrim($one->getPath(), '/'));

    // Visit the Site Information form (system.site configuration form) and
    // verify the button is initially visible.
    $path = $one->getPath() . 'admin/config/system/site-information';
    $this->drupalGet($path);
    $this->assertSession()->linkExists('Enable domain configuration');

    // Disallow overrides for the system.site configuration object.
    $this->config('domain_config_ui.settings')
      ->set('disallowed_configurations', ['system.site'])
      ->save();

    // The Enable domain configuration link should NOT be present when
    // the configuration name is disallowed.
    $this->drupalGet($path);
    $this->assertSession()->linkNotExists('Enable domain configuration');
  }

}
