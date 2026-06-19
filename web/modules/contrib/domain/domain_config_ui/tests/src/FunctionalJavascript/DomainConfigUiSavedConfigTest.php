<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\Core\Language\Language;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config inspector.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUiSavedConfigTest extends WebDriverTestBase {

  use DomainTestTrait;
  use DomainConfigUITestTrait;

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
    'config_translation',
    'language',
    'domain_config_ui',
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
   * Tests that we can save domain and language-specific settings.
   */
  public function testSavedConfig() {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->adminUser);

    // Visit the site information page.
    $this->drupalGet('/admin/config/system/site-information');
    $page->clickLink('Enable domain configuration');

    $page->fillField('site_name', 'New name');
    $page->fillField('site_frontpage', '/user');
    $page->pressButton('Save configuration');
    $assert->waitForText('The configuration options have been saved.');

    // Now let's save a language.
    // Visit the site information translation page.
    $this->drupalGet('/admin/config/system/site-information/translate/es/add');
    $edit = [
      'translation[config_names][system.site][name]' => 'Neuvo nombre',
    ];
    $this->submitForm($edit, 'Save translation');
    $assert->waitForText('Successfully saved Spanish translation.');

    /** @var \Drupal\domain_config\Config\DomainLanguageConfigFactoryOverrideInterface $domain_language_config_factory_override */
    $domain_language_config_factory_override = \Drupal::service('domain.language.config_factory_override');
    $config = $domain_language_config_factory_override->getDomainOverride('example_com', 'es', 'system.site');
    $this->assertEquals('Neuvo nombre', $config->get('name'), 'Name properly translated to spanish.');

    // Check that the base config value was not touched. Read it via
    // getOriginal() with $apply_overrides = FALSE so the domain override
    // saved above is bypassed.
    $config_name = 'system.site';
    $config = \Drupal::configFactory()->get($config_name);

    $this->assertEquals('Drupal', $config->getOriginal('name', FALSE));

    // Check that the English version uses the overridden name.
    $this->drupalGet('<front>');
    $assert->responseContains(' | New name</title>');

    // Check that the Spanish version uses the translated name.
    $language_es = new Language(['id' => 'es']);
    $this->drupalGet('<front>', ['language' => $language_es]);
    $assert->responseContains(' | Neuvo nombre</title>');

    // Now, head to /admin/config/domain/config-ui/list.
    $this->drupalGet('/admin/config/domain/config-ui/list');
    $assert->pageTextContains('Saved configuration');
    $assert->pageTextContains('example_com');
    $assert->pageTextContains('system.site');
    $assert->pageTextContains('Spanish');
    $assert->pageTextNotContains('English');

    $page->clickLink('Inspect');
    $assert->pageTextContains('system.site This configuration is for the Example domain.');
    $assert->pageTextContains('New name');

    $this->drupalGet('/admin/config/system/site-information/translate/es/edit');
    $assert->fieldValueEquals('translation[config_names][system.site][name]', 'Neuvo nombre');

    // Now, head to /admin/config/domain/config-ui/list to delete the config.
    $this->drupalGet('/admin/config/domain/config-ui/list');

    // Check we have the expected URL for the delete link.
    $delete_link = $assert->waitForLink('Delete');
    $this->assertNotNull($delete_link);
    $delete_path = '/admin/config/domain/config_ui/delete/example_com/system.site';
    $destination_query = '?destination=' . base_path() . 'admin/config/domain/config-ui/list';
    $expected_delete_href = base_path() . ltrim($delete_path, '/') . $destination_query;
    $this->assertEquals($expected_delete_href, $delete_link->getAttribute('href'));

    // Open the action menu popup.
    $toggle = $assert->elementExists('css', '.dropbutton-toggle button');
    $toggle->click();
    // Click the delete link.
    $delete_link->click();

    // Check that the confirmation page is properly displayed.
    $delete_confirm_message = 'Are you sure you want to delete the configuration override(s) system.site for the domain Example as well as the associated translations?';
    $this->assertTrue($assert->waitForText($delete_confirm_message));

    // Test the delete URL directly.
    $this->drupalGet($delete_path);
    $assert->pageTextContains($delete_confirm_message);

    // Confirm configuration delete.
    $delete_button = $page->findButton('Delete configuration');
    $this->assertNotNull($delete_button);
    $delete_button->press();

    // Check that the saved configuration list is now empty.
    $this->drupalGet('/admin/config/domain/config-ui/list');
    $assert->pageTextContains('Saved configuration');
    $assert->pageTextContains('No domain-specific configurations have been found.');
    $assert->pageTextNotContains('Warning:');
  }

}
