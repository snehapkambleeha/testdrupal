<?php

namespace Drupal\domain_source\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain_source\DomainSourceElementManagerInterface;

/**
 * Removes the domain source from a node.
 */
#[Action(
  id: 'domain_source_unset_action',
  label: new TranslatableMarkup('Unset domain source value'),
  type: 'node',
)]
class DomainSourceUnset extends DomainSourceActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Remove domain source value.
    if ($entity instanceof ContentEntityInterface) {
      $entity->set(DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD, NULL);
      $entity->save();
    }
    else {
      $this->messenger()->addError($this->t('Content not available.'));
    }
  }

}
