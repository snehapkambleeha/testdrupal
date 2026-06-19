<?php

namespace Drupal\Tests\domain\Traits;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Contains helper classes for tests to set up various configuration.
 */
trait DomainTestTrait {

  /**
   * Sets a base hostname for running tests.
   *
   * When creating test domains, try to use $this->baseHostname or the
   * domainCreateTestDomains() method.
   *
   * @var string
   */
  public $baseHostname;

  /**
   * Sets a base TLD for running tests.
   *
   * When creating test domains, try to use $this->baseTLD or the
   * domainCreateTestDomains() method.
   *
   * @var string
   */
  public $baseTLD;

  /**
   * Generates a list of domains for testing.
   *
   * In my environment, I use the example.local hostname as a base. Then I name
   * hostnames one.* two.* up to ten. Note that we always use *_example_com
   * for the machine_name (entity id) value, though the hostname can vary
   * based on the system. This naming allows us to load test schema files.
   *
   * The script may also add test1, test2, test3 up to any number to test a
   * large number of domains.
   *
   * When $prefixes is TRUE, all domains share the base hostname
   * and are differentiated by path prefix instead of subdomain.
   * The $list items are used as prefixes: the first entry ('')
   * becomes an empty prefix (default domain), 'one' becomes
   * prefix 'one', etc. Machine names are preserved so that
   * existing test references remain valid.
   *
   * @param int $count
   *   The number of domains to create.
   * @param string|null $base_hostname
   *   The root domain to use for domain creation
   *   (e.g. example.com). You should normally leave this
   *   blank to account for alternate test environments.
   * @param array $list
   *   An optional list of subdomains to apply instead of
   *   the default set. When $prefixes is TRUE, these values
   *   are used as path prefixes instead.
   * @param bool $prefixes
   *   When TRUE, all domains share the base hostname and
   *   $list items are used as path prefixes.
   */
  public function domainCreateTestDomains($count = 1, $base_hostname = NULL, array $list = [], bool $prefixes = FALSE) {
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    if (is_null($base_hostname)) {
      $base_hostname = $this->baseHostname;
    }
    // Note: these domains are rigged to work on my test server.
    // For proper testing, yours should be set up similarly, but you can pass a
    // $list array to change the default.
    if ($list === []) {
      $list = [
        '',
        'one',
        'two',
        'three',
        'four',
        'five',
        'six',
        'seven',
        'eight',
        'nine',
        'ten',
      ];
    }
    for ($i = 0; $i < $count; $i++) {
      if ($i === 0) {
        $hostname = $base_hostname;
        $machine_name = 'example.com';
        $name = 'Example';
      }
      elseif (isset($list[$i])) {
        $hostname = $prefixes
          ? $base_hostname
          : $list[$i] . '.' . $base_hostname;
        $machine_name = $list[$i] . '.example.com';
        $name = 'Test ' . ucfirst($list[$i]);
      }
      // These domains are not setup and are just for UX testing.
      else {
        $hostname = $prefixes
          ? $base_hostname
          : 'test' . $i . '.' . $base_hostname;
        $machine_name = 'test' . $i . '.example.com';
        $name = 'Test ' . $i;
      }
      // Create a new domain programmatically.
      $values = [
        'hostname' => $hostname,
        'name' => $name,
        'id' => $storage->createMachineName($machine_name),
      ];
      // Use the list item as path prefix.
      if ($prefixes && isset($list[$i])) {
        $values['path_prefix'] = $list[$i];
      }
      $domain = $storage->create($values);
      $domain->save();
    }

    // Refresh domain negotiation context after creating test domains.
    $this->getActiveDomain(TRUE);
  }

  /**
   * Gets the active domain.
   *
   * Determines and returns the currently active domain based on the request.
   * If negotiation hasn't occurred yet or needs to be reset, this method
   * will trigger the domain negotiation process.
   *
   * @param bool $reset
   *   (optional) If TRUE, forces re-negotiation of the active domain.
   *   Defaults to FALSE.
   *
   * @return \Drupal\domain\DomainInterface|null
   *   The active domain object, or NULL if no domain could be determined.
   */
  public function getActiveDomain($reset = FALSE) {
    return \Drupal::service('domain.negotiator')->getActiveDomain($reset);
  }

  /**
   * Adds a test domain to an entity.
   *
   * @param string $entity_type
   *   The entity type being acted upon.
   * @param int $entity_id
   *   The entity id.
   * @param array|string $ids
   *   An id or array of ids to add.
   * @param string $field
   *   The name of the domain field used to attach to the entity.
   */
  public function addDomainsToEntity($entity_type, $entity_id, $ids, $field) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    if ($entity instanceof FieldableEntityInterface) {
      $entity->set($field, $ids);
      $entity->save();
    }
  }

  /**
   * Returns an uncached list of all domains.
   *
   * @return \Drupal\domain\DomainInterface[]
   *   An array of domain entities.
   */
  public function getDomains() {
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $storage->resetCache();

    return $storage->loadMultiple();
  }

  /**
   * Returns an uncached list of all domains, sorted by weight.
   *
   * @return \Drupal\domain\DomainInterface[]
   *   An array of domain entities.
   */
  public function getDomainsSorted() {
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $storage->resetCache();

    return $storage->loadMultipleSorted();
  }

  /**
   * Converts a domain hostname to a trusted host pattern.
   *
   * @param string $hostname
   *   A hostname string.
   *
   * @return string
   *   A regex-safe hostname, without delimiters.
   */
  public function prepareTrustedHostname($hostname) {
    $hostname = mb_strtolower(preg_replace('/:\d+$/', '', trim($hostname)));
    return preg_quote($hostname);
  }

  /**
   * Set the base hostname for this test.
   */
  public function setBaseHostname() {
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $this->baseHostname = $storage->createHostname();
    $this->setBaseDomain();
  }

  /**
   * Set the base TLD for this test.
   */
  public function setBaseDomain() {
    $hostname = $this->baseHostname;
    $parts = explode('.', $hostname);
    $this->baseTLD = array_pop($parts);
  }

  /**
   * Reusable test function for checking initial / empty table status.
   */
  public function domainTableIsEmpty() {
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultiple();
    $this->assertEmpty($domains, 'No domains have been created.');
    $default_id = $storage->loadDefaultId();
    $this->assertEmpty($default_id, 'No default domain has been set.');
  }

  /**
   * Creates domain record for use with POST request tests.
   */
  public function domainPostValues() {
    $edit = [];
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $storage->create();
    $required = \Drupal::service('domain.validator')->getRequiredFields();
    foreach ($required as $key) {
      $edit[$key] = $domain->get($key);
    }
    $edit['id'] = $storage->createMachineName($edit['hostname']);

    // Validation requires extra test steps, so do not do it by default.
    $edit['validate_url'] = 0;

    return $edit;
  }

}
