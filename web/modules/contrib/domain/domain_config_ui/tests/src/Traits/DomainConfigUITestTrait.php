<?php

namespace Drupal\Tests\domain_config_ui\Traits;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Contains helper classes for tests to set up various configuration.
 */
trait DomainConfigUITestTrait {

  /**
   * A user with full permissions to use the module.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * A user with access administration but not this module.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $editorUser;

  /**
   * A user with access to domains but not language.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $limitedUser;

  /**
   * A user with permission to domains and language.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $languageUser;

  /**
   * Create an admin user.
   */
  public function createAdminUser() {
    $permissions = [
      'access administration pages',
      'access content',
      'administer domains',
      'administer domain config ui',
      'administer site configuration',
      'administer languages',
      'administer themes',
      'set default domain configuration',
      'translate domain configuration',
      'use domain config ui',
      'view domain information',
    ];
    if (\Drupal::moduleHandler()->moduleExists('config_translation')) {
      $permissions[] = 'translate configuration';
    }
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Create an editor user.
   */
  public function createEditorUser() {
    $this->editorUser = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'administer site configuration',
      'administer languages',
    ]);
  }

  /**
   * Create a limited admin user.
   */
  public function createLimitedUser() {
    $this->limitedUser = $this->drupalCreateUser([
      'access administration pages',
      'administer languages',
      'administer site configuration',
      'use domain config ui',
      'set default domain configuration',
    ]);
  }

  /**
   * Create a language administrator.
   */
  public function createLanguageUser() {
    $this->languageUser = $this->drupalCreateUser([
      'access administration pages',
      'use domain config ui',
      'translate domain configuration',
      'administer site configuration',
    ]);
  }

  /**
   * Creates a second language for testing overrides.
   */
  protected function createLanguage() {
    // Create language entity directly.
    if (!ConfigurableLanguage::load('es')) {
      ConfigurableLanguage::create([
        'id' => 'es',
        'label' => 'Spanish',
      ])->save();
    }

    // Enable URL language detection.
    $config = $this->config('language.types');
    $negotiation = $config->get('negotiation') ?? [];
    $negotiation['language_interface']['enabled']['language-url'] = TRUE;
    $config->set('negotiation', $negotiation)->save();

    // Rebuild the container so the changes take effect.
    $this->rebuildContainer();

    // Assert language exists.
    $es = \Drupal::entityTypeManager()->getStorage('configurable_language')->load('es');
    $this->assertNotNull($es, 'Created test language.');
  }

  /**
   * Waits for an AJAX call sensitive to the test domain.
   *
   * @param string $key
   *   The key to search in the window location.
   * @param string $value
   *   The value to search in the window location.
   */
  public function waitOnAjaxRequest($key, $value) {
    $string = "$key=$value";
    // Finesse value here.
    $this->getSession()->wait(10000, 'window.location.search.includes("' . $string . '")');
  }

}
