<?php

/**
 * @file
 * Tugboat setup script for the Domain module.
 *
 * Called via: vendor/bin/drush php:script $TUGBOAT_ROOT/.tugboat/setup.php.
 */

$preview_name = getenv('TUGBOAT_PREVIEW_NAME') ?: 'Tugboat';

// Enable all Domain submodules and admin toolbar.
\Drupal::service('module_installer')->install([
  'domain',
  'domain_access',
  'domain_alias',
  'domain_config',
  'domain_config_ui',
  'domain_content',
  'domain_source',
  'admin_toolbar',
]);

// Enable path prefix support.
\Drupal::configFactory()
  ->getEditable('domain.settings')
  ->set('path_prefix', TRUE)
  ->save();

$domain_storage = \Drupal::entityTypeManager()->getStorage('domain');

// Create the default domain.
$default_domain = $domain_storage->create([
  'id' => 'domain_drupal_org',
  'hostname' => 'domain.drupal.org',
  'name' => $preview_name,
  'scheme' => 'https',
  'is_default' => TRUE,
]);
$default_domain->save();

// Create a domain with a path prefix.
$prefixed_domain = $domain_storage->create([
  'id' => 'domain_drupal_org_prefix',
  'hostname' => 'domain.drupal.org',
  'name' => "$preview_name (Prefixed)",
  'scheme' => 'https',
  'path_prefix' => 'prefix',
]);
$prefixed_domain->save();

// Add a wildcard alias. Use a non-default environment ("testing") so
// that domain_alias rewrites hostnames to the actual Tugboat preview URL;
// the "default" environment skips hostname rewriting.
$alias_storage = \Drupal::entityTypeManager()->getStorage('domain_alias');
$alias = $alias_storage->create([
  'id' => 'star_tugboatqa_com',
  'domain_id' => 'domain_drupal_org',
  'pattern' => '*.tugboatqa.com',
  'redirect' => 0,
  'environment' => 'testing',
]);
$alias->save();

// Set a different Olivero primary color for the prefixed domain via
// Domain Config override (demonstrates per-domain configuration).
// Register olivero.settings as overridable first, then write the override.
$config_ui_manager = \Drupal::service('domain_config_ui.manager');
$config_ui_manager->addConfigurationsToDomain(
  'domain_drupal_org_prefix',
  ['olivero.settings']
);

$collection = 'domain.domain_drupal_org_prefix';
$config_storage = \Drupal::service('config.storage');
$collection_storage = $config_storage->createCollection($collection);
$collection_storage->write('olivero.settings', [
  'base_primary_color' => '#43a047',
]);

// Place Domain blocks in Olivero's sidebar.
$block_storage = \Drupal::entityTypeManager()->getStorage('block');

$nav_block = $block_storage->create([
  'id' => 'domain_nav_block',
  'plugin' => 'domain_nav_block',
  'theme' => 'olivero',
  'region' => 'sidebar',
  'weight' => 0,
  'settings' => [
    'label' => 'Domain navigation',
    'label_display' => 'visible',
  ],
]);
$nav_block->save();

$switcher_block = $block_storage->create([
  'id' => 'domain_switcher_block',
  'plugin' => 'domain_switcher_block',
  'theme' => 'olivero',
  'region' => 'sidebar',
  'weight' => 1,
  'settings' => [
    'label' => 'Domain switcher',
    'label_display' => 'visible',
  ],
]);
$switcher_block->save();

// Rebuild node access permissions (required after enabling domain_access).
node_access_rebuild();
