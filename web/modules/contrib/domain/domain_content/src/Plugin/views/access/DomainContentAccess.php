<?php

namespace Drupal\domain_content\Plugin\views\access;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain_access\Plugin\views\access\DomainAccessContent;
use Drupal\views\Attribute\ViewsAccess;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides domain-editing access control.
 *
 * These access controls extend those provided by Domain Access, merely adding
 * an additional permission specific to this module.
 */
#[ViewsAccess(
  id: 'domain_content_editor',
  title: new TranslatableMarkup('Domain Content: View domain-specific content'),
  help: new TranslatableMarkup('Access will be granted to domains on which the user may edit content.')
)]
class DomainContentAccess extends DomainAccessContent {

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    parent::alterRouteDefinition($route);
    $route->setRequirement('_permission', 'access domain content');
  }

}
