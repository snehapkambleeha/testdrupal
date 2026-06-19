<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\StorageComparer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\Entity\Domain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests domain import validation.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'system', 'user', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
  }

  /**
   * Tests that duplicate domain_id is rejected during import.
   */
  public function testDuplicateDomainIdImport() {
    $sync = $this->container->get('config.storage.sync');

    // Create two domain records with the same domain_id in the sync storage.
    $domain1_data = [
      'uuid' => 'uuid1',
      'id' => 'example_com',
      'domain_id' => 1,
      'hostname' => 'example.com',
      'name' => 'Example',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => 0,
    ];
    $sync->write('domain.record.example_com', $domain1_data);

    $domain2_data = [
      'uuid' => 'uuid2',
      'id' => 'example_net',
      // Duplicate domain_id.
      'domain_id' => 1,
      'hostname' => 'example.net',
      'name' => 'Example Net',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 2,
      'is_default' => 0,
    ];
    $sync->write('domain.record.example_net', $domain2_data);

    $config_importer = $this->configImporter();
    try {
      $config_importer->import();
      $this->fail('ConfigImporterException should be thrown due to duplicate domain_id.');
    }
    catch (\Exception $e) {
      $errors = $config_importer->getErrors();
      $found = FALSE;
      foreach ($errors as $error) {
        if (str_starts_with($error, 'The domain_id 1 is already used')) {
          $found = TRUE;
          break;
        }
      }
      $this->assertTrue($found, 'Error message for duplicate domain_id found.');
    }

    // Now test check against existing records.
    $this->container->get('config.storage')->deleteAll('domain.record.');
    $domain_existing = Domain::create([
      'id' => 'existing_com',
      'domain_id' => 1,
      'hostname' => 'existing.com',
      'name' => 'Existing',
    ]);
    $domain_existing->save();

    // Reset sync storage to have only one record with same domain_id.
    $sync->deleteAll('domain.record.');
    $sync->write('domain.record.example_com', $domain1_data);

    $config_importer = $this->configImporter();
    try {
      $config_importer->import();
      $this->fail('ConfigImporterException should be thrown due to duplicate domain_id with existing record.');
    }
    catch (\Exception $e) {
      $errors = $config_importer->getErrors();
      $found = FALSE;
      foreach ($errors as $error) {
        if (str_starts_with($error, 'The domain_id 1 is already used')) {
          $found = TRUE;
          break;
        }
      }
      $this->assertTrue($found, 'Error message for duplicate domain_id with existing record found.');
    }

    // Now test check for update (no error should be thrown).
    $sync->deleteAll('domain.record.');
    $domain1_data['name'] = 'Updated Name';
    $sync->write('domain.record.example_com', $domain1_data);
    // Remove the other record from sync that was causing failure in first test.
    $sync->delete('domain.record.example_net');

    // Remove the existing record that was created in the previous step.
    $this->container->get('config.storage')->delete('domain.record.existing_com');

    // Create 'example_com' in active storage so we can update it.
    Domain::create([
      'id' => 'example_com',
      'domain_id' => 1,
      'hostname' => 'example.com',
      'name' => 'Example',
    ])->save();

    $config_importer = $this->configImporter();
    $config_importer->import();
    $this->assertEmpty($config_importer->getErrors(), 'No errors should be thrown during update of domain record with same domain_id.');
    $this->assertEquals('Updated Name', $this->container->get('config.factory')->get('domain.record.example_com')->get('name'));
  }

  /**
   * Returns a ConfigImporter object to use in this test.
   *
   * @return \Drupal\Core\Config\ConfigImporter
   *   The ConfigImporter object.
   */
  protected function configImporter() {
    $storage_comparer = new StorageComparer(
      $this->container->get('config.storage.sync'),
      $this->container->get('config.storage')
    );
    $storage_comparer->createChangelist();

    return new ConfigImporter(
      $storage_comparer,
      $this->container->get('event_dispatcher'),
      $this->container->get('config.manager'),
      $this->container->get('lock'),
      $this->container->get('config.typed'),
      $this->container->get('module_handler'),
      $this->container->get('module_installer'),
      $this->container->get('theme_handler'),
      $this->container->get('string_translation'),
      $this->container->get('extension.list.module'),
      $this->container->get('extension.list.theme')
    );
  }

}
