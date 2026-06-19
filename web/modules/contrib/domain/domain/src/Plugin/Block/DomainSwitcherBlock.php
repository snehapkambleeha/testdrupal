<?php

namespace Drupal\domain\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block that links to all domains.
 */
#[Block(
  id: 'domain_switcher_block',
  admin_label: new TranslatableMarkup('Domain switcher (for admins and testing)'),
)]
class DomainSwitcherBlock extends DomainBlockBase {

  /**
   * Overrides \Drupal\block\BlockBase::access().
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = AccessResult::allowedIfHasPermissions($account,
              ['administer domains', 'use domain switcher block'], 'OR');
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Build the output.
   */
  public function build() {
    $active_domain_id = $this->domainNegotiationContext->getDomainId();

    $items = [];
    foreach ($this->domainStorage->loadMultipleSorted() as $domain) {
      $link = (string) $domain->getLink();
      $marker = $domain->status() ? '' : ' * ';
      if ($domain->id() === $active_domain_id) {
        $link = '<em>' . $link . '</em>';
      }
      $items[] = ['#markup' => $link . $marker];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

}
