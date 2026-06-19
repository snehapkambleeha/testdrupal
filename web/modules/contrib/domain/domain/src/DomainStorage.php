<?php

namespace Drupal\domain;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads Domain records.
 */
class DomainStorage extends ConfigEntityStorage implements DomainStorageInterface {

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The typed config handler.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * The request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Whether to ignore www prefix when negotiating domains.
   *
   * @var bool
   */
  protected bool $ignoreWwwPrefix;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->container = $container;
    $instance->typedConfig = $container->get('config.typed');
    $instance->requestStack = $container->get('request_stack');
    $instance->ignoreWwwPrefix = $container->getParameter('domain.www_prefix');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadSchema() {
    $fields = $this->typedConfig->getDefinition('domain.record.*');

    return $fields['mapping'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadDefaultId() {
    $result = $this->loadDefaultDomain();
    if ($result instanceof DomainInterface) {
      return $result->id();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadDefaultDomain() {
    $result = $this->loadByProperties(['is_default' => TRUE]);
    if (count($result) > 0) {
      return current($result);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleSorted(?array $ids = NULL) {
    $domains = $this->loadMultiple($ids);
    uasort($domains, [$this, 'sort']);

    return $domains;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByHostname($hostname) {
    $result = $this->loadMultipleByHostname($hostname);
    if (count($result) === 0) {
      return NULL;
    }

    return current($result);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleByHostname(string $hostname): array {
    $hostname = $this->prepareHostname($hostname);
    return $this->loadByProperties(['hostname' => $hostname]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadOptionsList() {
    $list = [];
    foreach ($this->loadMultipleSorted() as $id => $domain) {
      $list[$id] = $domain->label();
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function sort(DomainInterface $a, DomainInterface $b) {
    // Prioritize the weights.
    $weight_difference = $a->getWeight() - $b->getWeight();
    if ($weight_difference !== 0) {
      return $weight_difference;
    }

    // Fallback to the labels if the weights are equal.
    return strcmp($a->label(), $b->label());
  }

  /**
   * {@inheritdoc}
   */
  public function prepareHostname($hostname) {
    // Strip www. prefix off the hostname.
    if ($this->ignoreWwwPrefix && substr($hostname, 0, 4) === 'www.') {
      $hostname = substr($hostname, 4);
    }

    return $hostname;
  }

  /**
   * {@inheritdoc}
   */
  public function create(array $values = []) {
    $default = $this->loadDefaultId();
    $count = $this->getQuery()->accessCheck(FALSE)->count()->execute();
    if ($values === []) {
      $values['hostname'] = $this->createHostname();
      $values['name'] = $this->configFactory->get('system.site')->get('name');
    }
    $values += [
      'scheme' => $this->getDefaultScheme(),
      'status' => '1',
      'weight' => $count + 1,
      'is_default' => (int) ($default === FALSE),
    ];
    $domain = parent::create($values);

    return $domain;
  }

  /**
   * {@inheritdoc}
   */
  public function createHostname() {
    $request = $this->requestStack->getCurrentRequest();
    $hostname = $request?->getHttpHost()
      ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $this->prepareHostname($hostname);
  }

  /**
   * {@inheritdoc}
   */
  public function createMachineName($hostname = NULL) {
    if (is_null($hostname)) {
      $hostname = $this->createHostname();
    }

    return preg_replace('/[^a-z0-9_]/', '_', $hostname);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultScheme() {
    // Use the foundation request if possible.
    $request = $this->requestStack->getCurrentRequest();
    if (!is_null($request)) {
      $scheme = $request->getScheme();
    }
    // Else use the server variable.
    elseif (isset($_SERVER['https']) && (bool) $_SERVER['https'] === TRUE) {
      $scheme = 'https';
    }
    // Else fall through to default.
    else {
      $scheme = 'http';
    }
    return $scheme;
  }

}
