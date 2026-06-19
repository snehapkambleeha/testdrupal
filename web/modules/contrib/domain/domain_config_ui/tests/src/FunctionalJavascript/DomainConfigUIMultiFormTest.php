<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config ui with multiple forms.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUIMultiFormTest extends WebDriverTestBase {

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
    $this->domainCreateTestDomains(5);

    $this->createLanguage();
  }

  /**
   * Tests ability to use multiple forms for same config.
   */
  public function testUsingMultipleForms() {
    $session = $this->getSession();
    $page = $session->getPage();
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();

    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $domainConfigUiManager */
    $domainConfigUiManager = \Drupal::service('domain_config_ui.manager');
    $domainConfigUiManager->addConfigurationsToCurrentDomain(['domain_config_ui_test.settings']);

    $this->drupalLogin($this->adminUser);

    // Get the original field1 value from the installation.
    $config = $this->config('domain_config_ui_test.settings');
    $field1_value = $config->get('field1');

    // Test with language and without.
    foreach (['en', 'es'] as $langcode) {
      $prefix = '';
      if ($langcode === 'es') {
        $prefix = '/es';
      }

      // Let's update the field1 configuration field.
      $path = $prefix . '/admin/config/domain_config_ui_test/form1';
      $this->drupalGet($path);
      $this->htmlOutput($page->getHtml());
      // The following check used to fail after a language change.
      $assert->fieldValueEquals('field1', $field1_value);
      $field1_value = 'New field 1 value';
      $page->fillField('field1', $field1_value);
      $page->pressButton('Save configuration');
      $this->assertTrue($assert->waitForText('The configuration options have been saved'));
      $this->htmlOutput($page->getHtml());
      $assert->pageTextContains('Field 1 value: ' . $field1_value);

      // Let's update field2 and then check field1 value after submit.
      $path = $prefix . '/admin/config/domain_config_ui_test/form2';
      $this->drupalGet($path);
      $this->htmlOutput($page->getHtml());
      $page->fillField('field2', '13');
      $page->pressButton('Save configuration');
      $this->assertTrue($assert->waitForText('The configuration options have been saved'));
      $this->htmlOutput($page->getHtml());
      $assert->pageTextContains('Field 1 value: ' . $field1_value);
    }
  }

}
