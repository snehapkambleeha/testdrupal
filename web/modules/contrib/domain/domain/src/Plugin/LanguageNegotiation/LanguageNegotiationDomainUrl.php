<?php

namespace Drupal\domain\Plugin\LanguageNegotiation;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Domain-aware URL language negotiation.
 *
 * Extends core's LanguageNegotiationUrl to handle domain path
 * prefixes. When the active domain has a path prefix (e.g.
 * "benl"), the raw request path /benl/fr/node/1 starts with the
 * domain prefix, not the language prefix. Core's getLangcode()
 * reads $request->getPathInfo() and would mistake "benl" for
 * the language prefix. This override strips the domain prefix
 * before performing the language lookup.
 */
class LanguageNegotiationDomainUrl extends LanguageNegotiationUrl implements ContainerFactoryPluginInterface {

  public function __construct(
    protected DomainNegotiationContext $negotiationContext,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(DomainNegotiationContext::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL) {
    if (!$request || !$this->languageManager) {
      return NULL;
    }

    $config = $this->config->get('language.negotiation')->get('url');

    // Only handle path prefix mode; delegate domain mode.
    if ($config['source'] !== LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {
      return parent::getLangcode($request);
    }

    $request_path = urldecode(
      trim($request->getPathInfo(), '/')
    );

    // Strip the domain path prefix if present.
    $domain = $this->negotiationContext->getDomain();
    if ($domain instanceof DomainInterface) {
      $prefix = $domain->getPathPrefix();
      if ($prefix !== '') {
        if (str_starts_with($request_path, $prefix . '/')) {
          $request_path = substr($request_path, strlen($prefix) + 1);
        }
        elseif ($request_path === $prefix) {
          $request_path = '';
        }
      }
    }

    // Check for the language prefix in the stripped path.
    $path_args = explode('/', $request_path);
    $lang_prefix = array_shift($path_args);

    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language) {
      if (
        isset($config['prefixes'][$language->getId()])
        && $config['prefixes'][$language->getId()] == $lang_prefix
      ) {
        return $language->getId();
      }
    }

    return NULL;
  }

}
