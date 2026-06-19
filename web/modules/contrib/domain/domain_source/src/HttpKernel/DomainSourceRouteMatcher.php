<?php

namespace Drupal\domain_source\HttpKernel;

use Drupal\Core\Routing\CacheableRouteProviderInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Processes the outbound path using path alias lookups.
 *
 * This contains code extract from Various Drupal core classes, such as
 * Router, UrlMatcher, and ParamConversionEnhancer, but modified to avoid
 * unnecessary parameter enhancement that can cause loop issues.
 *
 * This class won't be necessary as soon as the following core issue is
 * resolved, as it will give direct access to the route name and route
 * parameters to the path processor:
 *
 * @see https://www.drupal.org/project/drupal/issues/3202329
 */
class DomainSourceRouteMatcher {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface|null
   */
  protected static mixed $routeProvider;

  /**
   * The expression language instance.
   *
   * @var mixed
   */
  protected static $expressionLanguage;

  /**
   * Returns the route provider service.
   *
   * This is a workaround to avoid the service being injected in the
   * path processor constructor, which would cause a circular dependency.
   *
   * @return \Drupal\Core\Routing\RouteProviderInterface
   *   The route provider service.
   */
  protected static function getRouteProvider() {
    if (!isset(static::$routeProvider)) {
      static::$routeProvider = \Drupal::service('domain_source.route_provider');
      if (static::$routeProvider instanceof CacheableRouteProviderInterface) {
        $domain_id = \Drupal::service('domain.negotiation_context')->getDomainId('und');
        static::$routeProvider->addExtraCacheKeyPart('domain', $domain_id);
      }
    }
    return static::$routeProvider;
  }

  /**
   * Matches a path against the route collection.
   *
   * Code taken from the Router class in Drupal core, but modified to
   * avoid unnecessary parameter enhancement that cause loop issues.
   *
   * @param string $path
   *   The path to match against the route collection.
   */
  public static function routeMatch(string $path): ?array {
    try {
      $request = Request::create($path);
    }
    catch (BadRequestException) {
      return NULL;
    }
    $context = new RequestContext();
    $context->fromRequest($request);
    $routes = static::getRouteProvider()->getRouteCollectionForRequest($request);
    // Try a case-sensitive match.
    $match = static::doMatchCollection($request, $context, $routes, TRUE);
    // Try a case-insensitive match.
    if ($match === NULL && $routes->count() > 0) {
      $match = static::doMatchCollection($request, $context, $routes, FALSE);
    }
    if ($match !== NULL) {
      // Copy the raw variables from the route defaults.
      $match['_raw_variables'] = static::copyRawVariables($match);
    }
    return $match;
  }

  /**
   * Tries to match a URL with a set of routes.
   *
   * This code is very similar to Symfony's UrlMatcher::matchCollection() but it
   * supports case-insensitive matching. The static prefix optimization is
   * removed as this duplicates work done by the query in
   * RouteProvider::getRoutesByPath().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The path info to be parsed.
   * @param \Drupal\Core\Routing\RequestContext $context
   *   The request context.
   * @param \Symfony\Component\Routing\RouteCollection $routes
   *   The set of routes.
   * @param bool $case_sensitive
   *   Determines if the match should be case-sensitive of not.
   *
   * @return array|null
   *   An array of parameters. NULL when there is no match.
   *
   * @see \Symfony\Component\Routing\Matcher\UrlMatcher::matchCollection()
   * @see \Drupal\Core\Routing\RouteProvider::getRoutesByPath()
   */
  protected static function doMatchCollection(
    Request $request,
    RequestContext $context,
    RouteCollection $routes,
    $case_sensitive,
  ) {
    $path_info = $request->getPathInfo();
    foreach ($routes as $name => $route) {
      $compiledRoute = $route->compile();

      // Set the regex to use UTF-8.
      $regex = $compiledRoute->getRegex() . 'u';
      if (!$case_sensitive) {
        $regex = $regex . 'i';
      }
      if (!preg_match($regex, $path_info, $matches)) {
        continue;
      }

      $hostMatches = [];
      if ($compiledRoute->getHostRegex() && !preg_match($compiledRoute->getHostRegex(), $context->getHost(), $hostMatches)) {
        $routes->remove($name);
        continue;
      }

      // Check HTTP method requirement.
      if ($requiredMethods = $route->getMethods()) {
        if (!in_array('GET', $requiredMethods)) {
          $routes->remove($name);
          continue;
        }
      }

      $attributes = static::getRouteAttributes($route, $name, array_replace($matches, $hostMatches));

      $status = static::handleRouteRequirements($request, $context, $route, $attributes);

      if (UrlMatcher::ROUTE_MATCH === $status[0]) {
        return $status[1];
      }

      if (UrlMatcher::REQUIREMENT_MISMATCH === $status[0]) {
        $routes->remove($name);
        continue;
      }

      return $attributes;
    }
    return NULL;
  }

  /**
   * Get the route attributes.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to get attributes from.
   * @param string $name
   *   The name of the route.
   * @param array $attributes
   *   The attributes to merge with the route defaults.
   */
  protected static function getRouteAttributes(Route $route, $name, array $attributes): array {
    // @phpstan-ignore-next-line
    if ($route instanceof RouteObjectInterface && is_string($route->getRouteKey())) {
      // @phpstan-ignore-next-line
      $name = $route->getRouteKey();
    }
    $attributes[RouteObjectInterface::ROUTE_NAME] = $name;
    $attributes[RouteObjectInterface::ROUTE_OBJECT] = $route;

    return static::mergeDefaults($attributes, $route->getDefaults());
  }

  /**
   * Handles specific route requirements.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Routing\RequestContext $context
   *   The request context.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check requirements against.
   * @param array $routeParameters
   *   The route parameters to check against the requirements.
   *
   * @return array
   *   The first element represents the status,
   *   the second contains additional information.
   */
  protected static function handleRouteRequirements(
    Request $request,
    RequestContext $context,
    Route $route,
    array $routeParameters,
  ): array {
    // Expression condition.
    // @phpstan-ignore-next-line
    if ($route->getCondition() && !static::getExpressionLanguage()->evaluate($route->getCondition(), [
      'context' => $context,
      'request' => $request,
      'params' => $routeParameters,
    ])) {
      return [UrlMatcher::REQUIREMENT_MISMATCH, NULL];
    }

    return [UrlMatcher::REQUIREMENT_MATCH, NULL];
  }

  /**
   * Get merged default parameters.
   *
   * @param array $params
   *   The parameters to merge with the defaults.
   * @param array $defaults
   *   The default parameters to merge with the provided parameters.
   */
  protected static function mergeDefaults(array $params, array $defaults): array {
    foreach ($params as $key => $value) {
      if (!\is_int($key) && NULL !== $value) {
        $defaults[$key] = $value;
      }
    }
    return $defaults;
  }

  /**
   * Get the expression language instance.
   */
  protected static function getExpressionLanguage() {
    if (!isset(static::$expressionLanguage)) {
      if (!class_exists(ExpressionLanguage::class)) {
        throw new \LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
      }
      static::$expressionLanguage = new ExpressionLanguage(NULL, []);
    }
    return static::$expressionLanguage;
  }

  /**
   * Store a backup of the raw values that corresponding to the route pattern.
   *
   * @param array $defaults
   *   The route defaults array.
   *
   * @return array
   *   The input bag container with the raw variables.
   */
  protected static function copyRawVariables(array $defaults) {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    $variables = array_flip($route->compile()->getVariables());
    // Foreach will copy the values from the array it iterates. Even if they
    // are references, use it to break them. This avoids any scenarios where raw
    // variables also get replaced with converted values.
    $raw_variables = [];
    foreach (array_intersect_key($defaults, $variables) as $key => $value) {
      $raw_variables[$key] = $value;
    }
    // Route defaults that do not start with a leading "_" are also
    // parameters, even if they are not included in path or host patterns.
    foreach ($route->getDefaults() as $name => $value) {
      if (!isset($raw_variables[$name]) && !str_starts_with($name, '_')) {
        $raw_variables[$name] = $value;
      }
    }
    return $raw_variables;
  }

}
