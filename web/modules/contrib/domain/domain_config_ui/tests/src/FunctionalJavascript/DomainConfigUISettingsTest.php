<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;
use Drupal\domain_config_ui\Form\SettingsForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config settings interface.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUISettingsTest extends WebDriverTestBase {

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
   * Tests ability to add/remove forms.
   */
  public function testSettings() {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $config = $this->config('domain_config_ui.settings');
    $value = $config->get('overridable_configurations');
    // The default configuration is empty as it requires pre-existing domains.
    $this->assertNull($value, 'The default configuration is empty.');

    // Test with language and without.
    foreach (['en', 'es'] as $langcode) {
      $config->save();
      $prefix = '';
      if ($langcode === 'es') {
        $prefix = '/es';
      }
      $this->drupalLogin($this->adminUser);
      // Test site information configuration page.
      $path = $prefix . '/admin/config/system/site-information';
      $this->drupalGet($path);
      $assert->linkExists('Enable domain configuration');
      $page->clickLink('Enable domain configuration');
      $assert->waitForLink('Remove domain configuration');

      // Test some theme paths.
      $path = $prefix . '/admin/appearance';
      $this->drupalGet($path);
      $assert->linkExists('Enable domain configuration');
      $page->clickLink('Enable domain configuration');
      $assert->waitForLink('Remove domain configuration');

      $path = $prefix . '/admin/appearance/settings/stark';
      $this->drupalGet($path);
      $page->clickLink('Enable domain configuration');
      $assert->waitForLink('Remove domain configuration');

      $config2 = $this->config('domain_config_ui.settings');
      $expected2 = SettingsForm::buildOverridableConfigurationFromText(
        'system.site: example_com' . "\r\n" .
        'system.theme: example_com' . "\r\n" .
        'stark.settings: example_com'
      );
      $value2 = $config2->get('overridable_configurations');
      $this->assertEquals($expected2, $value2);

      // Test removal of paths.
      $this->drupalGet($path);
      $page->clickLink('Remove domain configuration');
      $assert->waitForButton('Delete configuration');
      $page->pressButton('Delete configuration');
      $this->assertNotNull($assert->waitForLink('Enable domain configuration'));

      $path = $prefix . '/admin/config/system/site-information';
      $this->drupalGet($path);
      $page->clickLink('Remove domain configuration');
      $assert->waitForButton('Delete configuration');
      $page->pressButton('Delete configuration');
      $this->assertNotNull($assert->waitForLink('Enable domain configuration'));

      $expected3 = SettingsForm::buildOverridableConfigurationFromText(
        'system.theme: example_com'
      );
      $config3 = $this->config('domain_config_ui.settings');
      // array_values() required to reset the array indexes as Drupal preserves
      // them after delete.
      $value3 = array_values($config3->get('overridable_configurations'));
      $this->assertEquals($expected3, $value3);

      $this->drupalGet($path);
      $this->assertNotNull($assert->waitForLink('Enable domain configuration'));

      // Ensure the editor cannot access the form.
      $this->drupalLogin($this->editorUser);
      $this->drupalGet($path);
      $assert->pageTextNotContains('Enable domain configuration');
    }
  }

}
