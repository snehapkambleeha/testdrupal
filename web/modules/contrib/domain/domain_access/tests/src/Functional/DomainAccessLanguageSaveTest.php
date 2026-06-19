<?php

namespace Drupal\Tests\domain_access\Functional;

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests saving the domain access field elements in multiple languages.
 *
 * @group domain_access
 */
#[Group('domain_access')]
#[RunTestsInSeparateProcesses]
class DomainAccessLanguageSaveTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_access',
    'field',
    'user',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure node_access table is clear.
    Database::getConnection()->delete('node_access')->execute();

    // Create 5 domains.
    $this->domainCreateTestDomains(5);

    // Add Hungarian and Afrikaans.
    ConfigurableLanguage::createFromLangcode('hu')->save();
    ConfigurableLanguage::createFromLangcode('af')->save();

    // Enable content translation for the current entity type.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
  }

  /**
   * Basic test setup.
   */
  public function testDomainAccessSave() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    // Save a node programmatically.
    $node = $storage->create([
      'type' => 'article',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 1,
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => ['example_com'],
      DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD => 1,
    ]);
    $node->save();

    // Load the node.
    $node = $storage->load(1);

    // Check that two values are set properly.
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(1, $values, 'Node saved with one domain records.');
    $value = DomainAccessManager::getAllValue($node);
    $this->assertTrue($value, 'Node saved to all affiliates.');

    // Create an Afrikaans translation assigned to domain 2.
    $translation = $node->addTranslation('af');
    $translation->set('title', $this->randomString());
    $translation->set(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD, [
      'example_com',
      'one_example_com',
    ]);
    $translation->set(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD, 0);
    $translation->set('status', 1);
    $node->save();

    // Load and check the translated node.
    $parent_node = $storage->load(1);
    $node = $parent_node->getTranslation('af');
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(2, $values, 'Node saved with two domain records.');
    $value = DomainAccessManager::getAllValue($node);
    $this->assertFalse($value, 'Node not saved to all affiliates.');
  }

  /**
   * Tests saving domain access fields when creating translations.
   *
   * This test verifies that domain access fields are handled correctly
   * when a node is translated, including the assignment of domains
   * and the behavior of untranslatable and translatable fields.
   *
   * This test also verifies that the access static cache properly
   * handles node translations and is cleared when a node is saved.
   *
   * @see https://www.drupal.org/project/domain/issues/3547422
   */
  public function testDomainAccessSaveTranslation() {

    // Get the 5 created domains.
    $domains = $this->getDomains();
    $default = $domains['example_com'];
    $one = $domains['one_example_com'];
    $two = $domains['two_example_com'];
    $three = $domains['three_example_com'];
    $four = $domains['four_example_com'];

    // Create editor role.
    $editorRole = $this->drupalCreateRole([
      'publish to any assigned domain',
      'create domain content',
      'edit domain content',
      'delete domain content',
      'edit any page content',
      'create page content',
      'create content translations',
      'delete content translations',
      'update content translations',
      'translate editable entities',
    ]);

    $editor1 = $this->createUser();
    $editor1->addRole($editorRole);
    $editor1->save();
    $this->addDomainsToEntity('user', $editor1->id(), ['one_example_com', 'two_example_com'], DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    $editor2 = $this->createUser();
    $editor2->addRole($editorRole);
    $editor2->save();
    $this->addDomainsToEntity('user', $editor2->id(), ['two_example_com'], DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    $editor3 = $this->createUser();
    $editor3->addRole($editorRole);
    $editor3->save();
    $this->addDomainsToEntity('user', $editor3->id(), ['two_example_com', 'three_example_com'], DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    // Make the field untranslatable for now.
    FieldConfig::loadByName('node', 'page', DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)
      ->set('translatable', FALSE)
      ->save();

    // Hide the untranslatable fields in edit form.
    $contentLanguageSettings = ContentLanguageSettings::loadByEntityTypeBundle('node', 'page');
    $settings = $contentLanguageSettings->getThirdPartySetting('content_translation', 'bundle_settings');
    $settings['untranslatable_fields_hide'] = '1';
    $contentLanguageSettings->setThirdPartySetting('content_translation', 'bundle_settings', $settings);
    $contentLanguageSettings->save();

    $storage = \Drupal::entityTypeManager()->getStorage('node');

    // Save a node programmatically, assign it to the default domain.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create([
      'type' => 'page',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 1,
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [$default->id()],
      DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD => 0,
    ]);
    $node->save();

    // Check that the node is only available on the default domain.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    // The newly created node should not be available on another domain.
    $this->drupalGet($one->getPath() . 'node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Assign the node to the one_example_com domain.
    $node->set(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD, [$default->id(), $one->id()]);
    $node->save();

    // Check that the node is now available on the one_example_com domain.
    $this->drupalGet($one->getPath() . 'node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Log as Editor1 and make the node also available on two_example_com.
    $this->drupalLogin($editor1);
    $this->drupalGet($node->toUrl('edit-form'));
    $edit = [
      "field_domain_access[{$two->id()}]" => $two->id(),
    ];
    $this->submitForm($edit, 'Save');

    // Reload the node from the database to reflect remote changes.
    $storage->resetCache([$node->id()]);
    $node = $storage->load($node->id());
    // Check that the node is now available on 3 domains (default, one, two).
    $this->assertEquals(3, $node->get(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)->count());
    // We should still have the original 2 values in the local static cache.
    // They were added into the cache by the $node->save() call above.
    $this->assertCount(2, DomainAccessManager::getAccessValues($node));
    // Clear the local static cache to reflect the remote changes.
    DomainAccessManager::clearStaticCache();
    // Check that the node is now affiliated to 3 domains (default, one, two).
    $this->assertCount(3, DomainAccessManager::getAccessValues($node));

    // Login with editor2 and create a "hu" translation.
    $this->drupalLogin($editor2);
    $this->drupalGet(Url::fromRoute('entity.node.content_translation_overview', ['node' => $node->id()]));
    $this->drupalGet('hu/node/' . $node->id() . '/translations/add/en/hu');
    $this->submitForm(['title[0][value]' => 'Test node (hu)'], 'Save');
    $this->assertSession()->addressEquals('hu/node/1');

    // Reload the node from the database so the newly created translation is
    // available locally.
    $storage->resetCache([$node->id()]);
    $node = $storage->load($node->id());
    $node_translation = $node->getTranslation('hu');
    // Check that the translation has inherited its affiliations from the
    // default language version.
    $this->assertEquals(3, $node_translation->get(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)->count());
    // Let's put domain access values into the local static cache,
    // so we can check that a purge is needed later.
    $this->assertCount(3, DomainAccessManager::getAccessValues($node_translation));

    // Re-enable translation for the field_domain_access field.
    FieldConfig::loadByName('node', 'page', DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)
      ->set('translatable', TRUE)
      ->save();

    // Make the "hu" translation only available on domain three_example.com.
    $this->drupalLogin($editor3);
    $this->drupalGet('hu/node/' . $node->id() . '/edit');
    $edit = [
      "field_domain_access[{$three->id()}]" => $three->id(),
    ];
    $this->submitForm($edit, 'Save');

    // Logout, switch to the anonymous user.
    $this->drupalLogout();

    // Reload the node from the database to get the updated translation.
    $storage->resetCache([$node->id()]);
    $node = $storage->load($node->id());
    $node_translation = $node->getTranslation('hu');
    // Check that the translation has been properly updated.
    // As the field has been made translatable after the translation was
    // created, we are not inheriting from the 3 original values.
    $this->assertEquals(1, $node_translation->get(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)->count());
    // The local static cache hasn't been cleared yet, so this translation
    // should still be affiliated to the 3 original domains.
    $this->assertCount(3, DomainAccessManager::getAccessValues($node_translation));
    // The non-translated node should still be affiliated to 3 domains.
    $this->assertCount(3, DomainAccessManager::getAccessValues($node));
    // A node save should trigger the presave hook that clears the static cache.
    // We could also directly call DomainAccessManager::clearStaticCache().
    $node_translation->save();
    // The local static cache has been cleared, so our translation should now
    // be affiliated to 1 domain (three_example_com).
    $this->assertCount(1, DomainAccessManager::getAccessValues($node_translation));
    // The non-translated node should still be affiliated to 3 domains.
    $this->assertCount(3, DomainAccessManager::getAccessValues($node));

    // Translation should be available on domain three now.
    $this->drupalGet($three->getPath() . '/hu/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Translation should not be available on domain one.
    $this->drupalGet($one->getPath() . '/hu/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Translation should not be available on domain two.
    $this->drupalGet($two->getPath() . '/hu/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Translation should not be available on domain four,
    // as the node was never assigned to this domain.
    $this->drupalGet($four->getPath() . '/hu/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Check that the original node is available on the default domain.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Check that the original node is available on one_example_com.
    $this->drupalGet($one->getPath() . 'node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Check that the original node is available on two_example_com.
    $this->drupalGet($two->getPath() . 'node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Check that the original node is not available on four_example_com,
    // as the node was never assigned to this domain.
    $this->drupalGet($four->getPath() . 'node/' . $node->id());
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

  }

}
