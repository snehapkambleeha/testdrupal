<?php

namespace Drupal\Tests\domain\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain record creation API.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainCreateTest extends DomainTestBase {

  /**
   * Tests initial domain creation.
   */
  public function testDomainCreate() {
    // No domains should exist.
    $this->domainTableIsEmpty();

    // Create a new domain programmatically.
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $storage->create();
    $domain->set('id', $storage->createMachineName($domain->getHostname()));
    $keys = [
      'id',
      'name',
      'hostname',
      'scheme',
      'status',
      'weight',
      'is_default',
    ];
    foreach ($keys as $key) {
      $property = $domain->get($key);
      $this->assertNotNull($property, 'Property loaded');
    }
    $domain->save();

    // Did it save correctly?
    $default_id = $storage->loadDefaultId();
    $this->assertNotEmpty($default_id, 'Default domain has been set.');

    // Does it load correctly?
    /** @var \Drupal\domain\DomainInterface $new_domain */
    $new_domain = $storage->load($default_id);
    $this->assertEquals($domain->id(), $new_domain->id(), 'Domain loaded properly.');

    // Has domain id been set?
    $this->assertNotNull($new_domain->getDomainId(), 'Domain id set properly.');

    // Has a UUID been set?
    $this->assertNotNull($new_domain->uuid(), 'Entity UUID set properly.');

    // Delete the domain.
    $domain->delete();
    $domain = $storage->load($default_id);
    $this->assertEmpty($domain, 'Domain record deleted.');

    // No domains should exist.
    $this->domainTableIsEmpty();
  }

}
