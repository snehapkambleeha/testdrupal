<?php

namespace Drupal\domain_config\Config;

/**
 * Provides a common trait for working with domain override collection names.
 */
trait DomainLanguageConfigCollectionNameTrait {

  /**
   * Creates a configuration collection name based on a domain id and language.
   *
   * @param string $domain_id
   *   The domain id.
   * @param string $lang_code
   *   The language code.
   *
   * @return string
   *   The configuration collection name for a domain and language.
   */
  protected function createDomainConfigCollectionName($domain_id, $lang_code) {
    return DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName($domain_id, $lang_code);
  }

  /**
   * Converts a configuration collection name to a domain id and language code.
   *
   * @param string $collection
   *   The configuration collection name.
   *
   * @return array|bool
   *   The domain id and language code of the collection.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown if the provided collection name is not in the format
   *   "domain.DOMAIN_ID.language.LANGCODE".
   *
   * @see self::createConfigCollectionName()
   */
  protected function getDomainAndLangcodeFromCollectionName($collection) {
    preg_match('/^domain\.([^.]+)\.language\.([^.]+)$/', $collection, $matches);
    if (count($matches) !== 3) {
      return FALSE;
    }
    return [$matches[1], $matches[2]];
  }

}
