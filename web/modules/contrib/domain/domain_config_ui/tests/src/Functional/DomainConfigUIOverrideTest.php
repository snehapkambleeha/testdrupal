<?php

namespace Drupal\Tests\domain_config_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\domain\Traits\DomainLoginTestTrait;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config\Functional\DomainConfigTestBase;
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
class DomainConfigUIOverrideTest extends DomainConfigTestBase {

  use DomainTestTrait;
  use DomainLoginTestTrait;
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
    'language',
    'user',
    'domain_config_ui',
    'domain_config_test',
    'node',
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
   * Tests saving domain-specific config via the UI.
   *
   * @see https://www.drupal.org/project/domain/issues/3575434
   */
  public function testOverrides() {
    /** @var \Drupal\domain_config\Config\DomainConfigFactoryOverrideInterface $domain_config_factory_override */
    $domain_config_factory_override = \Drupal::service('domain.config_factory_override');
    /** @var \Drupal\domain_config\Config\DomainLanguageConfigFactoryOverrideInterface $domain_language_config_factory_override */
    $domain_language_config_factory_override = \Drupal::service('domain.language.config_factory_override');

    // Verify base configuration.
    $config = \Drupal::configFactory()->get('system.site')->getRawData();
    $this->assertEquals('Drupal', $config['name']);
    $this->assertEquals('/node', $config['page']['front']);

    // Verify the installed domain and language overrides for
    // one_example_com (provided by domain_config_test).
    $config = $domain_config_factory_override->getOverride('one_example_com', 'system.site');
    $config_en = $domain_language_config_factory_override->getDomainOverride('one_example_com', 'en', 'system.site');
    $this->assertEquals('/node/1', $config->get('page.front'));
    $this->assertEquals('One', $config_en->get('name'));

    // Log in on the one_example_com domain and verify the front page
    // shows the node provided by the domain override.
    $node = $this->createNode();
    $domains = $this->getDomains();
    $one = $domains['one_example_com'];
    $this->drupalLoginOnHost($this->adminUser, rtrim($one->getPath(), '/'));
    $frontpage = Url::fromRoute('<front>', options: ['domain' => $one]);
    $this->drupalGet($frontpage);
    $this->assertSession()->pageTextContains($node->getTitle());

    // Enable domain configuration on the site information form.
    $path = $one->getPath() . 'admin/config/system/site-information';
    $this->drupalGet($path);
    $this->getSession()->getPage()->clickLink('Enable domain configuration');

    // The domain override needs a langcode, otherwise the form submit
    // triggers a type mismatch warning during schema validation.
    $config = $domain_config_factory_override->getOverride('one_example_com', 'system.site');
    $config->set('langcode', 'en')->save();

    // Change the domain front page to /user.
    $this->drupalGet($path);
    $this->submitForm([
      'site_name' => 'New name',
      'site_frontpage' => '/user',
    ], 'Save configuration');

    // Verify the override values were saved correctly.
    $config = $domain_config_factory_override->getOverride('one_example_com', 'system.site');
    $this->assertEquals('New name', $config->get('name'));
    $this->assertEquals('/user', $config->get('page.front'));

    // Verify the new front page takes effect immediately without a
    // manual cache rebuild (regression test for #3575434).
    $this->drupalGet($frontpage);
    $this->assertSession()->pageTextNotContains($node->getTitle());
  }

}
