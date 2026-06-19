<?php

namespace Drupal\domain_config\Service;

/**
 * @file
 * Migration path from Domain module 2.x to 3.x configuration collections.
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_config\Config\DomainConfigCollectionUtils;

/**
 * Service class for migrating Domain 2.x configurations to 3.x collections.
 */
class DomainConfigMigration {

  /**
   * The list of domains.
   *
   * @var \Drupal\domain\DomainInterface[]
   */
  protected $domains;

  /**
   * The list of languages.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * The default language.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $defaultLanguage;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StorageInterface $configStorage,
    protected TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
  ) {
    $domain_storage = $entity_type_manager->getStorage('domain');
    $this->domains = $domain_storage->loadMultiple();
    $this->languages = $language_manager->getLanguages();
    $this->defaultLanguage = $language_manager->getDefaultLanguage();
  }

  /**
   * Migrate domain configurations from 2.x to 3.x format.
   *
   * @return array
   *   Migration results:
   *   - migrated: legacy config names successfully copied into a
   *     collection (and removed from the default storage afterwards).
   *   - conflicts: legacy config names whose target slot in the
   *     destination collection is already populated. The migration
   *     never overwrites a live collection value with a stale 2.x
   *     entry; the legacy row is left on disk for the administrator
   *     to compare and resolve.
   *   - failed: number of domains whose migration raised an exception.
   *   - errors: human-readable per-domain error messages.
   */
  public function migrateConfigurations(): array {
    $results = [
      'migrated' => [],
      'conflicts' => [],
      'failed' => 0,
      'errors' => [],
    ];

    $config = $this->configFactory->getEditable('domain_config_ui.settings');
    $overridable_configurations = $config->get('overridable_configurations') ?? [];

    $overridable_configurations_map = $this->toConfigurationMap($overridable_configurations);

    foreach ($this->domains as $domain) {
      try {
        $this->migrateDomainConfiguration(
          $domain, $overridable_configurations_map, $results,
        );
      }
      catch (\Exception $e) {
        $results['failed']++;
        $results['errors'][] = sprintf(
          'Failed to migrate domain %s: %s',
          $domain['machine_name'],
          $e->getMessage()
        );
      }
    }

    // Clean up migrated legacy configuration files. Conflicting rows are
    // intentionally left on disk so the admin can compare the legacy
    // payload against the live collection value.
    $this->cleanupLegacyConfigurations($results['migrated']);

    $overridable_configurations =
      $this->fromConfigurationMap($overridable_configurations_map);

    $config->set('overridable_configurations', $overridable_configurations);

    // Save updated configuration.
    $config->save();

    return $results;
  }

  /**
   * Migrate a single domain's configuration overrides to collection format.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain entity.
   * @param array $overridable_configurations
   *   The overridable_configurations registry, mutated in place.
   * @param array $results
   *   The migration results, mutated in place.
   */
  protected function migrateDomainConfiguration(
    DomainInterface $domain,
    array &$overridable_configurations,
    array &$results,
  ): void {
    $domain_id = $domain->id();

    // Get legacy configuration overrides for this domain.
    $legacy_overrides = $this->configStorage->listAll("domain.config.$domain_id.");
    if (empty($legacy_overrides)) {
      return;
    }

    // Per-domain destination collection.
    $domain_collection = $this->configStorage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName($domain_id)
    );
    // Per-(domain, language) destination collections, lazily created.
    $language_collections = [];

    // Strip the `domain.config.{domain_id}.` prefix; everything after is the
    // payload of the legacy entry. Splitting payload into (langcode,
    // config_name) is left to the loop body below: parsing that with a
    // regex alone is unreliable because (a) Drupal config-entity names have
    // 3+ dot-separated segments (e.g. `block.block.example_block`,
    // `views.view.frontpage`), so a fixed `[^.]+\.[^.]+` tail wrongly drops
    // them; (b) langcodes can be 2-letter, 3-letter, or hyphenated
    // (`pt-br`, `zh-hans`); (c) the only reliable way to tell apart
    // "first segment is a langcode" and "first segment is part of the
    // config name" is the actual installed-languages list.
    $prefix = "domain.config.$domain_id.";
    $prefix_length = strlen($prefix);

    foreach ($legacy_overrides as $legacy_name) {
      if (!str_starts_with($legacy_name, $prefix)) {
        continue;
      }
      $payload = substr($legacy_name, $prefix_length);

      // Decide whether the payload starts with a langcode segment.
      //
      // The installed-languages list is the source of truth: a langcode-
      // shape regex would mis-handle 3-letter module names (`eca`, `seo`,
      // `geo`, `gtm`, …) whose configurations are perfectly valid first
      // segments of a base config name. If the first segment matches an
      // installed language we treat it as a langcode prefix; otherwise
      // it is just the first segment of the base config name.
      //
      // Trade-off: a 2.x site that had per-domain overrides for a
      // language since uninstalled lands those legacy rows in the per-
      // domain collection under their original (now invalid) name --
      // the data is preserved but never read at runtime, and the admin
      // can clean up by hand. We accept that over the alternative of
      // silently stranding `eca.settings`-style legacy rows.
      $first_dot = strpos($payload, '.');
      if ($first_dot === FALSE) {
        // Single-segment payload: not a valid Drupal config name.
        continue;
      }
      $first_segment = substr($payload, 0, $first_dot);
      if (isset($this->languages[$first_segment])) {
        $langcode = $first_segment;
        $config_name = substr($payload, $first_dot + 1);
      }
      else {
        $langcode = NULL;
        $config_name = $payload;
      }
      // The base config name must itself have at least one dot. Drupal
      // config names follow `provider.name[.id...]`; a single segment
      // here would mean we either captured a stray entry or mis-split a
      // langcode-prefixed single-segment name. Skip rather than write an
      // invalid entry into the destination collection.
      if (!str_contains($config_name, '.')) {
        continue;
      }

      $config_data = $this->configStorage->read($legacy_name);
      if (!$config_data) {
        continue;
      }

      if ($langcode === NULL) {
        if ($domain_collection->exists($config_name)) {
          // The destination already holds a value -- typically because
          // the admin has overridden this configuration through the UI
          // in the meantime. NEVER stomp a live collection value with a
          // stale 2.x entry; flag it as a conflict and leave the legacy
          // row in place so the admin can compare and resolve. The
          // (config_name, domain_id) pair still has an active override
          // in the collection, so register it.
          $overridable_configurations[$config_name][$domain_id] = $domain_id;
          $results['conflicts'][] = $legacy_name;
          continue;
        }
        $overridable_configurations[$config_name][$domain_id] = $domain_id;
        $domain_collection->write($config_name, $config_data);
        $results['migrated'][] = $legacy_name;
        continue;
      }
      $language_collections[$langcode] ??= $this->configStorage->createCollection(
        DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName(
          $domain_id, $langcode,
        )
      );
      if ($language_collections[$langcode]->exists($config_name)) {
        // Same conflict guard as the per-domain branch above. The most
        // common cause here is the previous `[a-z]{2}` regex bug:
        // legacy hyphenated/3-letter langcode entries were skipped, the
        // admin re-overrode through the UI, and the legacy row's stale
        // value would now stomp the live one if we let the write
        // through.
        $overridable_configurations[$config_name][$domain_id] = $domain_id;
        $results['conflicts'][] = $legacy_name;
        continue;
      }
      $overridable_configurations[$config_name][$domain_id] = $domain_id;
      $language_collections[$langcode]->write($config_name, $config_data);
      $results['migrated'][] = $legacy_name;
    }
  }

  /**
   * Removes legacy domain configuration overrides after migration.
   *
   * @param array $legacy_names
   *   The list of legacy configuration overrides to delete.
   *
   * @return int
   *   The number of legacy configuration overrides deleted.
   */
  protected function cleanupLegacyConfigurations(array $legacy_names): int {
    // Clean up legacy domain config overrides.
    $total_deleted = 0;
    foreach ($legacy_names as $legacy_name) {
      if ($this->configStorage->delete($legacy_name)) {
        $total_deleted += 1;
      }
    }
    return $total_deleted;
  }

  /**
   * Removes legacy domain configuration overrides after migration.
   *
   * @return int
   *   The number of legacy configuration overrides deleted.
   */
  public function cleanupAllLegacyConfigurations(): int {
    $legacy_names = $this->configStorage->listAll('domain.config.');
    return $this->cleanupLegacyConfigurations($legacy_names);
  }

  /**
   * Convert the configuration array to a map.
   */
  private function toConfigurationMap(array $configurations): array {
    $map = [];
    foreach ($configurations as $configuration) {
      $map[$configuration['name']] =
        array_combine($configuration['domains'], $configuration['domains']);
    }
    return $map;
  }

  /**
   * Convert the configuration map to an array.
   */
  private function fromConfigurationMap(array $configurations): array {
    $array = [];
    foreach ($configurations as $name => $domains) {
      $array[] = [
        'name' => $name,
        'domains' => array_keys($domains),
      ];
    }
    return $array;
  }

}
