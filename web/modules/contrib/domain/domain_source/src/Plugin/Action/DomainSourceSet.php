<?php

namespace Drupal\domain_source\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_source\DomainSourceElementManagerInterface;

/**
 * Assigns a domain source to a node.
 */
#[Action(
  id: 'domain_source_set_action',
  label: new TranslatableMarkup('Set domain source value'),
  type: 'node',
)]
class DomainSourceSet extends DomainSourceActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $id = $this->configuration['domain_id'];
    $valid_source = TRUE;

    // This is only run if Domain Access is present.
    if ($this->moduleHandler->moduleExists('domain_access')) {
      if (!$entity->access('update')) {
        $valid_source = FALSE;
      }
      $node_domains_all = DomainAccessManager::getAllValue($entity);
      $node_domains = DomainAccessManager::getAccessValues($entity);

      if (!isset($node_domains[$id]) && !$node_domains_all) {
        $valid_source = FALSE;
      }
    }

    // Set the domain source value.
    if ($entity instanceof ContentEntityInterface) {
      if ($valid_source) {
        $entity->set(DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD, $id);
        $entity->save();
      }
      else {
        $this->messenger()->addWarning(
          $this->t(
            'Content @cid must be assigned to domain @did before it can be set as the source.',
            ['@cid' => $entity->id(), '@did' => $id]
          )
        );
      }
    }
    else {
      $this->messenger()->addError($this->t('Content not available.'));
    }
  }

}
