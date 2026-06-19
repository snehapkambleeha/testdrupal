<?php

/**
 * @file
 * Post update functions for the Domain module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\domain\DomainInterface;

/**
 * Add path_prefix property to existing domain records.
 */
function domain_post_update_add_path_prefix(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'domain', function (DomainInterface $domain): bool {
      if ($domain->getPathPrefix() === '') {
        // Re-save to persist the default value in config.
        return TRUE;
      }
      return FALSE;
    });
}
