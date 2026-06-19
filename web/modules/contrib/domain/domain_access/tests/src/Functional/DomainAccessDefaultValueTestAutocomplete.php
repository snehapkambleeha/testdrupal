<?php

namespace Drupal\Tests\domain_access\Functional;

use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\Tests\domain\Functional\DomainTestBase;

/**
 * Tests domain access default value handling with autocomplete widget.
 *
 * This class extends the functionality of DomainTestBase to test the behavior
 * of the Domain Access field when it is rendered using a standard entity
 * reference autocomplete widget. The test evaluates default values, filtering
 * capabilities, and behavior for varying levels of permissions.
 */
class DomainAccessDefaultValueTestAutocomplete extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_access', 'field', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Switch the Domain Access field widget to standard entity reference
    // autocomplete. With unlimited cardinality this renders an indexed
    // [0][target_id] and an "Add another item" control.
    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $display */
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.page.default');
    if ($display) {
      $display->setComponent('field_domain_access', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 40,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])->save();
    }

    $admin_user = $this->drupalCreateUser([
      'bypass node access',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer domains',
      'publish to any domain',
    ]);
    $this->drupalLogin($admin_user);

    // Create 2 domains.
    $this->domainCreateTestDomains(2);

    // Ensure that for the standard 'page' bundle, the Domain Access field
    // defaults to both created domains.
    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $domain_ids = array_keys($domains);
    /** @var \Drupal\field\Entity\FieldConfig|null $field_domain_access */
    $field_domain_access = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->load('node.page.field_domain_access');
    if ($field_domain_access) {
      $default = [];
      foreach ($domain_ids as $id) {
        $default[] = ['target_id' => $id];
      }
      $field_domain_access->setDefaultValue($default)->save();
    }
  }

  /**
   * Test default value behavior with autocomplete widget.
   */
  public function testDefaultValueFilteringWithAutocomplete(): void {
    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = array_values(\Drupal::entityTypeManager()->getStorage('domain')->loadMultiple());
    $domain_a = $domains[0];
    $domain_b = $domains[1];

    // Admin should see the domain access autocomplete widget with both domains.
    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('field_domain_access[0][target_id]', $domain_a->label() . ' (' . $domain_a->id() . ')');
    $this->assertSession()->fieldValueEquals('field_domain_access[1][target_id]', $domain_b->label() . ' (' . $domain_b->id() . ')');
    // A third empty field should exist as cardinality is unlimited.
    $this->assertSession()->fieldValueEquals('field_domain_access[2][target_id]', '');

    // Create Editor limited to a single domain (no global publish permission).
    $editor = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit own page content',
      'view domain entity',
      'publish to any assigned domain',
    ]);
    // Assign editor to domain A.
    $this->addDomainsToEntity('user', $editor->id(), [$domain_a], DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    // Editor should only see domain A in the domain access autocomplete widget.
    $this->drupalLogin($editor);
    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('field_domain_access[0][target_id]', $domain_a->label() . ' (' . $domain_a->id() . ')');
    // The second autocomplete field should be empty.
    $this->assertSession()->fieldValueEquals('field_domain_access[1][target_id]', '');
    // There should not be a third autocomplete field.
    $this->assertSession()->fieldNotExists('field_domain_access[2][target_id]');

  }

}
