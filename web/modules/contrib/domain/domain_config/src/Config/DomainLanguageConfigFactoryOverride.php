<?php

namespace Drupal\domain_config\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigCollectionInfo;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactoryOverrideBase;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\StorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides language overrides for the configuration factory.
 */
class DomainLanguageConfigFactoryOverride extends ConfigFactoryOverrideBase implements DomainLanguageConfigFactoryOverrideInterface {

  use DomainLanguageConfigCollectionNameTrait;

  /**
   * The language object used to override configuration data.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainEntityStorage;

  /**
   * The domain negotiation context.
   *
   * @var \Drupal\domain\DomainNegotiationContext
   */
  protected $domainNegotiationContext;

  /**
   * An array of configuration storages keyed by domain id and langcode.
   *
   * @var \Drupal\Core\Config\StorageInterface[][]
   */
  protected $storages;

  public function __construct(
    protected StorageInterface $baseStorage,
    protected EventDispatcherInterface $eventDispatcher,
    protected TypedConfigManagerInterface $typedConfigManager,
    LanguageDefault $default_language,
  ) {
    // Prior to negotiation the override language should be the default
    // language.
    $this->language = $default_language->get();
  }

  /**
   * Sets the domain negotiation context.
   *
   * @param \Drupal\domain\DomainNegotiationContext $domainNegotiationContext
   *   The domain negotiation context.
   */
  public function setDomainNegotiationContext(DomainNegotiationContext $domainNegotiationContext): void {
    $this->domainNegotiationContext = $domainNegotiationContext;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguages() {
    // @phpstan-ignore-next-line
    return \Drupal::languageManager()->getLanguages();
  }

  /**
   * Gets the domain storage.
   *
   * @return \Drupal\domain\DomainStorageInterface
   *   The domain storage handler.
   */
  protected function getDomainEntityStorage() {
    if (!isset($this->domainEntityStorage)) {
      // @phpstan-ignore-next-line
      $this->domainEntityStorage = \Drupal::entityTypeManager()->getStorage('domain');
    }
    return $this->domainEntityStorage;
  }

  /**
   * Returns all available domain entities sorted.
   *
   * @return \Drupal\domain\DomainInterface[]
   *   An array of domain entities.
   */
  protected function getDomains() {
    return $this->getDomainEntityStorage()->loadMultipleSorted();
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $domain_id = $this->domainNegotiationContext->getDomainId();
    if ($domain_id && $this->language) {
      $storage = $this->getDomainStorage($domain_id, $this->language->getId());
      return $storage->readMultiple($names);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDomainOverride($domain_id, $lang_code, $name) {
    $storage = $this->getDomainStorage($domain_id, $lang_code);
    $data = $storage->read($name);

    $override = new DomainLanguageConfigOverride(
      $name,
      $storage,
      $this->typedConfigManager,
      $this->eventDispatcher
    );

    if (!empty($data)) {
      $override->initWithData($data);
    }
    return $override;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomainStorage($domain_id, $lang_code) {
    if (!isset($this->storages[$domain_id][$lang_code])) {
      $collection_name = $this->createDomainConfigCollectionName($domain_id, $lang_code);
      $this->storages[$domain_id][$lang_code] = $this->baseStorage->createCollection($collection_name);
    }
    return $this->storages[$domain_id][$lang_code];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return $this->domainNegotiationContext->getDomainId('und') . ':' . $this->language->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function installLanguageOverrides($langcode) {
    /** @var \Drupal\Core\Config\ConfigInstallerInterface $config_installer */
    // @phpstan-ignore-next-line
    $config_installer = \Drupal::service('config.installer');
    foreach ($this->getDomains() as $domain) {
      $collection_name = $this->createDomainConfigCollectionName($domain->id(), $langcode);
      $config_installer->installCollectionDefaultConfig($collection_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    $codes = $this->getDomainAndLangcodeFromCollectionName($collection);
    return $this->getDomainOverride($codes[0], $codes[1], $name);
  }

  /**
   * {@inheritdoc}
   */
  public function addCollections(ConfigCollectionInfo $collection_info) {
    foreach ($this->getDomains() as $domain) {
      foreach ($this->getLanguages() as $language) {
        $collection_name = $this->createDomainConfigCollectionName($domain->id(), $language->getId());
        $collection_info->addCollection($collection_name, $this);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    foreach ($this->getDomains() as $domain) {
      foreach ($this->getLanguages() as $language) {
        $config_translation = $this->getDomainOverride($domain->id(), $language->getId(), $name);
        if (!$config_translation->isNew()) {
          $this->filterOverride($config, $config_translation);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigRename(ConfigRenameEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    $old_name = $event->getOldName();
    foreach ($this->getDomains() as $domain) {
      foreach ($this->getLanguages() as $language) {
        $config_translation = $this->getDomainOverride($domain->id(), $language->getId(), $old_name);
        if (!$config_translation->isNew()) {
          $saved_config = $config_translation->get();
          $storage = $this->getDomainStorage($language->getId(), $language->getId());
          $storage->write($name, $saved_config);
          $config_translation->delete();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    foreach ($this->getDomains() as $domain) {
      foreach ($this->getLanguages() as $language) {
        $config_translation = $this->getDomainOverride($domain->id(), $language->getId(), $name);
        if (!$config_translation->isNew()) {
          $config_translation->delete();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    $metadata = new CacheableMetadata();
    if ($this->language) {
      $metadata->setCacheContexts(['languages:language_interface']);
    }
    if ($this->domainNegotiationContext->hasDomain()) {
      $metadata->setCacheContexts(['domain']);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage() {
    return $this->language;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage(?LanguageInterface $language = NULL) {
    $this->language = $language;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverride($langcode, $name) {
    $domain_id = $this->domainNegotiationContext->getDomainId();
    return $this->getDomainOverride($domain_id, $langcode, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($langcode) {
    $domain_id = $this->domainNegotiationContext->getDomainId();
    return $this->getDomainStorage($domain_id, $langcode);
  }

}
