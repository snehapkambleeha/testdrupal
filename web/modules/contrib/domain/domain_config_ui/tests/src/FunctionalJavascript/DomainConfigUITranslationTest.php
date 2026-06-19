<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\Core\Language\Language;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config user interface.
 *
 * @group domain_config_ui
 */
#[Group('domain_config_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigUITranslationTest extends WebDriverTestBase {

  use DomainTestTrait;
  use DomainConfigUITestTrait;

  /**
   * Disabled config schema checking.
   *
   * Domain Config actually duplicates schemas provided by other modules,
   * so it cannot define its own.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // phpcs:ignore

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
    'user',
    'domain_config_ui',
    'domain_config_test',
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
  public function testTranslations() {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();

    // Log as admin.
    $this->drupalLogin($this->adminUser);

    // Visit the site information page.
    $path = 'admin/config/system/site-information';
    $this->drupalGet($path);
    $page = $this->getSession()->getPage();
    $page->clickLink('Enable domain configuration');

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

    // Make sure the en version is untouched.
    $config_name = 'system.site';
    $config = \Drupal::configFactory()->get($config_name);

    $this->assertEquals('Drupal', $config->get('name'));

    // Check that the english version uses the original name.
    $this->drupalGet('<front>');
    $assert->responseContains(' | Drupal</title>');

    // Check that the spanish version uses the translated name.
    $language_es = new Language(['id' => 'es']);
    $this->drupalGet('<front>', ['language' => $language_es]);
    $assert->responseContains(' | Neuvo nombre</title>');
  }

}
