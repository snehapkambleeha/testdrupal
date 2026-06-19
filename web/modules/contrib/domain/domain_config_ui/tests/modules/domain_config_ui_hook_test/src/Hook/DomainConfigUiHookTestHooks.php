<?php

namespace Drupal\domain_config_ui_hook_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for domain_config_ui_hook_test.
 */
class DomainConfigUiHookTestHooks {

  /**
   * Implements hook_domain_config_ui_disallowed_routes_alter().
   */
  #[Hook('domain_config_ui_disallowed_routes_alter')]
  public function domainConfigUiDisallowedRoutesAlter(&$disallowed) {
    $disallowed[] = 'entity.user.admin_form';
  }

}
