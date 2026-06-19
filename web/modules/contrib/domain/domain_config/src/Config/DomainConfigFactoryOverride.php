<?php

namespace Drupal\domain_config\Config;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigCollectionInfo;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactoryOverrideBase;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides domain overrides for the configuration factory.
 */
class DomainConfigFactoryOverride extends ConfigFactoryOverrideBase implements DomainConfigFactoryOverrideInterface {

  use DomainConfigCollectionNameTrait;

  /**
   * An array of configuration storages keyed by domain id.
   *
   * @var \Drupal\Core\Config\StorageInterface[]
   */
  protected $storages;

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    protected StorageInterface $baseStorage,
    protected EventDispatcherInterface $eventDispatcher,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {}

  /**
   * Gets the domain storage.
   *
   * @return \Drupal\domain\DomainStorageInterface
   *   The domain storage handler.
   */
  protected function getDomainStorage() {
    if (!isset($this->domainStorage)) {
      // @phpstan-ignore-next-line
      $this->domainStorage = \Drupal::entityTypeManager()->getStorage('domain');
    }
    return $this->domainStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $domain_id = $this->domainNegotiationContext->getDomainId();
    if ($domain_id) {
      $storage = $this->getStorage($domain_id);
      return $storage->readMultiple($names);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOverride($domain_id, $name) {
    $storage = $this->getStorage($domain_id);
    $data = $storage->read($name);

    $override = new DomainConfigOverride(
      $name,
      $storage,
      $this->eventDispatcher,
      $this->typedConfigManager,
    );

    if (!empty($data)) {
      $override->initWithData($data);
    }
    return $override;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverrideEditable($domain_id, $name) {
    $domain_storage = $this->getStorage($domain_id);

    $override = new DomainConfigOverrideEditable(
      $name,
      $domain_storage,
      $this->eventDispatcher,
      $this->typedConfigManager,
    );

    $base_data = $this->baseStorage->read($name) ?: [];
    $domain_data = $domain_storage->read($name);
    $has_domain_data = is_array($domain_data);

    // Initialize $data with base merged with any existing domain override.
    // DomainConfigOverrideEditable::save() casts $data through the schema
    // and copies the cast values back onto $moduleOverrides for every key
    // already present there. If $data held only the base values, a form
    // saving a subset of fields would overwrite unrelated override keys
    // with their base values. Pre-merging keeps those override values
    // intact through the cast step.
    $initial_data = $has_domain_data
      ? NestedArray::mergeDeepArray([$base_data, $domain_data], TRUE)
      : $base_data;
    if (!empty($initial_data)) {
      $override->initWithData($initial_data);
    }

    if ($has_domain_data) {
      $override->setModuleOverride($domain_data);
    }
    else {
      $override->setModuleOverride([]);
      // initWithData() above left isNew=FALSE. This is a new override,
      // so flip it back to TRUE.
      $override->setNew(TRUE);
    }

    return $override;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($domain_id) {
    if (!isset($this->storages[$domain_id])) {
      $this->storages[$domain_id] =
        $this->baseStorage->createCollection(
          $this->createConfigCollectionName($domain_id)
        );
    }
    return $this->storages[$domain_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return $this->domainNegotiationContext->getDomainId('und');
  }

  /**
   * {@inheritdoc}
   */
  public function installDomainOverrides($domain_id) {
    /** @var \Drupal\Core\Config\ConfigInstallerInterface $config_installer */
    // @phpstan-ignore-next-line
    $config_installer = \Drupal::service('config.installer');
    $config_installer->installCollectionDefaultConfig($this->createConfigCollectionName($domain_id));
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    $domain_id = $this->getDomainFromCollectionName($collection);
    return $this->getOverride($domain_id, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function addCollections(ConfigCollectionInfo $collection_info) {
    foreach ($this->getDomainStorage()->loadMultipleSorted() as $domain) {
      $collection_info->addCollection($this->createConfigCollectionName($domain->id()), $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    foreach ($this->getDomainStorage()->loadMultipleSorted() as $domain) {
      $domain_config = $this->getOverride($domain->id(), $name);
      if (!$domain_config->isNew()) {
        $this->filterOverride($config, $domain_config);
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
    foreach ($this->getDomainStorage()->loadMultipleSorted() as $domain) {
      $domain_config = $this->getOverride($domain->id(), $old_name);
      if (!$domain_config->isNew()) {
        $saved_config = $domain_config->get();
        $storage = $this->getStorage($domain->id());
        $storage->write($name, $saved_config);
        $domain_config->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    foreach ($this->getDomainStorage()->loadMultipleSorted() as $domain) {
      $domain_config = $this->getOverride($domain->id(), $name);
      if (!$domain_config->isNew()) {
        $domain_config->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    $metadata = new CacheableMetadata();
    if ($this->domainNegotiationContext->hasDomain()) {
      $metadata->setCacheContexts(['domain']);
    }
    return $metadata;
  }

}
