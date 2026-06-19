<?php

namespace Drupal\domain_alias\Utility;

use Drupal\domain\DomainInterface;

/**
 * Provides a utility class for matching domain alias patterns.
 */
class DomainAliasPatternResolver {

  /**
   * Convert a wildcard pattern to regex.
   */
  public static function patternToRegex(string $pattern): string {
    $escaped = preg_quote($pattern, '/');
    $regex = str_replace('\*', '(.+?)', $escaped);
    return '/^' . $regex . '$/i';
  }

  /**
   * Replace each '*' in a pattern with the corresponding captured match.
   *
   * @param string $pattern
   *   The original pattern containing '*' wildcards.
   * @param array $matches
   *   The list of strings to substitute for each '*'.
   * @param int $start
   *   The index of the first match to substitute.
   *
   * @return string
   *   The pattern with each '*' replaced.
   */
  public static function replaceWildcards(string $pattern, array $matches, int $start = 1): string {
    $index = $start;
    return preg_replace_callback('/\*/', function () use ($matches, &$index) {
      return $matches[$index++] ?? '*';
    }, $pattern);
  }

  /**
   * Resolves an alias pattern based on the active hostname.
   *
   * This method resolves wildcard patterns in domain aliases by replacing
   * wildcards with values captured from the active hostname. It handles
   * port wildcards and prevents domain loops.
   *
   * @param string $active_hostname
   *   The current hostname being accessed.
   * @param string $active_alias_pattern
   *   The pattern of the active domain alias.
   * @param string $canonical_hostname
   *   The domain canonical hostname.
   * @param string $alias_pattern
   *   The domain alias pattern.
   *
   * @return string|false
   *   The resolved pattern string, or FALSE if resolution fails.
   */
  public static function resolveAliasPattern(
    string $active_hostname,
    string $active_alias_pattern,
    string $canonical_hostname,
    string $alias_pattern,
  ) {
    // We always prefer a string match.
    if (substr_count($alias_pattern, '*') === 0) {
      return $alias_pattern;
    }
    else {
      $active_alias_wildcards =
        substr_count($active_alias_pattern, '*');
      if ($active_alias_wildcards > 0) {
        // Do not replace ports unless they are nonstandard. See
        // \Symfony\Component\HttpFoundation\Request\getHttpHost().
        if ((substr_count($alias_pattern, ':*') > 0)
          && (substr_count($active_hostname, ':') === 0)) {
          $alias_pattern = str_replace(':*', '', $alias_pattern);
        }

        // Do a wildcard replacement based on the current host name.
        $regex = static::patternToRegex($active_alias_pattern);
        if (preg_match($regex, $active_hostname, $matches)
          && $active_alias_wildcards === (count($matches) - 1)) {
          $pattern = static::replaceWildcards($alias_pattern, $matches);
          // Do not let the domain loop back on itself.
          if ($pattern !== $canonical_hostname) {
            return $pattern;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Applies a resolved alias pattern to a domain object.
   *
   * This method updates the domain object with the resolved pattern,
   * setting the hostname, canonical value, path, and URL.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain object to modify.
   * @param string $pattern
   *   The resolved pattern to apply to the domain.
   */
  public static function applyAliasPatternToDomain(
    DomainInterface $domain,
    string $pattern,
  ) {
    // Set the canonical from the current hostname.
    $domain->setCanonical();
    // Override the domain hostname. Recompute path and URL
    // since they are cached from the original hostname.
    $domain->setHostname($pattern);
    $domain->setPath();
    $domain->setUrl();
  }

}
