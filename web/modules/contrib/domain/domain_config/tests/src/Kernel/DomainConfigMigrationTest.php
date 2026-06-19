<?php

namespace Drupal\Tests\domain_config\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\Entity\Domain;
use Drupal\domain_config\Config\DomainConfigCollectionUtils;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 2.x → 3.x flat-name → collection migration.
 *
 * Locks in the contract of the 3.0.x DomainConfigMigration service:
 * legacy `domain.config.{domain_id}.[langcode.]{config_name}` entries
 * are copied into the matching per-domain or per-(domain, langcode)
 * collection and removed from the default storage. Conflicting rows
 * (target collection already populated) are not overwritten and are
 * reported in the conflicts result. The matching
 * `domain_config_ui.settings.overridable_configurations` entries are
 * registered alongside, additively.
 *
 * @group domain_config
 *
 * @coversDefaultClass \Drupal\domain_config\Service\DomainConfigMigration
 */
#[Group('domain_config')]
#[RunTestsInSeparateProcesses]
class DomainConfigMigrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_config',
    'domain_config_ui',
    'language',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installEntitySchema('configurable_language');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'language', 'domain_config_ui']);

    Domain::create([
      'id' => 'one_example_com',
      'hostname' => 'one.example.com',
      'name' => 'One',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ])->save();
    Domain::create([
      'id' => 'two_example_com',
      'hostname' => 'two.example.com',
      'name' => 'Two',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
    ])->save();

    ConfigurableLanguage::create(['id' => 'es', 'label' => 'Spanish'])->save();
    ConfigurableLanguage::create(['id' => 'pt-br', 'label' => 'Brazilian Portuguese'])->save();
  }

  /**
   * Migrating a per-domain legacy entry lands in the per-domain collection.
   *
   * Seeds a single 2.x-style flat name without a langcode, runs the
   * migration, asserts the data ends up in domain.{domain_id}, the legacy
   * entry is removed from the default collection, and the
   * overridable_configurations registry has been updated.
   */
  public function testMigratesPerDomainLegacyEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write('domain.config.one_example_com.system.site', ['name' => 'One Domain']);

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame(['domain.config.one_example_com.system.site'], $results['migrated']);
    $this->assertSame([], $results['conflicts']);
    $this->assertSame([], $results['errors']);

    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(['name' => 'One Domain'], $domain_collection->read('system.site'));
    $this->assertFalse($storage->exists('domain.config.one_example_com.system.site'));

    $this->assertOverridableRegistryContains('system.site', ['one_example_com']);
  }

  /**
   * Migrating a per-(domain, language) legacy entry lands in the right place.
   *
   * Seeds a 2.x-style flat name with a langcode embedded, runs the
   * migration, and asserts the data ends up in
   * domain.{domain_id}.language.{langcode}.
   */
  public function testMigratesPerDomainLanguageLegacyEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write('domain.config.two_example_com.es.system.site', ['name' => 'Dos']);

    $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $language_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('two_example_com', 'es'),
    );
    $this->assertSame(['name' => 'Dos'], $language_collection->read('system.site'));
    $this->assertFalse($storage->exists('domain.config.two_example_com.es.system.site'));

    $this->assertOverridableRegistryContains('system.site', ['two_example_com']);
  }

  /**
   * Hyphenated locales (e.g. pt-br) are recognized by the new splitter.
   *
   * Regression test for the original `[a-z]{2}` regex that silently
   * dropped compound language codes during migration. The migration must
   * support any langcode that exists as a configured ConfigurableLanguage.
   */
  public function testMigratesHyphenatedLangcodeLegacyEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write('domain.config.one_example_com.pt-br.system.site', ['name' => 'One Domain']);

    $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $language_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'),
    );
    $this->assertSame(['name' => 'One Domain'], $language_collection->read('system.site'));
    $this->assertFalse($storage->exists('domain.config.one_example_com.pt-br.system.site'));
  }

  /**
   * A first segment not in installed languages is part of the config name.
   *
   * The installed-languages list is the source of truth for langcode
   * detection: a 2.x site that had overrides for a language since
   * uninstalled has its legacy row treated as a plain (3-segment-base)
   * per-domain entry. The data is preserved -- it lands in the per-
   * domain collection under the original `de.system.site` name. Such
   * an entry is never read at runtime (no real Drupal config has that
   * name), but it is visible in the collection for the admin to clean
   * up if desired. Trade-off accepted to avoid stranding
   * `eca.settings`-style legitimate 3-letter module configs.
   */
  public function testTreatsUninstalledLangcodeFirstSegmentAsConfigName(): void {
    $storage = $this->container->get('config.storage');
    $storage->write('domain.config.one_example_com.de.system.site', ['name' => 'Ein']);

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame(['domain.config.one_example_com.de.system.site'], $results['migrated']);
    $this->assertFalse($storage->exists('domain.config.one_example_com.de.system.site'));
    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(
      ['name' => 'Ein'],
      $domain_collection->read('de.system.site'),
      'A first segment not in installed languages must be carried into the destination as part of the base config name.',
    );
  }

  /**
   * Migrates a 3-letter module-name first segment (e.g. `eca.settings`).
   *
   * `eca`, `seo`, `geo`, `gtm`, `oai` and similar 3-letter Drupal modules
   * produce configurations whose canonical name starts with the module's
   * machine name. The migration must not mistake these for langcodes:
   * the installed-languages list is the only signal we trust. For a
   * 2.x site that had a per-domain override on, say, `eca.settings`,
   * the legacy row must migrate verbatim into the per-domain collection.
   */
  public function testMigratesThreeLetterModuleConfigEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write(
      'domain.config.one_example_com.eca.settings',
      ['log_level' => 'debug'],
    );

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame(['domain.config.one_example_com.eca.settings'], $results['migrated']);
    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(
      ['log_level' => 'debug'],
      $domain_collection->read('eca.settings'),
    );
    $this->assertFalse($storage->exists('domain.config.one_example_com.eca.settings'));
  }

  /**
   * The migration writes the domain_config_ui overridable registry.
   *
   * Unlike the 3.1.x DomainConfigOverrideMigration (which leaves registry
   * sync to a separate UI-side hook), the 3.0.x DomainConfigMigration is
   * the single source for the `overridable_configurations` registry on
   * `domain_config_ui.settings` during the 2.x → 3.0.x upgrade. Migrating
   * a legacy entry must add the
   * matching (config_name, domain_id) pair to that registry, additively
   * over any pre-existing entries.
   */
  public function testWritesOverridableConfigurationsRegistry(): void {
    // Seed a pre-existing registry entry that must be preserved.
    $this->container
      ->get('config.factory')
      ->getEditable('domain_config_ui.settings')
      ->set('overridable_configurations', [
        ['name' => 'system.menu.main', 'domains' => ['two_example_com']],
      ])
      ->save();

    $storage = $this->container->get('config.storage');
    $storage->write('domain.config.one_example_com.system.site', ['name' => 'One Domain']);

    $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertOverridableRegistryContains('system.site', ['one_example_com']);
    $this->assertOverridableRegistryContains('system.menu.main', ['two_example_com']);
  }

  /**
   * The migration never overwrites a live per-domain collection value.
   *
   * Reproduces the post-3.0.0 scenario where the original `[a-z]{2}` /
   * `[^.]+\.[^.]+` regex left some legacy entries stranded; the admin
   * then re-set the same configuration through the UI, populating the
   * per-domain collection. A second migration run with the new splitter
   * must NOT stomp the live value with the stale legacy entry. Instead,
   * the legacy row is reported as a conflict and left on disk.
   */
  public function testDoesNotOverwriteLivePerDomainCollectionEntry(): void {
    $storage = $this->container->get('config.storage');
    // Pre-existing live override (e.g. set via the UI):
    $storage
      ->createCollection(DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'))
      ->write('system.site', ['name' => 'Live UI Value']);
    // Stale 2.x legacy row sitting in the default collection:
    $storage->write('domain.config.one_example_com.system.site', ['name' => 'Stale Legacy Value']);

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame([], $results['migrated']);
    $this->assertSame(['domain.config.one_example_com.system.site'], $results['conflicts']);

    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(
      ['name' => 'Live UI Value'],
      $domain_collection->read('system.site'),
      'The live collection value must survive the migration unchanged.',
    );
    $this->assertTrue(
      $storage->exists('domain.config.one_example_com.system.site'),
      'The conflicting legacy row stays on disk for admin review.',
    );
  }

  /**
   * The migration never overwrites a live per-(domain, language) value.
   *
   * The most common real-world conflict surfaced by the broader splitter:
   * a per-language legacy override using a hyphenated langcode (e.g.
   * `pt-br`) was silently skipped by the original migration, the admin
   * re-overrode the same configuration through the UI in the meantime,
   * and the per-(domain, language) collection now holds the live value.
   * The migration must not stomp it.
   */
  public function testDoesNotOverwriteLivePerDomainLanguageCollectionEntry(): void {
    $storage = $this->container->get('config.storage');
    // Pre-existing live per-(domain, pt-br) override:
    $storage
      ->createCollection(DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'))
      ->write('system.site', ['name' => 'Live UI Value (pt-br)']);
    // Stale 2.x legacy row sitting in the default collection (this row
    // was left behind by the original `[a-z]{2}` migration regex):
    $storage->write('domain.config.one_example_com.pt-br.system.site', ['name' => 'Stale Legacy Value']);

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame([], $results['migrated']);
    $this->assertSame(['domain.config.one_example_com.pt-br.system.site'], $results['conflicts']);

    $language_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'),
    );
    $this->assertSame(
      ['name' => 'Live UI Value (pt-br)'],
      $language_collection->read('system.site'),
      'The live language-aware collection value must survive the migration unchanged.',
    );
    $this->assertTrue(
      $storage->exists('domain.config.one_example_com.pt-br.system.site'),
      'The conflicting legacy row stays on disk for admin review.',
    );
  }

  /**
   * A single migration run can report both migrated and conflicting rows.
   *
   * The realistic upgrade scenario for a site that hit the original
   * narrow regex bug: legacy rows for some configs still need to be
   * copied into their collections (no live value there), while other
   * legacy rows -- typically the hyphenated/3-letter langcode ones the
   * admin re-overrode through the UI -- collide with live values that
   * must be preserved. Both lists must populate correctly in a single
   * pass, the cleanup must delete only the migrated entries, and the
   * conflicting entries must remain on disk for review.
   */
  public function testReportsMigratedAndConflictsInOneRun(): void {
    $storage = $this->container->get('config.storage');
    // Will migrate: the per-domain collection has no entry for
    // `system.site` on `one_example_com` yet.
    $storage->write('domain.config.one_example_com.system.site', ['name' => 'New One']);
    // Will conflict: the per-(domain, pt-br) collection already holds a
    // live value the admin re-set through the UI, the stale legacy row
    // must not stomp it.
    $storage
      ->createCollection(DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'))
      ->write('system.site', ['name' => 'Live UI Value (pt-br)']);
    $storage->write('domain.config.one_example_com.pt-br.system.site', ['name' => 'Stale Legacy Value']);

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame(['domain.config.one_example_com.system.site'], $results['migrated']);
    $this->assertSame(['domain.config.one_example_com.pt-br.system.site'], $results['conflicts']);
    $this->assertSame([], $results['errors']);

    // Migrated row: written into the destination, removed from default.
    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(['name' => 'New One'], $domain_collection->read('system.site'));
    $this->assertFalse($storage->exists('domain.config.one_example_com.system.site'));

    // Conflicting row: live value preserved, legacy row left on disk.
    $language_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'),
    );
    $this->assertSame(['name' => 'Live UI Value (pt-br)'], $language_collection->read('system.site'));
    $this->assertTrue($storage->exists('domain.config.one_example_com.pt-br.system.site'));
  }

  /**
   * Migrates a 3-segment SIMPLE config base name (system.theme.global).
   *
   * `system.theme.global` is one of several core simple configs whose
   * canonical name uses 3 dot-separated segments. Its admin form
   * (`SystemThemeSettingsForm`, served at `/admin/appearance/settings`)
   * extends `ConfigFormBase` and exposes the config via
   * `getEditableConfigNames()`, which means 2.0.x's domain_config_ui
   * "Enable domain configuration" toggle was offered on it like on any
   * other simple-config form. Every multi-domain 2.x site that used the
   * standard UI to override the global theme settings per domain has
   * `domain.config.{domain_id}.system.theme.global` legacy rows on disk
   * -- and the original 3.0.x migration regex silently stranded them.
   * This test pins down that the new splitter rescues them verbatim.
   */
  public function testMigratesThreeSegmentSimpleConfigEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write(
      'domain.config.one_example_com.system.theme.global',
      ['features' => ['logo' => TRUE]],
    );

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame(['domain.config.one_example_com.system.theme.global'], $results['migrated']);
    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(
      ['features' => ['logo' => TRUE]],
      $domain_collection->read('system.theme.global'),
    );
    $this->assertFalse($storage->exists('domain.config.one_example_com.system.theme.global'));
  }

  /**
   * Migrates a 3-segment (config-entity) base name without a langcode.
   *
   * Domain 2.x supported per-domain overrides on any base config name,
   * including entity-config names with 3+ segments such as
   * `block.block.example_block` and `views.view.frontpage`. The 2.x
   * domain_config_ui never offered the toggle on entity forms, so these
   * legacy rows came from manual paths (hand-crafted YAML in
   * config/sync, drush config:set, custom hook_install, etc.). The
   * original 3.0.x migration regex stranded them too. Sibling case to
   * testMigratesThreeSegmentSimpleConfigEntry.
   */
  public function testMigratesEntityConfigPerDomainEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write(
      'domain.config.one_example_com.block.block.example_block',
      ['region' => 'sidebar', 'theme' => 'olivero'],
    );

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame(['domain.config.one_example_com.block.block.example_block'], $results['migrated']);
    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(
      ['region' => 'sidebar', 'theme' => 'olivero'],
      $domain_collection->read('block.block.example_block'),
    );
    $this->assertFalse($storage->exists('domain.config.one_example_com.block.block.example_block'));
  }

  /**
   * Migrates a 3-segment (config-entity) base name WITH a langcode.
   *
   * Same as above, but exercises the langcode peel in front of an
   * entity-config base name. The destination is
   * `domain.{id}.language.{lang}` and the inner entry keeps its full
   * 3-segment name.
   */
  public function testMigratesEntityConfigPerLanguageEntry(): void {
    $storage = $this->container->get('config.storage');
    $storage->write(
      'domain.config.one_example_com.es.block.block.example_block',
      ['region' => 'content', 'theme' => 'olivero'],
    );

    $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $language_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'es'),
    );
    $this->assertSame(
      ['region' => 'content', 'theme' => 'olivero'],
      $language_collection->read('block.block.example_block'),
    );
    $this->assertFalse($storage->exists('domain.config.one_example_com.es.block.block.example_block'));
  }

  /**
   * Migrates a deeply-nested base name (e.g. core.entity_form_display.*).
   *
   * Drupal core ships configuration-entity types whose machine names use
   * 4-5 dot-separated segments
   * (`core.entity_form_display.node.article.default`,
   * `core.entity_view_display.taxonomy_term.tags.default`, …).
   * Per-domain overrides on those have to migrate verbatim.
   */
  public function testMigratesDeeplyNestedBaseName(): void {
    $storage = $this->container->get('config.storage');
    $storage->write(
      'domain.config.two_example_com.core.entity_form_display.node.article.default',
      ['hidden' => ['promote' => TRUE]],
    );

    $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('two_example_com'),
    );
    $this->assertSame(
      ['hidden' => ['promote' => TRUE]],
      $domain_collection->read('core.entity_form_display.node.article.default'),
    );
  }

  /**
   * Module-named first segments (4+ chars) are not mistaken for langcodes.
   *
   * Most Drupal module names are 4+ characters and never overlap with an
   * installed langcode. This test pins that distinction by migrating a
   * `system.site` legacy entry: the `system` segment is not in the
   * installed-languages list and must be carried into the destination
   * as the first segment of the base config name.
   */
  public function testDoesNotMisinterpretModuleNameAsLangcode(): void {
    $storage = $this->container->get('config.storage');
    // `system` is 6 letters -- does not match `[a-z]{2,3}`.
    $storage->write('domain.config.one_example_com.system.site', ['name' => 'One Domain']);

    $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertSame(['name' => 'One Domain'], $domain_collection->read('system.site'));
  }

  /**
   * The conflict guard fires on entity-config names too.
   *
   * Cross-cuts the conflict-aware fix with the deeply-nested-name fix:
   * a live entity-config override sitting in the per-(domain, langcode)
   * collection must be preserved even when a stale legacy 4-segment-base
   * row is matched by the broader splitter.
   */
  public function testConflictGuardCoversEntityConfigOnPerLanguagePath(): void {
    $storage = $this->container->get('config.storage');
    $storage
      ->createCollection(DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'))
      ->write('block.block.example_block', ['region' => 'live_value']);
    $storage->write(
      'domain.config.one_example_com.pt-br.block.block.example_block',
      ['region' => 'stale_legacy_value'],
    );

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame([], $results['migrated']);
    $this->assertSame(['domain.config.one_example_com.pt-br.block.block.example_block'], $results['conflicts']);
    $language_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName('one_example_com', 'pt-br'),
    );
    $this->assertSame(['region' => 'live_value'], $language_collection->read('block.block.example_block'));
    $this->assertTrue($storage->exists('domain.config.one_example_com.pt-br.block.block.example_block'));
  }

  /**
   * Single-segment payloads (no dot in the base name) are skipped.
   *
   * `domain.config.one_example_com.foo` strips down to a single-segment
   * payload `foo`, which is not a valid Drupal config name (Drupal
   * config follows `provider.name[.id...]`). The migration must skip
   * rather than write a malformed entry into the destination.
   */
  public function testSkipsSingleSegmentPayload(): void {
    $storage = $this->container->get('config.storage');
    $storage->write('domain.config.one_example_com.foo', ['x' => 'y']);

    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame([], $results['migrated']);
    $this->assertSame([], $results['conflicts']);
    $domain_collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName('one_example_com'),
    );
    $this->assertFalse($domain_collection->exists('foo'));
  }

  /**
   * Running the migration with no legacy data is a no-op on the storage.
   *
   * Fresh installs and second runs on an already-migrated site must
   * yield zero migrated entries; the only allowed write to the default
   * collection is the no-op save of `domain_config_ui.settings`
   * (the migration always re-saves it, even when the registry is
   * unchanged).
   */
  public function testNoOpWhenThereIsNothingToMigrate(): void {
    $results = $this->container
      ->get('domain_config.config_migration')
      ->migrateConfigurations();

    $this->assertSame([], $results['migrated']);
    $this->assertSame([], $results['conflicts']);
    $this->assertSame([], $results['errors']);
  }

  /**
   * Asserts that the overridable_configurations registry contains a pair.
   *
   * @param string $config_name
   *   The base config name expected in the registry.
   * @param string[] $domain_ids
   *   The domain IDs expected to be associated with $config_name.
   */
  protected function assertOverridableRegistryContains(string $config_name, array $domain_ids): void {
    $registry = $this->container
      ->get('config.factory')
      ->get('domain_config_ui.settings')
      ->get('overridable_configurations') ?? [];
    foreach ($registry as $entry) {
      if ($entry['name'] === $config_name) {
        foreach ($domain_ids as $domain_id) {
          $this->assertContains(
            $domain_id,
            $entry['domains'],
            sprintf('Registry entry for %s must include domain %s.', $config_name, $domain_id),
          );
        }
        return;
      }
    }
    $this->fail(sprintf('Registry has no entry for config name "%s".', $config_name));
  }

}
