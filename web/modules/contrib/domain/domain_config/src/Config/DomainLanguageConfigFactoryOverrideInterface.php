<?php

namespace Drupal\domain_config\Config;

use Drupal\language\Config\LanguageConfigFactoryOverrideInterface;

/**
 * Defines the interface for a configuration factory domain override object.
 */
interface DomainLanguageConfigFactoryOverrideInterface extends LanguageConfigFactoryOverrideInterface {

  /**
   * Returns all available languages.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   An array of language entities.
   */
  public function getLanguages();

  /**
   * Get override for a given domain, language and configuration name.
   *
   * @param string $domain_id
   *   Domain ID.
   * @param string $lang_code
   *   Language code.
   * @param string $name
   *   Configuration name.
   *
   * @return \Drupal\Core\Config\Config
   *   Configuration override object.
   */
  public function getDomainOverride($domain_id, $lang_code, $name);

  /**
   * Returns the storage instance for a particular domain language.
   *
   * @param string $domain_id
   *   Domain ID.
   * @param string $lang_code
   *   Language code.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage instance for a particular domain and language.
   */
  public function getDomainStorage($domain_id, $lang_code);

}
