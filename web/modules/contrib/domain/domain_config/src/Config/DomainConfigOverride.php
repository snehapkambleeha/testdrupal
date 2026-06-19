<?php

namespace Drupal\domain_config\Config;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCollectionEvents;
use Drupal\Core\Config\ConfigCrudEvent;

/**
 * Defines domain configuration overrides.
 */
class DomainConfigOverride extends Config {

  use DomainConfigCollectionNameTrait;

  /**
   * {@inheritdoc}
   */
  public function save($has_trusted_data = FALSE) {
    if (!$has_trusted_data) {
      // @todo Use configuration schema to validate.
      //   https://www.drupal.org/node/2270399
      // Perform basic data validation.
      foreach ($this->data as $key => $value) {
        $this->validateValue($key, $value);
      }
    }

    $this->storage->write($this->name, $this->data);
    // Invalidate the cache tags not only when updating, but also when creating,
    // because a domain config override object uses the same cache tag as the
    // default configuration object. Hence creating a domain override is like
    // an update of configuration, but only for a specific domain.
    Cache::invalidateTags($this->getCacheTags());
    $this->isNew = FALSE;
    // Dispatch configuration override event as detailed in
    // \Drupal\Core\Config\ConfigFactoryOverrideInterface::createConfigObject().
    $this->eventDispatcher->dispatch(new ConfigCrudEvent($this), ConfigCollectionEvents::SAVE_IN_COLLECTION);
    // Dispatch an event specifically for domain configuration override
    // changes.
    $this->eventDispatcher->dispatch(new DomainConfigOverrideCrudEvent($this), DomainConfigOverrideEvents::SAVE_OVERRIDE);
    $this->originalData = $this->data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->data = [];
    $this->storage->delete($this->name);
    Cache::invalidateTags($this->getCacheTags());
    $this->isNew = TRUE;
    // Dispatch configuration override event as detailed in
    // \Drupal\Core\Config\ConfigFactoryOverrideInterface::createConfigObject().
    $this->eventDispatcher->dispatch(new ConfigCrudEvent($this), ConfigCollectionEvents::DELETE_IN_COLLECTION);
    // Dispatch an event specifically for domain configuration override
    // changes.
    $this->eventDispatcher->dispatch(new DomainConfigOverrideCrudEvent($this), DomainConfigOverrideEvents::DELETE_OVERRIDE);
    $this->originalData = $this->data;
    return $this;
  }

  /**
   * Returns the domain id of this domain override.
   *
   * @return string
   *   The domain id.
   */
  public function getDomainId() {
    return $this->getDomainFromCollectionName($this->getStorage()->getCollectionName());
  }

}
