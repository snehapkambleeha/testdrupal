<?php

/**
 * @file
 * API documentation file for Domain Source module.
 */

use Drupal\domain\DomainInterface;

/**
 * Allows modules to specify the target domain for an entity.
 *
 * There is no return value for this hook. Modify $source by reference by
 * loading a valid domain record or set $source = NULL to discard an existing
 * $source value and not rewrite the path.
 *
 * Note that $options['entity'] is the entity for the path request and
 * $options['entity_type'] is the type of entity (e.g. 'node').
 * These values have already been verified before this hook is called.
 *
 * If the entity's path is a translation, the requested translation of the
 * entity will be passed as the $entity value.
 *
 * @param \Drupal\domain\DomainInterface|null $source
 *   A domain object or NULL if not set, passed by reference.
 * @param string $path
 *   The outbound path request, passed by reference.
 * @param array $options
 *   The options for the url, as defined by
 *   \Drupal\Core\PathProcessor\OutboundPathProcessorInterface.
 */
function hook_domain_source_alter(?DomainInterface &$source, &$path, array $options) {
  // Always link to the default domain.
  $source = \Drupal::entityTypeManager()->getStorage('domain')->loadDefaultDomain();
}

/**
 * Allows modules to specify the target link for a Drupal path.
 *
 * Note: This hook is not meant to be used for node or entity paths, which
 * are handled by hook_domain_source_alter(). This hook is split
 * from hook_domain_source_alter() for better performance.
 *
 * Note that hook_domain_source_alter() only paths that are not content
 * entities.
 *
 * Currently, no modules in the package implement this hook.
 *
 * There is no return value for this hook. Modify $source by reference by
 * loading a valid domain record or set $source = NULL to discard an existing
 * $source value and not rewrite the path.
 *
 * @param \Drupal\domain\DomainInterface|null $source
 *   A domain object or NULL if not set, passed by reference.
 * @param string $path
 *   The outbound path request, passed by reference.
 * @param array $options
 *   The options for the url, as defined by
 *   \Drupal\Core\PathProcessor\OutboundPathProcessorInterface.
 */
function hook_domain_source_path_alter(?DomainInterface &$source, &$path, array $options) {
  // Always make admin links go to the primary domain.
  $parts = explode('/', $path);
  if (isset($parts[0]) && $parts[0] === 'admin') {
    $source = \Drupal::entityTypeManager()->getStorage('domain')->loadDefaultDomain();
  }
}

/**
 * Allows modules to alter the list of excluded paths.
 *
 * Domain Source will never rewrite excluded paths.
 *
 * @param array $excluded_paths
 *   An array of excluded paths. Each path may include wildcards, e.g.
 *   '/admin/*'.
 */
function hook_domain_source_excluded_paths_alter(array &$excluded_paths) {
  // Exclude admin paths.
  $excluded_paths[] = '/admin';
  $excluded_paths[] = '/admin/*';
}

/**
 * Modifies the list of excluded route names for domain source processing.
 *
 * @param array &$excluded_route_names
 *   Array of route names that are excluded from domain source processing.
 */
function hook_domain_source_excluded_route_names_alter(array &$excluded_route_names) {
  // Exclude layout builder node view route from domain source processing.
  $excluded_route_names[] = 'layout_builder.overrides.node.view';
}

/**
 * Alter the excluded routes options of the domain source settings form.
 *
 * This hook allows modules to modify or define additional options to exclude
 * specific routes from being processed by domain source functionality.
 *
 * Currently, no modules in the package implement this hook.
 *
 * @param array $options
 *   The associative array of exclude options, passed by reference.
 */
function hook_domain_source_exclude_routes_options_alter(array &$options) {
  // Exclude collection entity routes from domain source processing.
  $options['collection'] = 'collection';
}
