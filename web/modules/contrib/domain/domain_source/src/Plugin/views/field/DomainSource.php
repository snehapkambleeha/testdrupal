<?php

namespace Drupal\domain_source\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * Field handler to present the link an entity on a domain.
 */
#[ViewsField('domain_source')]
class DomainSource extends EntityField {

  /**
   * {@inheritdoc}
   */
  public function getItems(ResultRow $values) {
    $items = parent::getItems($values);
    // Override the default link generator, which wants to send us to the entity
    // page, not the entity we are looking at.
    if (isset($this->options['settings']['link']) && !is_null($this->options['settings']['link'])) {
      foreach ($items as &$item) {
        $object = $item['raw'];
        $entity = $object->getEntity();
        $item['rendered']['#type'] = 'link';
        if (!isset($item['rendered']['#title'])) {
          $item['rendered']['#title'] = $item['rendered']['#plain_text'];
          unset($item['rendered']['#plain_text']);
        }
        $item['rendered']['#url'] = $entity->toUrl();
        unset($item['rendered']['#options']);
      }
      uasort($items, [$this, 'sort']);
    }

    return $items;
  }

  /**
   * Sort the domain list, if possible.
   */
  private function sort($a, $b) {
    $domainA = $a['rendered']['#entity'] ?? 0;
    $domainB = $b['rendered']['#entity'] ?? 0;
    if ($domainA !== 0) {
      return ($domainA->getWeight() > $domainB->getWeight()) ? 1 : 0;
    }
    // We don't have a domain object so sort as best we can.
    return strcmp($a['rendered']['#title'], $b['rendered']['#title']);
  }

}
