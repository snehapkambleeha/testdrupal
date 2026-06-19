<?php

namespace Drupal\domain_config_ui;

use Drupal\domain\DomainNegotiationContext;
use Drupal\domain_config\DomainConfigLanguageManager;
use Drupal\domain_config_ui\Config\DomainConfigFactory;

/**
 * Extends the language manager to set the current language on runtime.
 */
class DomainConfigUILanguageManager extends DomainConfigLanguageManager {

  /**
   * The domain negotiation context.
   *
   * @var \Drupal\domain\DomainNegotiationContext
   */
  protected $domainNegotiationContext;

  /**
   * Sets the domain negotiation context.
   *
   * @param \Drupal\domain\DomainNegotiationContext $domainNegotiationContext
   *   The domain negotiation context.
   */
  public function setDomainNegotiationContext(DomainNegotiationContext $domainNegotiationContext): void {
    $this->domainNegotiationContext = $domainNegotiationContext;
  }

  /**
   * Checks if a configuration is allowed to be overridden for active domain.
   *
   * @param string $names
   *   A configuration name.
   *
   * @return bool
   *   TRUE if configuration is overridable for the active domain,
   *   FALSE otherwise.
   */
  protected function isRegisteredConfiguration($names) {
    if ($this->configFactory instanceof DomainConfigFactory) {
      return $this->configFactory->isRegisteredConfiguration($names);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageConfigOverride($langcode, $name) {
    $domain_id = $this->domainNegotiationContext->getDomainId();
    if ($domain_id && $this->isRegisteredConfiguration($name)) {
      return $this->domainConfigFactoryOverride->getDomainOverride($domain_id, $langcode, $name);
    }
    else {
      return $this->decoratedManager->getLanguageConfigOverride($langcode, $name);
    }
  }

}
