<?php

namespace Drupal\domain_access\Plugin\views\access;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsAccess;

/**
 * Access plugin that provides domain-editing access control.
 */
#[ViewsAccess(
  id: 'domain_access_admin',
  title: new TranslatableMarkup('Domain Access: Administer domain editors'),
  help: new TranslatableMarkup('Access will be granted to domains on which the user may assign editors.')
)]
class DomainAccessEditor extends DomainAccessContent {

  /**
   * Sets the permission to use when checking access.
   *
   * @var string
   */
  protected $permission = 'assign domain editors';

  /**
   * Sets the permission to use when checking all access.
   *
   * @var string
   */
  protected $allPermission = 'assign editors to any domain';

}
