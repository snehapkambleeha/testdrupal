<?php

namespace Drupal\domain_source\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\domain_source\DomainSourceElementManagerInterface;

/**
 * Views hook implementations for domain_source.
 */
class DomainSourceViewsHooks {

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data) {
    $table = 'node__' . DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD;
    $data[$table][DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD]['field']['id'] = 'domain_source';
    $data[$table][DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD . '_target_id']['filter']['id'] = 'domain_source';
    // Domains are not stored in the database, so remove
    // relationships.
    unset($data[$table][DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD]['relationship']);
  }

}
