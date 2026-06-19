<?php

namespace Drupal\domain_access\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;

/**
 * Assigns a node to a domain.
 */
#[Action(
  id: 'domain_access_add_action',
  label: new TranslatableMarkup('Add domain to content'),
  type: 'node',
)]
class DomainAccessAdd extends DomainAccessActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $save = FALSE;
    $values = [];
    if ($entity) {
      $ids = $this->configuration['domain_id'];
      $existing_values = DomainAccessManager::getAccessValues($entity);
      $values = $existing_values;
      foreach ($ids as $domain_id) {
        if (!isset($existing_values[$domain_id])) {
          $save = TRUE;
          $values[$domain_id] = $domain_id;
        }
      }
    }
    if ($save) {
      $entity->set(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD, array_keys($values));
      $entity->save();
    }
  }

}
