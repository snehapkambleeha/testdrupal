<?php

namespace Drupal\domain_config\Config;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides a domain override event for event listeners.
 *
 * @see \Drupal\Core\Config\ConfigCrudEvent
 */
class DomainConfigOverrideCrudEvent extends Event {

  public function __construct(protected DomainConfigOverride $override) {
    $this->override = $override;
  }

  /**
   * Gets the configuration override object.
   *
   * @return \Drupal\domain_config\Config\DomainConfigOverride
   *   The configuration object that caused the event to fire.
   */
  public function getOverride() {
    return $this->override;
  }

  /**
   * Checks to see if the provided configuration key's value has changed.
   *
   * @param string $key
   *   The configuration key to check if it has changed.
   *
   * @return bool
   *   TRUE if the value has changed, FALSE otherwise.
   */
  public function isChanged($key) {
    return $this->override->get($key) !== $this->override->getOriginal($key);
  }

}
