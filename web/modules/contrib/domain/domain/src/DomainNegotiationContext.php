<?php

namespace Drupal\domain;

/**
 * Provides a context for domain negotiation state.
 *
 * This service stores the state of domain negotiation process and holds
 * the currently active domain.
 */
class DomainNegotiationContext {

  /**
   * The domain record returned by the lookup request.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $domain = NULL;

  /**
   * Indicates that the active domain is being looked up.
   *
   * @var bool
   */
  protected $negotiating = FALSE;

  /**
   * Indicates that the active domain has been negotiated.
   *
   * @var bool
   */
  protected $negotiated = FALSE;

  /**
   * The timestamp of the last negotiation.
   *
   * @var int|null
   */
  protected $timestamp = NULL;

  /**
   * The normalized hostname of the active request.
   *
   * This is the HTTP hostname after normalization (e.g. www.
   * prefix stripped), which may differ from the raw HTTP host.
   *
   * @var string|null
   */
  protected ?string $hostname = NULL;

  /**
   * Sets the negotiating flag.
   *
   * @param bool $negotiating
   *   TRUE if the active domain is being looked up, FALSE otherwise.
   */
  public function setNegotiating($negotiating) {
    $this->negotiating = $negotiating;
  }

  /**
   * Returns TRUE if the active domain is being looked up.
   *
   * @return bool
   *   TRUE if the active domain is being looked up, FALSE otherwise.
   */
  public function isNegotiating() {
    return $this->negotiating;
  }

  /**
   * Sets the negotiated flag.
   *
   * @param bool $negotiated
   *   TRUE if the active domain has been negotiated, FALSE otherwise.
   */
  public function setNegotiated($negotiated) {
    $this->negotiated = $negotiated;
    if ($negotiated) {
      $this->timestamp = time();
    }
  }

  /**
   * Returns TRUE if the active domain has been negotiated.
   *
   * @return bool
   *   TRUE if the active domain has been negotiated, FALSE otherwise.
   */
  public function isNegotiated() {
    return $this->negotiated;
  }

  /**
   * Sets the active domain.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The active domain.
   */
  public function setDomain(DomainInterface $domain) {
    $this->domain = $domain;
  }

  /**
   * Returns the active domain.
   *
   * @return \Drupal\domain\DomainInterface
   *   The active domain.
   */
  public function getDomain() {
    return $this->domain;
  }

  /**
   * Returns TRUE if the active domain is set.
   *
   * @return bool
   *   TRUE if the active domain is set, FALSE otherwise.
   */
  public function hasDomain() {
    return $this->domain !== NULL;
  }

  /**
   * Returns the active domain ID.
   *
   * @param string|null $default
   *   The default domain ID.
   *
   * @return string
   *   The active domain ID.
   */
  public function getDomainId(?string $default = NULL) {
    return $this->domain ? $this->domain->id() : $default;
  }

  /**
   * Returns the timestamp of the last negotiation.
   *
   * @return int|null
   *   The timestamp of the last negotiation.
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

  /**
   * Sets the normalized hostname of the active request.
   *
   * @param string|null $hostname
   *   The normalized hostname, or NULL to clear.
   */
  public function setHostname(?string $hostname): void {
    $this->hostname = $hostname;
  }

  /**
   * Returns the normalized hostname of the active request.
   *
   * @return string|null
   *   The normalized hostname, or NULL if not set.
   */
  public function getHostname(): ?string {
    return $this->hostname;
  }

}
