<?php

namespace Drupal\domain_config\Config;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCollectionEvents;
use Drupal\Core\Config\ConfigCrudEvent;

/**
 * Defines domain configuration overrides.
 */
class DomainConfigOverrideEditable extends DomainConfigOverride {

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    parent::set($key, $value);
    $this->setOverride($key, $value);
    return $this;
  }

  /**
   * Sets a value in this configuration object.
   *
   * @param string $key
   *   Identifier to store value in configuration.
   * @param mixed $value
   *   Value to associate with identifier.
   *
   * @return $this
   *   The configuration object.
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   *   If $value is an array and any of its keys in any depth contains a dot.
   */
  public function setOverride($key, $value) {
    $value = $this->castSafeStrings($value);
    $parts = explode('.', $key);
    if (count($parts) == 1) {
      $this->moduleOverrides[$key] = $value;
    }
    else {
      NestedArray::setValue($this->moduleOverrides, $parts, $value);
    }
    return $this;
  }

  /**
   * Sets whether this is a new configuration object.
   *
   * @param bool $new
   *   Whether this is a new configuration object.
   *
   * @return $this
   *   The configuration object.
   */
  public function setNew(bool $new) {
    $this->isNew = $new;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save($has_trusted_data = FALSE) {
    // Validate the configuration object name before saving.
    static::validateName($this->name);

    // If there is a schema for this configuration object, cast all values to
    // conform to the schema.
    if (!$has_trusted_data) {
      if ($this->typedConfigManager->hasConfigSchema($this->name)) {
        // Ensure that the schema wrapper has the latest data.
        $this->schemaWrapper = NULL;
        $this->data = $this->castValue(NULL, $this->data);
        // Update domain overrides with the cast values.
        static::updateExistingKeysInNestedArray($this->moduleOverrides, $this->data);
        // Reclaim the memory used by the schema wrapper.
        $this->schemaWrapper = NULL;
      }
      else {
        foreach ($this->moduleOverrides as $key => $value) {
          $this->validateValue($key, $value);
        }
      }
    }

    $this->storage->write($this->name, $this->moduleOverrides);
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
   * Updates values in a nested array structure.
   *
   * Recursively updates the `$target` array with values from the `$source`
   * array, but only for keys that already exist in `$target`.
   * If a key in `$source` does not exist in `$target`, it is skipped.
   *
   * @param array &$target
   *   The array to be updated. This array is modified in place.
   * @param array $source
   *   The array containing new values to update in `$target`.
   */
  public static function updateExistingKeysInNestedArray(array &$target, array $source) {
    foreach ($source as $key => $value) {
      if (!array_key_exists($key, $target)) {
        // Skip if key doesn't exist in target.
        continue;
      }
      if (is_array($value) && is_array($target[$key])) {
        static::updateExistingKeysInNestedArray($target[$key], $value);
      }
      else {
        $target[$key] = $value;
      }
    }
  }

}
