<?php

namespace Drupal\domain_config\Config;

use Drupal\Core\Config\ConfigFactoryOverrideInterface;

/**
 * Defines the interface for a configuration factory domain override object.
 */
interface DomainConfigFactoryOverrideInterface extends ConfigFactoryOverrideInterface {

  /**
   * Get override for a given domain and configuration name.
   *
   * @param string $domain_id
   *   Domain ID.
   * @param string $name
   *   Configuration name.
   *
   * @return \Drupal\Core\Config\Config
   *   Configuration override object.
   */
  public function getOverride($domain_id, $name);

  /**
   * Get override for a given domain and configuration name.
   *
   * @param string $domain_id
   *   Domain ID.
   * @param string $name
   *   Configuration name.
   *
   * @return \Drupal\Core\Config\Config
   *   Configuration override editable object.
   */
  public function getOverrideEditable($domain_id, $name);

  /**
   * Returns the storage instance for a particular domain.
   *
   * @param string $domain_id
   *   Domain ID.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage instance for a particular domain.
   */
  public function getStorage($domain_id);

  /**
   * Installs available configuration overrides for a given domain.
   *
   * @param string $domain_id
   *   Domain ID.
   */
  public function installDomainOverrides($domain_id);

}
