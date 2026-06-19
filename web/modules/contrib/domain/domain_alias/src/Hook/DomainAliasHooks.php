<?php

namespace Drupal\domain_alias\Hook;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainStorageInterface;
use Drupal\domain_alias\DomainAliasStorageInterface;
use Drupal\domain_alias\Utility\DomainAliasPatternResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for domain_alias.
 */
class DomainAliasHooks {

  use StringTranslationTrait;

  /**
   * Cache of aliases keyed by domain ID and environment.
   *
   * @var array
   */
  protected array $aliasesCache = [];

  /**
   * Cache of resolved patterns keyed by hostname and alias.
   *
   * @var array
   */
  protected array $patternCache = [];

  /**
   * Cache of sibling domain IDs keyed by canonical hostname.
   *
   * @var array
   */
  protected array $siblingsCache = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainNegotiatorInterface $negotiator,
    protected LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(param: 'domain.path_prefix')]
    protected bool $pathPrefixEnabled = FALSE,
  ) {}

  /**
   * Implements hook_domain_request_alter().
   */
  #[Hook('domain_request_alter')]
  public function domainRequestAlter(DomainInterface &$domain) {
    // During installation the entity definition is not yet added.
    if (!$this->entityTypeManager->hasDefinition('domain_alias')) {
      return;
    }

    // If an exact match has loaded, do nothing.
    if ($domain->getMatchType() === DomainNegotiatorInterface::DOMAIN_MATCHED_EXACT) {
      return;
    }
    // If no exact match, then run the alias load routine.
    $hostname = $domain->getHostname();
    /** @var \Drupal\domain_alias\DomainAliasStorageInterface $alias_storage */
    $alias_storage = $this->entityTypeManager->getStorage('domain_alias');
    /** @var \Drupal\domain_alias\DomainAliasInterface $alias */
    $alias = $alias_storage->loadByHostname($hostname);

    if (!is_null($alias)) {
      /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
      $domain_storage = $this->entityTypeManager->getStorage('domain');
      // Load the alias target to get its hostname, then load all
      // domains sharing that hostname for prefix disambiguation.
      $target = $domain_storage->load($alias->getDomainId());
      if ($target instanceof DomainInterface) {
        if ($this->pathPrefixEnabled) {
          $candidates = $domain_storage->loadMultipleByHostname($target->getHostname());
          $domain = $this->negotiator->negotiateByPathPrefix($candidates) ?? $target;
        }
        else {
          $domain = $target;
        }
        $domain->addProperty('alias', $alias);
        $domain->setMatchType(DomainNegotiatorInterface::DOMAIN_MATCHED_ALIAS);
        $redirect = $alias->getRedirect();
        if ($redirect > 0) {
          $domain->setRedirect($redirect);
          if ($alias->getEnvironment() !== 'default') {
            // Find the first non-redirect alias for that
            // domain environment.
            $aliases = $alias_storage->loadByEnvironmentMatch($domain, $alias->getEnvironment());
            $no_redirect_aliases = array_filter($aliases, function ($alias) {
              return !($alias->getRedirect() > 0);
            });
            $no_redirect_alias = reset($no_redirect_aliases);
            if ($no_redirect_alias) {
              $active_hostname = $this->negotiator->getHttpHost();
              $active_alias_pattern = $alias->getPattern();
              if (
                $pattern = DomainAliasPatternResolver::resolveAliasPattern(
                  $active_hostname,
                  $active_alias_pattern,
                  $domain->getCanonical(),
                  $no_redirect_alias->getPattern(),
                )
              ) {
                DomainAliasPatternResolver::applyAliasPatternToDomain($domain, $pattern);
              }
            }
          }
        }
        elseif ($alias->getEnvironment() !== 'default') {
          // Rewrite the domain hostname to the alias
          // hostname so the active domain in the
          // negotiation context has the correct hostname
          // immediately, without waiting for
          // hook_domain_load.
          DomainAliasPatternResolver::applyAliasPatternToDomain(
            $domain, $hostname,
          );
        }
        // Evict domains cached during negotiation so the
        // domainLoad() hook resolves aliases on reload.
        if ($alias->getEnvironment() !== 'default') {
          $domain_storage->resetCache();
        }
      }
      else {
        $this->loggerFactory->get('domain_alias')->error('Found matching alias %alias for host request %hostname, but failed to load matching domain with id %id.', [
          '%alias' => $alias->getPattern(),
          '%hostname' => $hostname,
          '%id' => $alias->getDomainId(),
        ]);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_load() for domain entities.
   *
   * When the active domain was matched via a non-default alias
   * environment (e.g. "local", "staging"), rewrites all loaded
   * domains' hostnames to their environment-specific aliases.
   * Domains sharing the active domain's canonical hostname get
   * the active hostname directly; others are resolved via their
   * own aliases or, in path-prefix mode, via sibling aliases.
   */
  #[Hook('domain_load')]
  public function domainLoad($entities) {
    // Cannot run before the negotiator service has fired.
    $active_domain = $this->negotiator->getActiveDomain();

    // Do nothing if no domain is active or no alias is defined.
    if (is_null($active_domain) || !isset($active_domain->alias)) {
      return;
    }

    // Load and rewrite environment-specific aliases.
    $environment = $active_domain->alias->getEnvironment();
    if ($environment !== 'default') {
      $active_hostname = $this->negotiator->getHttpHost();
      $active_alias_pattern = $active_domain->alias->getPattern();
      /** @var \Drupal\domain_alias\DomainAliasStorageInterface $alias_storage */
      $alias_storage = $this->entityTypeManager
        ->getStorage('domain_alias');
      /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
      $domain_storage = $this->entityTypeManager
        ->getStorage('domain');
      /** @var \Drupal\domain\DomainInterface $domain */
      foreach ($entities as $domain) {
        if ($domain->getCanonical()
            === $active_domain->getCanonical()) {
          // Same canonical hostname as the active domain.
          DomainAliasPatternResolver::applyAliasPatternToDomain(
            $domain, $active_hostname,
          );
        }
        else {
          $this->resolveAliasByEnvironment(
            $domain,
            $environment,
            $active_hostname,
            $active_alias_pattern,
            $alias_storage,
            $domain_storage,
          );
        }
      }
    }
  }

  /**
   * Resolves a domain's hostname via environment aliases.
   *
   * Checks the domain's own aliases first. If path prefixes are
   * enabled and no match is found, falls back to sibling domains
   * sharing the same canonical hostname.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to resolve.
   * @param string $environment
   *   The alias environment (e.g. 'local').
   * @param string $active_hostname
   *   The current request hostname.
   * @param string $active_alias_pattern
   *   The active domain's alias pattern.
   * @param \Drupal\domain_alias\DomainAliasStorageInterface $alias_storage
   *   The alias storage.
   * @param \Drupal\domain\DomainStorageInterface $domain_storage
   *   The domain storage.
   */
  protected function resolveAliasByEnvironment(
    DomainInterface $domain,
    string $environment,
    string $active_hostname,
    string $active_alias_pattern,
    DomainAliasStorageInterface $alias_storage,
    DomainStorageInterface $domain_storage,
  ): void {
    // Try aliases on this domain first.
    if ($this->matchAliases(
      $domain->id(),
      $domain->getCanonical(),
      $environment,
      $active_hostname,
      $active_alias_pattern,
      $alias_storage,
      $domain,
    )) {
      return;
    }

    // Sibling fallback: a prefixed domain without its own alias
    // inherits from its unprefixed sibling. Only query siblings
    // by entity ID (not loadMultiple) to avoid hook recursion.
    if (!$this->pathPrefixEnabled) {
      return;
    }

    $canonical = $domain->getCanonical();
    if (!isset($this->siblingsCache[$canonical])) {
      $this->siblingsCache[$canonical] = $domain_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('hostname', $canonical)
        ->execute();
    }
    $sibling_ids = $this->siblingsCache[$canonical];
    unset($sibling_ids[$domain->id()]);

    foreach ($sibling_ids as $sibling_id) {
      if ($this->matchAliases(
        $sibling_id,
        $canonical,
        $environment,
        $active_hostname,
        $active_alias_pattern,
        $alias_storage,
        $domain,
      )) {
        return;
      }
    }
  }

  /**
   * Matches aliases for a domain ID in an environment.
   *
   * @param string $domain_id
   *   The domain entity ID whose aliases to check.
   * @param string $canonical
   *   The canonical hostname of the domain.
   * @param string $environment
   *   The alias environment.
   * @param string $active_hostname
   *   The current request hostname.
   * @param string $active_alias_pattern
   *   The active domain's alias pattern.
   * @param \Drupal\domain_alias\DomainAliasStorageInterface $alias_storage
   *   The alias storage.
   * @param \Drupal\domain\DomainInterface $target_domain
   *   The domain to apply the alias to if matched.
   *
   * @return bool
   *   TRUE if an alias matched and was applied.
   */
  protected function matchAliases(
    string $domain_id,
    string $canonical,
    string $environment,
    string $active_hostname,
    string $active_alias_pattern,
    DomainAliasStorageInterface $alias_storage,
    DomainInterface $target_domain,
  ): bool {
    $alias_cache_key = $domain_id . ':' . $environment;
    if (!isset($this->aliasesCache[$alias_cache_key])) {
      $aliases = $alias_storage->loadByProperties([
        'domain_id' => $domain_id,
        'environment' => $environment,
      ]);
      uasort($aliases, [ConfigEntityBase::class, 'sort']);
      $this->aliasesCache[$alias_cache_key] = $aliases;
    }

    foreach ($this->aliasesCache[$alias_cache_key] as $alias) {
      $pattern_cache_key = $active_hostname
        . ':' . $alias->id();
      if (!isset($this->patternCache[$pattern_cache_key])) {
        $this->patternCache[$pattern_cache_key] =
          DomainAliasPatternResolver::resolveAliasPattern(
            $active_hostname,
            $active_alias_pattern,
            $canonical,
            $alias->getPattern(),
          );
      }
      $pattern = $this->patternCache[$pattern_cache_key];
      if ($pattern) {
        DomainAliasPatternResolver::applyAliasPatternToDomain(
          $target_domain, $pattern,
        );
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Implements hook_domain_operations().
   */
  #[Hook('domain_operations')]
  public function domainOperations(DomainInterface $domain, AccountInterface $account) {
    $operations = [];
    $is_domain_admin = $domain->access('update', $account);
    if ($account->hasPermission('administer domain aliases') || ($is_domain_admin && $account->hasPermission('view domain aliases'))) {
      $operations['domain_alias'] = [
        'title' => $this->t('Aliases'),
        'url' => Url::fromRoute('domain_alias.admin', ['domain' => $domain->id()]),
        'weight' => 60,
      ];
    }
    return $operations;
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for domain entities.
   */
  #[Hook('domain_delete')]
  public function domainDelete(EntityInterface $entity) {
    $alias_storage = $this->entityTypeManager->getStorage('domain_alias');
    $properties = ['domain_id' => $entity->id()];
    foreach ($alias_storage->loadByProperties($properties) as $alias) {
      $alias->delete();
    }
  }

}
