<?php

namespace Drupal\domain_config_ui;

/**
 * Domain Config UI manager.
 */
interface DomainConfigUIManagerInterface {

  /**
   * Get the active domain ID.
   *
   * @return string|null
   *   The active domain machine name.
   */
  public function getActiveDomainId();

  /**
   * Checks if route is admin.
   *
   * @return bool
   *   TRUE if route is admin. Otherwise, FALSE.
   */
  public function isAllowedRoute();

  /**
   * Check that a specific config can be edited per domain.
   *
   * @param string|array $names
   *   The config name.
   *
   * @return bool
   *   TRUE if it can be edited by domain, FALSE otherwise.
   */
  public function isAllowedConfiguration($names):bool;

  /**
   * Checks if a configuration is allowed to be overridden for active domain.
   *
   * @param string $names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if configuration is overridable for the active domain,
   *   FALSE otherwise.
   */
  public function isRegisteredConfiguration($names);

  /**
   * Checks if a configuration is allowed to be overridden for a domain.
   *
   * @param string $domain_id
   *   The domain id.
   * @param string $names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if configuration is overridable for the domain, FALSE otherwise.
   */
  public function isConfigurationRegisteredForDomain($domain_id, $names);

  /**
   * Add configuration to a specific domain.
   *
   * @param string $domain_id
   *   The domain id.
   * @param array $config_names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if successfully added, FALSE otherwise.
   */
  public function addConfigurationsToDomain($domain_id, $config_names);

  /**
   * Remove configuration from a specific domain.
   *
   * @param string $domain_id
   *   The domain id.
   * @param array $config_names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if successfully removed, FALSE otherwise.
   */
  public function removeConfigurationsFromDomain($domain_id, $config_names);

  /**
   * Add configurations to the current domain.
   *
   * @param array $config_names
   *   Configuration names.
   *
   * @return bool
   *   TRUE if successfully added, FALSE otherwise.
   */
  public function addConfigurationsToCurrentDomain($config_names);

  /**
   * Remove configurations from the current domain.
   *
   * @param array $config_names
   *   Configuration names.
   *
   * @return bool
   *   TRUE if successfully removed, FALSE otherwise.
   */
  public function removeConfigurationsFromCurrentDomain($config_names);

  /**
   * Deletes a domain configuration and optionally removes its registration.
   *
   * @param mixed $domain_id
   *   The domain ID.
   * @param mixed $config_name
   *   The configuration name.
   * @param bool $remove
   *   Whether to remove the configuration registration from the domain.
   */
  public function deleteConfigurationOverridesForDomain(mixed $domain_id, mixed $config_name, bool $remove = TRUE);

  /**
   * Determines if the current user can administer domain config.
   *
   * @return bool
   *   TRUE if the current user can administer domain config, FALSE otherwise.
   */
  public function canAdministerDomainConfig();

  /**
   * Determines if the current user can use domain config.
   *
   * @return bool
   *   TRUE if the current user can use domain config, FALSE otherwise.
   */
  public function canUseDomainConfig();

  /**
   * Determines if the current user can set the default domain config.
   *
   * @return bool
   *   TRUE if the current user can set the default domain config, FALSE
   *   otherwise.
   */
  public function canSetDefaultDomainConfig();

  /**
   * Determines if the current user can translate domain config.
   *
   * @return bool
   *   TRUE if the current user can translate domain config, FALSE otherwise.
   */
  public function canTranslateDomainConfig();

}
