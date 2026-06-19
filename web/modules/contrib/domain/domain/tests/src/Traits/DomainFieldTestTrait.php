<?php

namespace Drupal\Tests\domain\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Trait for testing domain fields.
 */
trait DomainFieldTestTrait {

  /**
   * Creates a simple field for testing on the article content type.
   *
   * Note: This code is a model for auto-creation of fields.
   */
  public function domainCreateDomainReferenceFieldOnArticle() {
    $label = 'domain';
    $name = 'field_' . $label;

    $storage = [
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'domain',
      ],
    ];

    FieldStorageConfig::create($storage)->save();

    $field = [
      'field_name' => $name,
      'entity_type' => 'node',
      'label' => 'Domain test field',
      'bundle' => 'article',
      'settings' => [
        'handler_settings' => [
          'sort' => ['field' => 'weight', 'direction' => 'ASC'],
        ],
      ],
    ];

    FieldConfig::create($field)->save();

    // Add a display configuration.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article', 'default')
      ->setComponent($name, [
        'type' => 'entity_reference_label',
        'label' => 'above',
      ])
      ->save();

    // Tell the form system how to behave.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article', 'default')
      ->setComponent($name, [
        'type' => 'options_buttons',
      ])
      ->save();
  }

}
