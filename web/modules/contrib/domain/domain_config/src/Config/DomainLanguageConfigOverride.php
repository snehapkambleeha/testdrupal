<?php

namespace Drupal\domain_config\Config;

use Drupal\language\Config\LanguageConfigOverride;

/**
 * Defines domain language configuration overrides.
 */
class DomainLanguageConfigOverride extends LanguageConfigOverride {

  use DomainLanguageConfigCollectionNameTrait;

  /**
   * Returns the domain id of this domain override.
   *
   * @return string
   *   The domain id.
   */
  public function getDomainId() {
    $codes = $this->getDomainAndLangcodeFromCollectionName($this->getStorage()->getCollectionName());
    return $codes[0];
  }

  /**
   * Returns the language code of this language override.
   *
   * @return string
   *   The language code.
   */
  public function getLangcode() {
    $codes = $this->getDomainAndLangcodeFromCollectionName($this->getStorage()->getCollectionName());
    return $codes[1] ?? NULL;
  }

}
