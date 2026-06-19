<?php

/**
 * @file
 * Hooks for the domain_config_ui module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of routes where domain config UI is disabled.
 *
 * Routes in this list will not show the "Enable domain
 * configuration" button.
 *
 * @param array $routes
 *   An array of route names.
 *
 * @see \Drupal\domain_config_ui\DomainConfigUIManager::checkAllowedRoute()
 */
function hook_domain_config_ui_disallowed_routes_alter(array &$routes) {
  $routes[] = 'my_module.settings';
}

/**
 * Alter the list of disallowed configuration names.
 *
 * Configuration names in this list cannot be overridden
 * per domain.
 *
 * @param array $configurations
 *   An array of configuration names.
 *
 * @see \Drupal\domain_config_ui\Config\DomainConfigFactory::isAllowedConfiguration()
 */
function hook_domain_config_ui_disallowed_configurations_alter(array &$configurations) {
  $configurations[] = 'my_module.settings';
}

/**
 * @} End of "addtogroup hooks".
 */
