<?php

namespace Drupal\domain;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * {@inheritdoc}
 */
class DomainNegotiator implements DomainNegotiatorInterface {

  /**
   * The domain storage class.
   *
   * @var \Drupal\domain\DomainStorageInterface|null
   */
  protected $domainStorage;

  /**
   * The HTTP_HOST value of the request.
   *
   * @var string
   */
  protected $httpHost;

  public function __construct(
    protected RequestStack $requestStack,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainNegotiationContext $context,
    #[Autowire(param: 'domain.path_prefix')]
    protected bool $pathPrefixEnabled = FALSE,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setRequestDomain($hostname, $reset = FALSE) {
    $this->setHttpHost($hostname);
    if ($hostname === NULL) {
      $domain = NULL;
    }
    elseif ($this->pathPrefixEnabled) {
      // Load all domains sharing this hostname and disambiguate
      // by path prefix.
      $candidates = $this->domainStorage()->loadMultipleByHostname($hostname);
      $domain = $this->negotiateByPathPrefix($candidates);
    }
    else {
      $domain = $this->domainStorage()->loadByHostname($hostname);
    }
    if ($domain instanceof DomainInterface) {
      $domain->setMatchType(DomainNegotiatorInterface::DOMAIN_MATCHED_EXACT);
    }
    // If no match, create a base domain for checking. This data
    // is required for hook_domain_request_alter().
    else {
      $values = ['hostname' => $hostname];
      /** @var \Drupal\domain\DomainInterface $domain */
      $domain = $this->domainStorage()->create($values);
      $domain->setMatchType(DomainNegotiatorInterface::DOMAIN_MATCHED_NONE);
    }

    // Now check with modules (like Domain Alias) that register alternate
    // lookup systems with the main module.
    $this->moduleHandler->alter('domain_request', $domain);

    // We must have registered a valid id, else the request made no match.
    if (!is_null($domain->id())) {
      $this->setActiveDomain($domain);
    }
    // Fallback to the default domain if no match.
    else {
      $domain = $this->domainStorage()->loadDefaultDomain();
      if ($domain instanceof DomainInterface) {
        $this->moduleHandler->alter('domain_request', $domain);
        $domain->setMatchType(DomainNegotiatorInterface::DOMAIN_MATCHED_NONE);
        if (!is_null($domain->id())) {
          $this->setActiveDomain($domain);
        }
      }
    }
  }

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
  public function negotiateByPathPrefix(array $candidates): ?DomainInterface {
    if (count($candidates) === 0) {
      return NULL;
    }
    if (count($candidates) === 1) {
      return reset($candidates);
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return NULL;
    }
    $path = $request->getPathInfo();

    // Sort by prefix length descending (longest first).
    usort($candidates, function ($a, $b) {
      return strlen($b->getPathPrefix()) - strlen($a->getPathPrefix());
    });

    foreach ($candidates as $candidate) {
      if ($candidate->getPathPrefix() === '') {
        // Empty prefix is the fallback.
        return $candidate;
      }
      if ($candidate->matchPathPrefix($path) !== FALSE) {
        return $candidate;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveDomain(DomainInterface $domain) {
    $this->context->setDomain($domain);
  }

  /**
   * Determine the active domain.
   */
  protected function negotiateActiveDomain() {
    // Reset the negotiated state.
    $this->context->setNegotiated(FALSE);
    // Required to avoid reentrancy issues with config overrides.
    if (!$this->context->isNegotiating()) {
      $this->context->setNegotiating(TRUE);
      try {
        $hostname = $this->negotiateActiveHostname();
        $this->setRequestDomain($hostname);
        $this->context->setNegotiated(TRUE);
      }
      finally {
        $this->context->setNegotiating(FALSE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveDomain($reset = FALSE) {
    if ($reset || !$this->isNegotiated()) {
      $this->negotiateActiveDomain();
    }
    return $this->context->getDomain();
  }

  /**
   * {@inheritdoc}
   */
  public function isNegotiating() {
    return $this->context->isNegotiating();
  }

  /**
   * {@inheritdoc}
   */
  public function isNegotiated() {
    return $this->context->isNegotiated();
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveId() {
    return $this->context->getDomainId('');
  }

  /**
   * {@inheritdoc}
   */
  public function negotiateActiveHostname() {
    return $this->domainStorage()->createHostname();
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpHost($hostname) {
    $this->httpHost = $hostname;
    $this->context->setHostname($hostname);
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpHost() {
    return $this->context->getHostname();
  }

  /**
   * {@inheritdoc}
   */
  public function isRegisteredDomain($hostname) {
    // Direct hostname match always passes.
    $domain = $this->domainStorage()->loadByHostname($hostname);
    if ($domain instanceof DomainInterface) {
      return TRUE;
    }
    // Check for registered alias matches.
    $values = ['hostname' => $hostname];
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->domainStorage()->create($values);
    $domain->setMatchType(DomainNegotiatorInterface::DOMAIN_MATCHED_NONE);

    // Now check with modules (like Domain Alias) that register alternate
    // lookup systems with the main module.
    $this->moduleHandler->alter('domain_request', $domain);

    // We must have registered a valid id, else the request made no match.
    if (!is_null($domain->id())) {
      return TRUE;
    }
    return FALSE;
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

}
