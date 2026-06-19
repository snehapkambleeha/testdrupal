<?php

namespace Drupal\domain_access\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainStorageInterface;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\user\UserStorageInterface;
use Drupal\views\Attribute\ViewsAccess;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides domain-editing access control.
 */
#[ViewsAccess(
  id: 'domain_access_editor',
  title: new TranslatableMarkup('Domain Access: Edit domain content'),
  help: new TranslatableMarkup('Access will be granted to domains on which the user may edit content.')
)]
class DomainAccessContent extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = FALSE;

  /**
   * Sets the permission to use when checking access.
   *
   * @var string
   */
  protected $permission = 'publish to any assigned domain';

  /**
   * Sets the permission to use when checking all access.
   *
   * @var string
   */
  protected $allPermission = 'publish to any domain';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DomainStorageInterface $domainStorage,
    protected UserStorageInterface $userStorage,
    protected DomainAccessManagerInterface $manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('domain'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('domain_access.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Domain editor');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    // Users with this permission can see any domain content lists, and it is
    // required to view all affiliates.
    if ($account->hasPermission($this->allPermission)) {
      return TRUE;
    }

    $domain = NULL;
    // The routine below determines what domain (if any) was passed to the View.
    if (isset($this->view->element['#arguments'])) {
      foreach ($this->view->element['#arguments'] as $value) {
        $domain = $this->domainStorage->load($value);
        if ($domain instanceof DomainInterface) {
          break;
        }
      }
    }

    // Domain found, check user permissions.
    if ($domain instanceof DomainInterface) {
      return $this->manager->hasDomainPermissions($account, $domain, [$this->permission]);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $list = [];
    $domains = $this->domainStorage->loadMultiple();
    if (!is_null($domains)) {
      $list = array_keys($domains);
    }
    $list += ['all_affiliates'];
    $route->setRequirement('_domain_access_views', implode('+', $list));
    $route->setDefault('domain_permission', $this->permission);
    $route->setDefault('domain_all_permission', $this->allPermission);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
