<?php

namespace Drupal\domain\HttpKernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rewrites outbound URLs that carry a "domain" option.
 *
 * Any code may target a specific domain on a Url object:
 * @code
 *   $url->setOption('domain', $domainEntity);
 * @endcode
 *
 * The option value must be a DomainInterface entity, similar
 * to how core's "language" option requires a LanguageInterface.
 */
class DomainPathProcessor implements OutboundPathProcessorInterface {

  /**
   * Indicates whether language negotiation is enabled.
   *
   * @var bool|null
   */
  protected ?bool $languageNegotiationEnabled = NULL;

  /**
   * The LanguageNegotiationUrl plugin instance.
   *
   * @var \Drupal\Core\PathProcessor\OutboundPathProcessorInterface|null
   */
  protected ?OutboundPathProcessorInterface $languageNegotiationUrl = NULL;

  /**
   * Cache for isSameLanguageNegotiationConfig results.
   *
   * @var array
   */
  protected array $sameLanguageNegotiationConfig = [];

  /**
   * Whether the destination domain functionality is enabled.
   *
   * @var bool|null
   */
  protected ?bool $destinationDomainEnabled = NULL;

  /**
   * Constructs a DomainPathProcessor object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\domain\DomainNegotiationContext $domainNegotiationContext
   *   The domain negotiation context.
   * @param \Drupal\language\LanguageNegotiatorInterface|null $languageNegotiator
   *   The language negotiator, or NULL if not available.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DomainNegotiationContext $domainNegotiationContext,
    protected ?LanguageNegotiatorInterface $languageNegotiator = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    // Skip external URLs — they already have a host.
    if (!empty($options['external'])) {
      return $path;
    }

    // Nothing to do without a domain option.
    if (!isset($options['domain'])) {
      return $path;
    }

    // The domain option must be a DomainInterface entity.
    $domain = $options['domain'];
    if (!$domain instanceof DomainInterface) {
      return $path;
    }

    // Determine the active domain for cross-domain features.
    $active_domain = $options['active_domain']
      ?? $this->domainNegotiationContext->getDomain();

    if (
      $active_domain instanceof DomainInterface
      && $active_domain->getDomainId() !== $domain->getDomainId()
    ) {
      // Re-process language negotiation.
      if (
        $this->isLanguageNegotiationEnabled()
        && !$this->isSameLanguageNegotiationConfig(
          $active_domain, $domain)
      ) {
        $this->processLanguageNegotiationOutbound(
          $domain, $path, $options, $request, $bubbleable_metadata
        );
      }

      // Process destination parameter.
      if ($request && $this->isDestinationDomainEnabled()) {
        $this->processDestinationParameter($options, $request);

        // The destination parameter includes the current domain's
        // host, so the output varies by domain.
        if ($bubbleable_metadata) {
          $bubbleable_metadata->addCacheContexts(['domain']);
        }
      }
    }

    // Rewrite to the target domain.
    // Use getBasePath() (without prefix) because the prefix
    // is added by DomainPrefixPathProcessor.
    $options['base_url'] = rtrim($domain->getBasePath(), '/');
    $options['absolute'] = TRUE;

    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheableDependency($domain);
    }

    return $path;
  }

  /**
   * Determines if language negotiation is enabled.
   *
   * Evaluates service availability and configuration settings
   * to determine if language negotiation is functional.
   *
   * @return bool
   *   Returns TRUE if language negotiation is enabled.
   */
  protected function isLanguageNegotiationEnabled(): bool {
    if ($this->languageNegotiationEnabled === NULL) {
      $this->languageNegotiationEnabled =
        $this->languageNegotiator
        && $this->configFactory
          ->get('domain.settings')
          ->get('language_negotiation');

      if ($this->languageNegotiationEnabled) {
        $language_negotiator_url = $this->languageNegotiator
          ->getNegotiationMethodInstance('language-url');
        if ($language_negotiator_url instanceof OutboundPathProcessorInterface) {
          $this->languageNegotiationUrl = $language_negotiator_url;
        }
        else {
          $this->languageNegotiationEnabled = FALSE;
          $this->languageNegotiationUrl = NULL;
        }
      }
    }
    return $this->languageNegotiationEnabled;
  }

  /**
   * Compares language negotiation config between two domains.
   *
   * @param \Drupal\domain\DomainInterface $active_domain
   *   The active domain entity.
   * @param \Drupal\domain\DomainInterface $target_domain
   *   The target domain entity.
   *
   * @return bool
   *   TRUE if the language negotiation configurations are identical.
   */
  protected function isSameLanguageNegotiationConfig(DomainInterface $active_domain, DomainInterface $target_domain): bool {
    $cache_key = $active_domain->id() . ':' . $target_domain->id();
    if (isset($this->sameLanguageNegotiationConfig[$cache_key])) {
      return $this->sameLanguageNegotiationConfig[$cache_key];
    }

    // Capture current domain to restore it.
    $previous_domain = $this->domainNegotiationContext->getDomain();

    $this->domainNegotiationContext->setDomain($active_domain);
    $active_config = $this->configFactory
      ->get('language.negotiation')->get('url');

    $this->domainNegotiationContext->setDomain($target_domain);
    $target_config = $this->configFactory
      ->get('language.negotiation')->get('url');

    // Restore the original domain context.
    $this->domainNegotiationContext->setDomain($previous_domain);

    $this->sameLanguageNegotiationConfig[$cache_key] =
      ($active_config == $target_config);

    return $this->sameLanguageNegotiationConfig[$cache_key];
  }

  /**
   * Processes language negotiation for a cross-domain path.
   *
   * @param \Drupal\domain\DomainInterface $target
   *   The target domain entity.
   * @param string $path
   *   The path being processed.
   * @param array &$options
   *   The URL options array.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request object, or NULL.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   Optional bubbleable metadata object.
   */
  protected function processLanguageNegotiationOutbound(
    DomainInterface $target,
    $path,
    &$options,
    ?Request $request,
    ?BubbleableMetadata $bubbleable_metadata,
  ) {
    // Capture the current domain to restore it later.
    $previous_domain = $this->domainNegotiationContext->getDomain();

    // Switch to the target domain for config overrides.
    $this->domainNegotiationContext->setDomain($target);

    try {
      // Remove any existing prefix to avoid conflicts.
      unset($options['prefix']);

      // Process the outbound path.
      $this->languageNegotiationUrl->processOutbound(
        $path, $options, $request, $bubbleable_metadata
      );
    }
    finally {
      // Restore the original domain context.
      $this->domainNegotiationContext->setDomain($previous_domain);
    }
  }

  /**
   * Determines if the destination domain feature is enabled.
   *
   * @return bool
   *   Returns TRUE if the destination domain is enabled.
   */
  protected function isDestinationDomainEnabled(): bool {
    if ($this->destinationDomainEnabled === NULL) {
      $this->destinationDomainEnabled =
        (bool) $this->configFactory
          ->get('domain.settings')
          ->get('allow_destination_domain');
    }
    return $this->destinationDomainEnabled;
  }

  /**
   * Processes the destination query parameter.
   *
   * Adjusts the destination parameter if present and not external.
   * Adds a domain-scoped destination for cross-domain redirects.
   *
   * @param array &$options
   *   The URL options array.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  protected function processDestinationParameter(array &$options, Request $request) {
    if (
      isset($options['query']['destination'])
      && $options['query']['destination'] === $request->getPathInfo()
    ) {
      $options['query']['destination_domain'] =
        $request->getSchemeAndHttpHost();
    }
  }

  /**
   * Resets cached properties.
   *
   * Used in functional tests after configuration changes.
   */
  public function reset() {
    $this->languageNegotiationEnabled = NULL;
    $this->sameLanguageNegotiationConfig = [];
    $this->languageNegotiationUrl = NULL;
    $this->destinationDomainEnabled = NULL;
  }

}
