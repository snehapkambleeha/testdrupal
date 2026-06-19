<?php

namespace Drupal\domain_config\Config;

/**
 * Provides utility methods for domain configuration collections.
 */
class DomainConfigCollectionUtils {

  /**
   * Creates a configuration collection name based on a domain id.
   *
   * @param string $domain_id
   *   The domain id.
   *
   * @return string
   *   The configuration collection name for a domain.
   */
  public static function createDomainConfigCollectionName($domain_id) {
    return 'domain.' . $domain_id;
  }

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
  public static function createDomainLanguageConfigCollectionName($domain_id, $lang_code) {
    return 'domain.' . $domain_id . '.language.' . $lang_code;
  }

}
