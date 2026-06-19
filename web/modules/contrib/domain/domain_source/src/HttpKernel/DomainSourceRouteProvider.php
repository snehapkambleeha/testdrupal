<?php

namespace Drupal\domain_source\HttpKernel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Routing\RouteProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a route provider for the domain_source module.
 */
class DomainSourceRouteProvider extends RouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRouteCollectionForRequest(Request $request) {
    // Cache both the system path as well as route parameters and matching
    // routes.
    // Warning: The cache is shared with the Core RouteProvider service.
    $cid = $this->getRouteCollectionCacheId($request);
    if ($cached = $this->cache->get($cid)) {
      $this->setRequestPathInfo($request, $cached->data['path']);
      if ($cached->data['routes'] === FALSE) {
        return new RouteCollection();
      }
      return $cached->data['routes'];
    }
    else {
      // Just trim on the right side.
      $path = $request->getPathInfo();
      $path = $path === '/' ? $path : rtrim($request->getPathInfo(), '/');
      $processed_path = $this->pathProcessor->processInbound($path, $request);
      $this->setRequestPathInfo($request, $processed_path);
      // Incoming path processors may also set query parameters.
      $query_parameters = $request->query->all();
      $routes = $this->getRoutesByPath(rtrim($processed_path, '/'));
      $cache_value = [
        'path' => $processed_path,
        'query' => $query_parameters,
        'routes' => $routes->count() === 0 ? FALSE : $routes,
      ];
      $this->cache->set($cid, $cache_value, CacheBackendInterface::CACHE_PERMANENT, ['route_match']);
      return $routes;
    }
  }

  /**
   * Sets the pathInfo property of the Request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $path
   *   The path to set.
   */
  protected function setRequestPathInfo(Request $request, string $path) {
    if ($request->getPathInfo() !== $path) {
      $path_info = new \ReflectionProperty($request, 'pathInfo');
      $path_info->setValue($request, $path);
    }
  }

}
