<?php

namespace Drupal\domain_config_ui\Config;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain_config\Config\DomainConfigFactoryOverrideInterface;
use Drupal\domain_config\Config\DomainLanguageConfigFactoryOverrideInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Extends core ConfigFactory class to save domain specific configuration.
 */
class DomainConfigFactory extends ConfigFactory {

  const DOMAIN_CONFIG_UI_DISALLOWED_CONFIGURATIONS = [
    'domain_config_ui.settings',
    'domain.settings',
    'language.types',
    'update.settings',
  ];

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Drupal\Core\Routing\AdminContext definition.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The overridable configurations.
   *
   * @var array
   */
  protected $overridableConfigurations;

  /**
   * List of configuration names that must not be overridden.
   *
   * @var array|null
   */
  protected $disallowedConfigurations = NULL;

  public function __construct(
    StorageInterface $storage,
    EventDispatcherInterface $event_dispatcher,
    TypedConfigManagerInterface $typed_config,
    protected DomainConfigFactoryOverrideInterface $overrideFactory,
    protected DomainLanguageConfigFactoryOverrideInterface $languageOverrideFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {
    parent::__construct($storage, $event_dispatcher, $typed_config);
  }

  /**
   * Create a domain editable configuration object.
   *
   * @param string $name
   *   The name of the configuration object to create.
   *
   * @return \Drupal\Core\Config\Config
   *   A new configuration object that is editable per domain.
   */
  protected function createDomainEditableConfigObject($name) {
    $domain_id = $this->getActiveDomainId();
    return $this->overrideFactory->getOverrideEditable($domain_id, $name);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $names, $immutable = TRUE) {
    // Don't override config if immutable or not per domain editable.
    if ($immutable || !$this->isPerDomainEditable($names)) {
      return parent::doLoadMultiple($names, $immutable);
    }

    $list = [];

    foreach ($names as $key => $name) {
      $cache_key = $this->getDomainEditableConfigCacheKey($name);
      if (isset($this->cache[$cache_key])) {
        $list[$name] = $this->cache[$cache_key];
        unset($names[$key]);
      }
    }

    // Pre-load remaining configuration files.
    if ($names !== []) {
      foreach ($names as $name) {
        $cache_key = $this->getDomainEditableConfigCacheKey($name);
        $this->cache[$cache_key] = $this->createDomainEditableConfigObject($name);
        $list[$name] = $this->cache[$cache_key];
      }
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGet($name, $immutable = TRUE) {
    // Don't override config if immutable or not per domain editable.
    if ($immutable || !$this->isPerDomainEditable($name)) {
      return parent::doGet($name, $immutable);
    }
    $config = $this->doLoadMultiple([$name], $immutable);
    if (isset($config[$name])) {
      return $config[$name];
    }
    else {
      // If the configuration object does not exist in the configuration
      // storage, create a new object.
      return $this->createDomainEditableConfigObject($name);
    }
  }

  /**
   * Get the cache key for a domain editable configuration object.
   *
   * @param string $name
   *   The name of the configuration object.
   *
   * @return string
   *   The cache key for the domain configuration object.
   */
  protected function getDomainEditableConfigCacheKey($name) {
    // We want to be able to cache all editable domain-specific configuration
    // objects, so we need to include the domain cache keys in the cache key.
    // Default implementation only add cache keys to immutable config.
    $suffix = ':' . $this->getActiveDomainId();
    // To avoid potential conflicts with the default config cache key.
    $suffix .= ':editable';
    return $name . $suffix;
  }

  /**
   * Get the selected domain ID.
   *
   * @return string|null
   *   A domain machine name.
   */
  protected function getActiveDomainId() {
    return $this->domainNegotiationContext->getDomainId();
  }

  /**
   * Check if a selected domain is available in request or session.
   */
  protected function hasActiveDomainId() {
    return $this->domainNegotiationContext->hasDomain();
  }

  /**
   * Get the configured overridable configurations.
   *
   * @return array
   *   The overridable configurations.
   */
  public function getOverridableConfigurations() {
    if (!isset($this->overridableConfigurations)) {
      $config = $this->get('domain_config_ui.settings');
      $overridable_configurations = $config->get('overridable_configurations');
      $this->overridableConfigurations = [];
      if ($overridable_configurations) {
        foreach ($overridable_configurations as $configuration) {
          $this->overridableConfigurations[$configuration['name']] = $configuration['domains'];
        }
      }
    }
    return $this->overridableConfigurations;
  }

  /**
   * Checks if a configuration is allowed to be overridden for active domain.
   *
   * @param string|array $names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if the configuration is overridable for the active domain,
   *   FALSE otherwise.
   */
  public function isRegisteredConfiguration($names) {
    return $this->isConfigurationRegisteredForDomain($this->getActiveDomainId(), $names);
  }

  /**
   * Checks if a configuration is allowed to be overridden for a domain.
   *
   * @param string $domain_id
   *   The domain ID to check.
   * @param string|array $names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if configuration is overridable for the domain, FALSE otherwise.
   */
  public function isConfigurationRegisteredForDomain($domain_id, $names) {
    $overridable_configurations = $this->getOverridableConfigurations();
    $name = is_array($names) ? current($names) : $names;
    if (isset($overridable_configurations[$name])) {
      return in_array($domain_id, $overridable_configurations[$name]);
    }
    return FALSE;
  }

  /**
   * Check that a specific config can be edited per domain.
   *
   * @param string|array $names
   *   The config name.
   *
   * @return bool
   *   TRUE if it can be edited by domain, FALSE otherwise.
   */
  public function isAllowedConfiguration($names):bool {
    if (!isset($this->disallowedConfigurations)) {
      $config = $this->get('domain_config_ui.settings');
      $disallowed_configurations = $config->get('disallowed_configurations') ?: [];
      $this->disallowedConfigurations = array_merge(
        // Never allow this module's settings to be added, for example.
        static::DOMAIN_CONFIG_UI_DISALLOWED_CONFIGURATIONS,
        $disallowed_configurations,
      );
      // Allow modules to alter the list of disallowed configurations.
      $this->moduleHandler->alter('domain_config_ui_disallowed_configurations', $this->disallowedConfigurations);
    }
    if (is_array($names)) {
      if (!empty(array_intersect($names, $this->disallowedConfigurations))) {
        return FALSE;
      }
    }
    else {
      if (in_array($names, $this->disallowedConfigurations, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Deletes a domain configuration and optionally removes its registration.
   *
   * @param mixed $domain_id
   *   The domain ID.
   * @param mixed $config_name
   *   The configuration name.
   */
  public function deleteConfigurationOverridesForDomain(mixed $domain_id, mixed $config_name) {
    if ($this->overrideFactory->getStorage($domain_id)->exists($config_name)) {
      $this->overrideFactory->getOverride($domain_id, $config_name)->delete();
    }
    foreach ($this->languageOverrideFactory->getLanguages() as $language) {
      if ($this->languageOverrideFactory->getDomainStorage(
        $domain_id, $language->getId())->exists($config_name)) {
        $this->languageOverrideFactory->getDomainOverride(
          $domain_id, $language->getId(), $config_name)->delete();
      }
    }
  }

  /**
   * Checks if the current route and path are domain-configurable.
   *
   * @param string|array $names
   *   The config name.
   *
   * @return bool
   *   TRUE if domain-configurable, false otherwise.
   */
  public function isPerDomainEditable($names) {
    return $this->hasActiveDomainId()
      && $this->isRegisteredConfiguration($names)
      && $this->isAllowedConfiguration($names);
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    parent::onConfigSave($event);
    $config = $event->getConfig();
    if ($config->getName() === 'domain_config_ui.settings') {
      $this->overridableConfigurations = NULL;
      $this->getOverridableConfigurations();
    }
  }

}
