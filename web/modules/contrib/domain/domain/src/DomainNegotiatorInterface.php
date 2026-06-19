<?php

namespace Drupal\domain;

/**
 * Handles the negotiation of the active domain record.
 */
interface DomainNegotiatorInterface {

  /**
   * Defines record matching types when dealing with request alteration.
   *
   * These constants are designed to help modules know how to react to a
   * domain record match, since an exact match is more important than a pattern
   * match.
   *
   * @see hook_domain_request_alter().
   *
   * No matching record found.
   */
  const DOMAIN_MATCHED_NONE = 0;

  /**
   * An exact domain record string match found.
   */
  const DOMAIN_MATCHED_EXACT = 1;

  /**
   * An alias pattern match found.
   */
  const DOMAIN_MATCHED_ALIAS = 2;

  /**
   * Determines the active domain request.
   *
   * The negotiator is passed an httpHost value, which is checked against domain
   * records for a match.
   *
   * @param string $hostname
   *   A string representing the hostname of the request (e.g. example.com).
   * @param bool $reset
   *   Indicates whether to reset the internal cache.
   */
  public function setRequestDomain($hostname, $reset = FALSE);

  /**
   * Sets the active domain.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   Sets the domain record as active for the duration of that request.
   */
  public function setActiveDomain(DomainInterface $domain);

  /**
   * Sets the normalized hostname of the active request.
   *
   * @param string $hostname
   *   The normalized hostname.
   */
  public function setHttpHost($hostname);

  /**
   * Gets the normalized hostname of the active request.
   *
   * @return string
   *   The normalized hostname.
   */
  public function getHttpHost();

  /**
   * Gets the id of the active domain.
   *
   * @return string
   *   The id of the active domain, empty string if it cannot be determined.
   *
   * @see \Drupal\domain\DomainNegotiatorInterface::getActiveDomain()
   */
  public function getActiveId();

  /**
   * Sets the hostname of the active request.
   *
   * This method is an internal method for use by the public getActiveDomain()
   * call. It is responsible for determining the active hostname of the request
   * and then passing that data to the negotiator.
   *
   * @return string
   *   The hostname, without the "www" if applicable.
   */
  public function negotiateActiveHostname();

  /**
   * Gets the active domain.
   *
   * This method should be called by external classes using the negotiator
   * service.
   *
   * @param bool $reset
   *   Reset the internal cache of the active domain.
   *
   * @return \Drupal\domain\DomainInterface
   *   The active domain object.
   */
  public function getActiveDomain($reset = FALSE);

  /**
   * Indicates if the active domain is being negotiated.
   *
   * @return bool
   *   TRUE if the active domain is being negotiated, FALSE otherwise.
   */
  public function isNegotiating();

  /**
   * Indicates if the active domain has been negotiated.
   *
   * @return bool
   *   TRUE if the active domain has been negotiated, FALSE otherwise.
   */
  public function isNegotiated();

  /**
   * Checks that a URL's hostname is registered as a valid domain or alias.
   *
   * @param string $hostname
   *   A string representing the hostname of the request (e.g. example.com).
   *
   * @return bool
   *   TRUE if a URL's hostname is registered as a valid domain or alias, or
   *   FALSE.
   */
  public function isRegisteredDomain($hostname);

  /**
   * Disambiguates domains sharing a hostname by path prefix.
   *
   * When multiple domains share the same hostname, this method
   * matches the request path against each domain's path prefix.
   * The longest matching prefix wins. If no prefix matches, the
   * domain without a prefix is used as fallback. Returns NULL
   * when no candidate matches the request path at all.
   *
   * @param \Drupal\domain\DomainInterface[] $candidates
   *   All domains sharing the negotiated hostname.
   *
   * @return \Drupal\domain\DomainInterface|null
   *   The matching domain, or NULL if no prefix matches.
   */
  public function negotiateByPathPrefix(array $candidates): ?DomainInterface;

}
