<?php

namespace Drupal\domain_config\Config;

/**
 * Provides a common trait for working with domain override collection names.
 */
trait DomainConfigCollectionNameTrait {

  /**
   * Creates a configuration collection name based on a domain id.
   *
   * @param string $domain_id
   *   The domain id.
   *
   * @return string
   *   The configuration collection name for a domain.
   */
  protected function createConfigCollectionName($domain_id) {
    return DomainConfigCollectionUtils::createDomainConfigCollectionName($domain_id);
  }

  /**
   * Converts a configuration collection name to a domain id.
   *
   * @param string $collection
   *   The configuration collection name.
   *
   * @return string
   *   The domain id of the collection.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown if the provided collection name is not in the format
   *   "domain.DOMAIN_ID".
   *
   * @see self::createConfigCollectionName()
   */
  protected function getDomainFromCollectionName($collection) {
    preg_match('/^domain\.([^.]+)$/', $collection, $matches);
    if (!isset($matches[1])) {
      throw new \InvalidArgumentException("'$collection' is not a valid domain override collection");
    }
    return $matches[1];
  }

}
