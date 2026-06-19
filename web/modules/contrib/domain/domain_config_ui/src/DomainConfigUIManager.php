<?php

namespace Drupal\domain_config_ui;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain_config_ui\Config\DomainConfigFactory;
use Drupal\user\Entity\User;

/**
 * Domain Config UI manager.
 */
class DomainConfigUIManager implements DomainConfigUIManagerInterface {

  const DOMAIN_CONFIG_UI_DISALLOWED_ROUTES = [
    'domain_config_ui.settings',
    'domain.settings',
  ];

  /**
   * List of route names that should not allow overrides.
   *
   * @var array|null
   */
  protected $disallowedRoutes = NULL;

  /**
   * TRUE if the current page is an admin route.
   *
   * @var bool
   */
  protected $allowedRoute;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RouteMatchInterface $currentRouteMatch,
    protected AdminContext $adminContext,
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountInterface $currentUser,
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowedRoute() {
    if (!isset($this->allowedRoute)) {
      $allowed_route = $this->checkAllowedRoute();
      if (is_bool($allowed_route)) {
        $this->allowedRoute = $allowed_route;
      }
      else {
        // Route probably not yet available.
        return FALSE;
      }
    }
    return $this->allowedRoute;
  }

  /**
   * Checks if the route is allowed and is an admin route.
   *
   * @return bool|null
   *   TRUE if the route is allowed, FALSE otherwise. NULL if undefined.
   */
  protected function checkAllowedRoute() {
    $route_name = $this->currentRouteMatch->getRouteName();
    if (is_null($route_name)) {
      return NULL;
    }
    if (!isset($this->disallowedRoutes)) {
      // Never allow this module's form to be added.
      $this->disallowedRoutes = self::DOMAIN_CONFIG_UI_DISALLOWED_ROUTES;
      // Allow modules to alter the list of disallowed routes.
      $this->moduleHandler->alter('domain_config_ui_disallowed_routes', $this->disallowedRoutes);
    }
    if (in_array($route_name, $this->disallowedRoutes, TRUE)) {
      return FALSE;
    }
    $route = $this->currentRouteMatch->getRouteObject();
    return $this->adminContext->isAdminRoute($route);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowedConfiguration($names):bool {
    if ($this->configFactory instanceof DomainConfigFactory) {
      return $this->configFactory->isAllowedConfiguration($names);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRegisteredConfiguration($names) {
    if ($this->configFactory instanceof DomainConfigFactory) {
      return $this->configFactory->isRegisteredConfiguration($names);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigurationRegisteredForDomain($domain_id, $names) {
    if ($this->configFactory instanceof DomainConfigFactory) {
      return $this->configFactory->isConfigurationRegisteredForDomain($domain_id, $names);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addConfigurationsToDomain($domain_id, $config_names) {
    $config = $this->configFactory->getEditable('domain_config_ui.settings');
    $overridable_configurations = $config->get('overridable_configurations') ?? [];
    foreach ($config_names as $config_name) {
      $already_exist = FALSE;
      $config_index = NULL;
      // Check to see if we already registered this form.
      foreach ($overridable_configurations as $index => $configuration) {
        if ($configuration['name'] === $config_name) {
          $config_index = $index;
          if (in_array($domain_id, $configuration['domains'])) {
            $already_exist = TRUE;
            break;
          }
        }
      }
      if (!$already_exist) {
        if (is_null($config_index)) {
          $overridable_configurations[] = ['name' => $config_name, 'domains' => [$domain_id]];
        }
        else {
          $overridable_configurations[$config_index]['domains'][] = $domain_id;
        }
        $config->set('overridable_configurations', $overridable_configurations);
        $config->save();
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeConfigurationsFromDomain($domain_id, $config_names) {
    $config = $this->configFactory->getEditable('domain_config_ui.settings');
    $overridable_configurations = $config->get('overridable_configurations');
    foreach ($config_names as $config_name) {
      $found = FALSE;
      $config_key = NULL;
      foreach ($overridable_configurations as $key => $configuration) {
        if ($configuration['name'] === $config_name) {
          if (in_array($domain_id, $configuration['domains'])) {
            $config_key = $key;
            $found = TRUE;
          }
          break;
        }
      }
      if ($found) {
        $domain_key = array_search($domain_id, $overridable_configurations[$config_key]['domains']);
        unset($overridable_configurations[$config_key]['domains'][$domain_key]);
        if (empty($overridable_configurations[$config_key]['domains'])) {
          unset($overridable_configurations[$config_key]);
        }
        $config->set('overridable_configurations', $overridable_configurations);
        $config->save();
      }
    }
    return TRUE;
  }

  /**
   * Get the selected domain ID.
   *
   * @return string|null
   *   A domain machine name.
   */
  public function getActiveDomainId() {
    return $this->domainNegotiationContext->getDomainId();
  }

  /**
   * {@inheritdoc}
   */
  public function addConfigurationsToCurrentDomain($config_names) {
    $domain_id = $this->getActiveDomainId();
    return $this->addConfigurationsToDomain($domain_id, $config_names);
  }

  /**
   * {@inheritdoc}
   */
  public function removeConfigurationsFromCurrentDomain($config_names) {
    $domain_id = $this->getActiveDomainId();
    return $this->removeConfigurationsFromDomain($domain_id, $config_names);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteConfigurationOverridesForDomain(mixed $domain_id, mixed $config_name, bool $remove = TRUE) {
    if ($this->configFactory instanceof DomainConfigFactory) {
      $this->configFactory->deleteConfigurationOverridesForDomain($domain_id, $config_name);
      if ($remove) {
        $this->removeConfigurationsFromDomain($domain_id, [$config_name]);
      }
    }
  }

  /**
   * Determines if the current user is an admin of the current domain.
   *
   * @return bool
   *   TRUE if the current user is an admin of the current domain.
   */
  protected function isUserCurrentDomainAdmin() {
    if ($this->currentUser->hasPermission('administer domains')) {
      return TRUE;
    }
    $is_domain_admin = FALSE;
    $user = User::load($this->currentUser->id());
    $field_name = DomainInterface::DOMAIN_ADMIN_FIELD;
    $user_domains = $user->hasField($field_name) ? $user->get($field_name) : NULL;
    if (!empty($user_domains)) {
      foreach ($user_domains as $domain_item) {
        $target = $domain_item->getValue();
        if (isset($target['target_id']) && $target['target_id'] === $this->getActiveDomainId()) {
          $is_domain_admin = TRUE;
          break;
        }
      }
    }
    return $is_domain_admin;
  }

  /**
   * {@inheritdoc}
   */
  public function canAdministerDomainConfig() {
    if ($this->isUserCurrentDomainAdmin()) {
      return $this->currentUser->hasPermission('administer domain config ui');
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function canUseDomainConfig() {
    if ($this->isUserCurrentDomainAdmin()) {
      return $this->currentUser->hasPermission('use domain config ui')
        || $this->currentUser->hasPermission('administer domain config ui');
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function canSetDefaultDomainConfig() {
    return $this->currentUser->hasPermission('set default domain configuration');
  }

  /**
   * {@inheritdoc}
   */
  public function canTranslateDomainConfig() {
    return $this->currentUser->hasPermission('translate domain configuration');
  }

}
