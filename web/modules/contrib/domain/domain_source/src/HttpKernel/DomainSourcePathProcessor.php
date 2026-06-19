<?php

namespace Drupal\domain_source\HttpKernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain_source\DomainSourceHelperInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the outbound path using route match lookups.
 */
class DomainSourcePathProcessor implements OutboundPathProcessorInterface {

  /**
   * The cache of processed paths.
   *
   * @var array
   */
  protected static array $cache = [];

  /**
   * An array of content entity types.
   *
   * @var array
   */
  protected $entityTypes;

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface|null
   */
  protected $domainStorage;

  /**
   * An array of routes exclusion settings, keyed by route.
   *
   * @var array
   */
  protected $excludedRoutes;

  /**
   * An array of excluded routes keyed by route name.
   *
   * @var array
   */
  protected $excludedRouteNames;

  /**
   * The excluded paths.
   *
   * @var string
   */
  protected $excludedPaths;

  public function __construct(
    protected DomainNegotiationContext $domainNegotiationContext,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected DomainSourceHelperInterface $sourceHelper,
    protected PathMatcherInterface $pathMatcher,
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {

    // Process only non-empty internal paths.
    if (empty($path) || !empty($options['external'])) {
      return $path;
    }

    // If a domain option is already set, skip source processing.
    if (isset($options['domain'])) {
      return $path;
    }

    if (isset($options['domain_target_id'])) {
      @trigger_error("\$options['domain_target_id'] is deprecated in domain:3.0.0 and will be removed in domain:4.0.0. Set \$options['domain'] to a DomainInterface entity instead. See https://www.drupal.org/project/domain/issues/3574800", E_USER_DEPRECATED);
    }

    // Load the active domain if not set.
    $options['active_domain'] = $options['active_domain'] ?? $this->getActiveDomain();

    // Skip processing if no active domain is available.
    if (!$options['active_domain'] instanceof DomainInterface) {
      return $path;
    }

    // Check that we haven't already processed this path.
    $cache_key = $this->buildCacheKey($path, $options);
    if (isset(static::$cache[$cache_key])) {
      if (static::$cache[$cache_key] === TRUE) {
        // Path and options have not been modified.
        return $path;
      }
      else {
        $options = static::$cache[$cache_key][1];
        return static::$cache[$cache_key][0];
      }
    }

    // Process only if the path is allowed, skip otherwise.
    if (!$this->allowedPath($path)) {
      static::$cache[$cache_key] = TRUE;
      return $path;
    }

    // Extract the route name and parameters from the path using an
    // in-house route matcher until the following core issue is fixed:
    // https://www.drupal.org/project/drupal/issues/3202329
    if (!isset($options['route_name'])) {
      if ($route_info = DomainSourceRouteMatcher::routeMatch($path)) {
        if (isset($route_info['_route'])) {
          $options['route_name'] = $route_info['_route'];
          $options['route_parameters'] = $route_info['_raw_variables'] ?? [];
          if (!isset($options['route'])) {
            $options['route'] = $route_info['_route_object'];
          }
        }
      }
    }

    // Check the route, if available. Entities can be configured to
    // only rewrite specific routes.
    if (isset($options['route_name']) && !$this->allowedRoute($options['route_name'])) {
      static::$cache[$cache_key] = TRUE;
      return $path;
    }

    $entity = NULL;
    if (isset($options['entity'])) {
      $entity = $options['entity'];
    }
    elseif (
      isset($options['route'])
      && isset($options['route_name'])
      && str_starts_with($options['route_name'], 'entity.')
    ) {
      $parameters = $options['route']->getOption('parameters');
      if (!empty($parameters)) {
        // Get the list allowed "content" entity types.
        $allowed_entity_types = $this->getEntityTypes();
        // Loop through the route parameters looking for entity parameter.
        foreach ($parameters as $parameter_name => $parameter_info) {
          $type = $parameter_info['type'] ?? NULL;
          // If not an entity parameter, skip.
          if ($type === NULL || !str_starts_with($type, 'entity:')) {
            continue;
          }
          // Check entity type is allowed.
          $entity_type_id = substr($type, 7);
          if (isset($allowed_entity_types[$entity_type_id])) {
            // Extract entity ID from route parameter values.
            $entity_id = $options['route_parameters'][$parameter_name] ?? NULL;
            if ($entity_id !== NULL) {
              $entity = $this->getEntity($entity_type_id, $entity_id);
            }
          }
          break;
        }
      }
    }

    $source = NULL;
    $langcode = NULL;
    // One hook for entities.
    if ($entity instanceof FieldableEntityInterface) {
      // Determine the current language for translation lookup.
      if (isset($options['language'])) {
        // Use the caller-provided language if valid.
        if ($options['language'] instanceof LanguageInterface) {
          $langcode = $options['language']->getId();
        }
      }
      elseif ($this->languageManager->isMultilingual()) {
        // Fall back to the current content language.
        $langcode = $this->languageManager
          ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
          ->getId();
        // We introduced a dependency on the content language.
        if ($bubbleable_metadata && $entity->getEntityType()->isTranslatable()) {
          $bubbleable_metadata->addCacheContexts([
            'languages:' . LanguageInterface::TYPE_CONTENT,
          ]);
        }
      }
      // Ensure we send the right translation.
      if (
        $langcode !== NULL
        && $entity->getEntityType()->isTranslatable()
        && $entity instanceof TranslatableInterface
        && $entity->hasTranslation($langcode)
        && $translation = $entity->getTranslation($langcode)
      ) {
        $entity = $translation;
      }
      if (isset($options['domain_target_id'])) {
        $target_id = $options['domain_target_id'];
        $source = $this->domainStorage()->load($target_id);
      }
      else {
        $source = $this->sourceHelper->getSourceDomain($entity);
      }
      $options['entity'] = $entity;
      $options['entity_type'] = $entity->getEntityTypeId();
      $this->moduleHandler->alter('domain_source', $source, $path, $options);
    }
    // One for other, because the latter is resource-intensive.
    else {
      if (isset($options['domain_target_id'])) {
        $target_id = $options['domain_target_id'];
        $source = $this->domainStorage()->load($target_id);
      }
      $this->moduleHandler->alter('domain_source_path', $source, $path, $options);
    }

    // Bubble cache metadata: alter hooks may use any entity field
    // to determine the source domain.
    if ($bubbleable_metadata && $entity instanceof FieldableEntityInterface) {
      $bubbleable_metadata->addCacheTags($entity->getCacheTags());
    }

    // If a source domain is specified and does not match the active domain,
    // rewrite the link.
    if (
      $source instanceof DomainInterface
      && $source->getDomainId() !== $options['active_domain']->getDomainId()
    ) {
      // Delegate URL rewriting to the base domain path processor.
      $options['domain'] = $source;
    }

    // Put the potentially modified path and options into the cache.
    static::$cache[$cache_key] = [$path, $options];

    return $path;
  }

  /**
   * Get an entity by its type and ID.
   *
   * Detect loop and return NULL if it happens.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns the entity when available, otherwise NULL.
   */
  public function getEntity(string $entity_type_id, string $entity_id) {
    // Loop protection.
    static $depth = 0;
    $entity = NULL;
    // The max depth of 1 could be increased if needed.
    if ($depth < 1) {
      $depth++;
      try {
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
      }
      finally {
        $depth--;
      }
    }
    return $entity;
  }

  /**
   * Checks that a path is allowed.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if the path is allowed, FALSE otherwise.
   *
   * @see https://www.drupal.org/project/domain/issues/3544347
   */
  protected function allowedPath($path) {
    if (!isset($this->excludedPaths)) {
      $excluded_paths =
        $this->configFactory->get('domain_source.settings')->get('excluded_paths') ?? [];
      $this->moduleHandler->alter('domain_source_excluded_paths', $excluded_paths);
      $this->excludedPaths = implode("\n", array_unique($excluded_paths));
    }
    if (!empty($this->excludedPaths)) {
      return !$this->pathMatcher->matchPath($path, $this->excludedPaths);
    }
    return TRUE;
  }

  /**
   * Checks that a route name is not disallowed.
   *
   * Looks at the name (e.g. canonical) of the route without regard for
   * the entity type.
   *
   * @parameter $route_name
   *   The route name being checked.
   *
   * @return bool
   *   Returns TRUE when allowed, otherwise FALSE.
   */
  public function allowedRoute($route_name) {
    if (isset($this->getExcludedRouteNames()[$route_name])) {
      return FALSE;
    }
    // No need to check for excluded routes if not an entity route.
    if (str_starts_with($route_name, 'entity.')) {
      $excluded = $this->getExcludedRoutes();
      $parts = explode('.', $route_name);
      $suffix = end($parts);
      // Config is stored as an array. Empty items are not excluded.
      return !isset($excluded[$suffix]);
    }
    return TRUE;
  }

  /**
   * Gets an array of content entity types, keyed by type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of content entity types, keyed by type.
   */
  public function getEntityTypes() {
    if (!isset($this->entityTypes)) {
      foreach ($this->entityTypeManager->getDefinitions() as $type => $definition) {
        if ($definition->getGroup() === 'content') {
          $this->entityTypes[$type] = $type;
        }
      }
    }
    return $this->entityTypes;
  }

  /**
   * Gets the settings for domain source path rewrites.
   *
   * @return array
   *   The settings for domain source path rewrites.
   */
  public function getExcludedRoutes() {
    if (!isset($this->excludedRoutes)) {
      $config = $this->configFactory->get('domain_source.settings');
      $excluded_entity_route_suffixes = $config->get('exclude_routes');
      if (is_array($excluded_entity_route_suffixes)) {
        $excluded_entity_route_suffixes[] = 'collection';
        $this->excludedRoutes = array_flip($excluded_entity_route_suffixes);
      }
      else {
        $this->excludedRoutes = [];
      }
    }
    return $this->excludedRoutes;
  }

  /**
   * Gets the list of excluded route names.
   *
   * @return array
   *   The list of excluded route names, keyed by route name.
   */
  public function getExcludedRouteNames() {
    if (!isset($this->excludedRouteNames)) {
      $excluded_route_names =
        $this->configFactory->get('domain_source.settings')->get('excluded_route_names') ?? [];
      $this->moduleHandler->alter('domain_source_excluded_route_names', $excluded_route_names);
      $this->excludedRouteNames = array_flip($excluded_route_names);
    }
    return $this->excludedRouteNames;
  }

  /**
   * Gets the active domain.
   *
   * @return \Drupal\domain\DomainInterface
   *   The active domain.
   */
  public function getActiveDomain() {
    return $this->domainNegotiationContext->getDomain();
  }

  /**
   * Retrieves the domain storage handler.
   *
   * @return \Drupal\domain\DomainStorageInterface
   *   The domain storage handler.
   */
  protected function domainStorage() {
    if (is_null($this->domainStorage)) {
      $this->domainStorage = $this->entityTypeManager->getStorage('domain');
    }

    return $this->domainStorage;
  }

  /**
   * Clears the static cache of processed paths.
   *
   * Used in functional tests after some node updates.
   */
  public function clearCache() {
    static::$cache = [];
  }

  /**
   * Reset the various processor caches.
   *
   * Used in functional tests.
   */
  public function reset() {
    $this->clearCache();
    $this->excludedRoutes = NULL;
    $this->excludedPaths = NULL;
    $this->excludedRouteNames = NULL;
  }

  /**
   * Normalizes the options array for cache key generation.
   *
   * Converts objects in the options array to their IDs if possible,
   * to ensure consistent cache keys.
   *
   * @param array $options
   *   The options array to normalize.
   *
   * @return array
   *   The normalized options array.
   */
  protected function normalizeOptions(array $options) {
    $normalized_options = $options;
    // Remove the route object from the options array as it depends on the path.
    unset($normalized_options['route']);
    array_walk_recursive($normalized_options, function (&$value) {
      if (is_object($value)) {
        if (method_exists($value, 'id')) {
          $value = $value->id();
        }
        elseif (method_exists($value, 'getId')) {
          $value = $value->getId();
        }
      }
    });
    return $normalized_options;
  }

  /**
   * Builds a cache key based on the path and options.
   *
   * Normalizes the options array and generates an MD5 hash to use
   * as a cache key.
   *
   * @param string $path
   *   The path to process.
   * @param array $options
   *   The options array.
   *
   * @return string
   *   The cache key.
   */
  protected function buildCacheKey(string $path, array $options) {
    // Use a fast, non-cryptographic hash with excellent distribution.
    return hash(
      'xxh3',
      $path . "\0" . serialize($this->normalizeOptions($options))
    );
  }

}
