<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config ui for registered paths.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUIRegisteredTest extends WebDriverTestBase {

  use DomainConfigUITrait;
  use DomainConfigUITestTrait;
  use DomainTestTrait;

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain_config_ui_test',
    'domain_config_ui',
    'language',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAdminUser();
    $this->createEditorUser();

    $this->setBaseHostname();
    $this->domainCreateTestDomains(1);

    $this->createLanguage();
  }

  /**
   * Tests that domain-specific configurations are limited to allowed ones.
   */
  public function testRegisteredConfigurations() {
    $session = $this->getSession();
    $page = $session->getPage();
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    // Update configuration for an unregistered path.
    // This update should not create a domain-specific configuration.
    $this->drupalGet('/admin/config/domain_config_ui_test/form_unregistered');
    $assert->waitForButton('Save configuration');
    $field1_value = 'New field 1 value for unregistered';
    $page->fillField('field1', $field1_value);
    $page->pressButton('Save configuration');
    $this->assertTrue($assert->waitForText('The configuration options have been saved'));
    $this->htmlOutput($page->getHtml());

    // Test that the domain-specific configuration has not been created.
    /** @var \Drupal\domain_config\Config\DomainConfigFactoryOverrideInterface $domain_config_factory_override */
    $domain_config_factory_override = \Drupal::service('domain.config_factory_override');
    $config = $domain_config_factory_override->getOverride('example_com', 'domain_config_ui_test_unregistered.settings');

    // Config being new means it was not found in storage.
    $this->assertTrue($config->isNew(), 'The domain-specific configuration has not been created for an unregistered path.');
  }

}
