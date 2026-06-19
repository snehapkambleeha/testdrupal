<?php

namespace Drupal\domain;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Site\Settings;

/**
 * Redirect that recognizes domain URLs as local to the installation.
 *
 * This class can be used in cases where LocalRedirectResponse needs to be
 * domain sensitive. The main implementation is in
 * DomainRedirectResponseSubscriber.
 */
class DomainRedirectResponse extends LocalRedirectResponse {

  /**
   * The trusted host patterns.
   *
   * @var array
   */
  protected static $trustedHostPatterns;

  /**
   * The trusted hosts matched by the settings.
   *
   * @var array
   */
  protected static $trustedHosts;

  /**
   * {@inheritdoc}
   */
  protected function isLocal($url) {
    return !UrlHelper::isExternal($url) || self::externalIsRegistered($url, $this->getRequestContext()->getCompleteBaseUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function isSafe($url) {
    return $this->isLocal($url);
  }

  /**
   * Determines if an external URL points to this domain-aware installation.
   *
   * This method replaces the logic in
   * Drupal\Component\Utility\UrlHelper::externalIsLocal(). Since that class is
   * not directly extendable, we have to replace it.
   *
   * @param string $url
   *   A string containing an external URL, such as "http://example.com/foo".
   * @param string $base_url
   *   The base URL string to check against, such as "http://example.com/".
   *
   * @return bool
   *   TRUE if the URL has the same domain and base path.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when $url is not fully qualified.
   */
  public static function externalIsRegistered($url, $base_url) {
    // Some browsers treat \ as / so normalize to forward slashes.
    $url = str_replace('\\', '/', $url);

    // Leading control characters may be ignored or mishandled by browsers, so
    // assume such a path may lead to a non-local location. The \p{C} character
    // class matches all UTF-8 control, unassigned, and private characters.
    if (preg_match('/^\p{C}/u', $url) !== 0) {
      return FALSE;
    }

    $url_parts = parse_url($url);
    $base_parts = parse_url($base_url);

    if (empty($base_parts['host']) || empty($url_parts['host'])) {
      throw new \InvalidArgumentException('A path was passed when a fully qualified domain was expected.');
    }

    // Check if the requested url host is local.
    $local_host = $url_parts['host'] == $base_parts['host'];

    if (!$local_host) {
      // Check that the host name is registered with trusted hosts.
      $trusted = self::checkTrustedHost($url_parts['host']);
      if (!$trusted) {
        return FALSE;
      }

      // Check that the requested $url domain is registered.
      // @phpstan-ignore-next-line
      $negotiator = \Drupal::service('domain.negotiator');
      if (!$negotiator->isRegisteredDomain($url_parts['host'])) {
        return FALSE;
      }
    }

    if (!isset($url_parts['path'], $base_parts['path'])) {
      return (!isset($base_parts['path']) || $base_parts['path'] === '/');
    }
    // When comparing base paths, we need a trailing slash to make sure a
    // partial URL match isn't occurring. Since base_path() always returns
    // with a trailing slash, we don't need to add the trailing slash here.
    return stripos($url_parts['path'], $base_parts['path']) === 0;
  }

  /**
   * Checks that a host is registered with trusted_host_patterns.
   *
   * This method is cribbed from Symfony's Request::getHost() method.
   *
   * @param string $host
   *   The hostname to check.
   *
   * @return bool
   *   TRUE if the hostname matches the trusted_host_patterns. FALSE otherwise.
   *   It is the caller's responsibility to deal with this result securely.
   */
  public static function checkTrustedHost($host) {
    // See Request::setTrustedHosts();
    if (!isset(self::$trustedHostPatterns)) {
      self::$trustedHostPatterns = array_map(function ($hostPattern) {
          return sprintf('#%s#i', $hostPattern);
      }, Settings::get('trusted_host_patterns', []));
      // Reset the trusted host match array.
      self::$trustedHosts = [];
    }

    // Trim and remove port number from host. Host is lowercase as per RFC
    // 952/2181.
    $host = mb_strtolower(preg_replace('/:\d+$/', '', trim($host)));

    // In the original Symfony code, hostname validation runs here. We have
    // removed that portion because Domains are already validated on creation.
    if (count(self::$trustedHostPatterns) > 0) {
      // To avoid host header injection attacks, you should provide a list of
      // trusted host patterns.
      if (in_array($host, self::$trustedHosts, TRUE)) {
        return TRUE;
      }
      foreach (self::$trustedHostPatterns as $pattern) {
        if (preg_match($pattern, $host)) {
          self::$trustedHosts[] = $host;
          return TRUE;
        }
      }
      return FALSE;
    }
    // In cases where trusted_host_patterns are not set, allow all. This is
    // flagged as a security issue by Drupal core in the Reports UI.
    return TRUE;
  }

}
