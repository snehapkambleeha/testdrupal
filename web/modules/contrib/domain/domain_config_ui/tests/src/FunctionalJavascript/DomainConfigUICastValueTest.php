<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config ui for schema types validation.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUICastValueTest extends WebDriverTestBase {

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
  public function testCastValue() {
    $session = $this->getSession();
    $page = $session->getPage();
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();

    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $domainConfigUiManager */
    $domainConfigUiManager = \Drupal::service('domain_config_ui.manager');
    $domainConfigUiManager->addConfigurationsToCurrentDomain(['domain_config_ui_test.settings']);

    $this->drupalLogin($this->adminUser);

    // Test with language and without.
    foreach (['en', 'es'] as $langcode) {
      $prefix = '';
      if ($langcode === 'es') {
        $prefix = '/es';
      }

      $path = $prefix . '/admin/config/domain_config_ui_test/form3';
      $this->drupalGet($path);
      $this->htmlOutput($page->getHtml());
      $page->checkField('field3');
      $page->pressButton('Save configuration');
      $this->assertTrue($assert->waitForText('The configuration options have been saved'));
      $this->htmlOutput($page->getHtml());
      $assert->pageTextContains('Field 3 value: true');
    }
  }

}
