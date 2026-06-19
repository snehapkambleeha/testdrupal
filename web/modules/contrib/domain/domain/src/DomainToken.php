<?php

namespace Drupal\domain;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Token handler for Domain.
 *
 * TokenAPI still uses procedural code, but we have moved it to a class for
 * easier refactoring.
 */
class DomainToken {

  use StringTranslationTrait;

  /**
   * The Domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {
    $this->domainStorage = $this->entityTypeManager->getStorage('domain');
  }

  /**
   * Implements hook_token_info().
   */
  public function getTokenInfo() {
    $info = [];
    // Domain token types.
    $info['types']['domain'] = [
      'name' => $this->t('Domains'),
      'description' => $this->t('Tokens related to domains.'),
      'needs-data' => 'domain',
    ];
    // These two types require the Token contrib module.
    $info['types']['current-domain'] = [
      'name' => $this->t('Current domain'),
      'description' => $this->t('Tokens related to the current domain.'),
      'type' => 'domain',
    ];
    $info['types']['default-domain'] = [
      'name' => $this->t('Default domain'),
      'description' => $this->t('Tokens related to the default domain.'),
      'type' => 'domain',
    ];

    // Domain tokens.
    $info['tokens']['domain']['id'] = [
      'name' => $this->t('Domain id'),
      'description' => $this->t("The domain's numeric ID."),
    ];
    $info['tokens']['domain']['machine-name'] = [
      'name' => $this->t('Domain machine name'),
      'description' => $this->t('The domain machine identifier.'),
    ];
    $info['tokens']['domain']['path'] = [
      'name' => $this->t('Domain path'),
      'description' => $this->t('The base URL for the domain.'),
    ];
    $info['tokens']['domain']['name'] = [
      'name' => $this->t('Domain name'),
      'description' => $this->t('The domain name.'),
    ];
    $info['tokens']['domain']['url'] = [
      'name' => $this->t('Domain URL'),
      'description' => $this->t("The domain's URL for the current page request."),
    ];
    $info['tokens']['domain']['hostname'] = [
      'name' => $this->t('Domain hostname'),
      'description' => $this->t('The domain hostname.'),
    ];
    $info['tokens']['domain']['scheme'] = [
      'name' => $this->t('Domain scheme'),
      'description' => $this->t('The domain scheme.'),
    ];
    $info['tokens']['domain']['status'] = [
      'name' => $this->t('Domain status'),
      'description' => $this->t('The domain status.'),
    ];
    $info['tokens']['domain']['weight'] = [
      'name' => $this->t('Domain weight'),
      'description' => $this->t('The domain weight.'),
    ];
    $info['tokens']['domain']['is_default'] = [
      'name' => $this->t('Domain default'),
      'description' => $this->t('The domain is the default domain.'),
    ];

    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  public function getTokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];

    $domain = NULL;

    // Based on the type, get the proper domain context.
    switch ($type) {
      case 'domain':
        if (isset($data['domain'])) {
          $domain = $data['domain'];
        }
        else {
          $domain = $this->domainNegotiationContext->getDomain();
        }
        break;

      case 'current-domain':
        $domain = $this->domainNegotiationContext->getDomain();
        break;

      case 'default-domain':
        $domain = $this->domainStorage->loadDefaultDomain();
        break;
    }

    // Set the token information.
    if ($domain instanceof DomainInterface) {
      $callbacks = $this->getCallbacks();
      foreach ($tokens as $name => $original) {
        if (isset($callbacks[$name])) {
          $replacements[$original] = call_user_func_array([$domain, $callbacks[$name]], []);
          $bubbleable_metadata->addCacheableDependency($domain);
        }
      }
    }

    return $replacements;
  }

  /**
   * Maps tokens to their entity callbacks.
   *
   * We assume that the token will call an instance of DomainInterface.
   *
   * @return array
   *   An array of callbacks keyed by the token string.
   */
  public function getCallbacks() {
    return [
      'id' => 'getDomainId',
      'machine-name' => 'id',
      'path' => 'getPath',
      'name' => 'label',
      'hostname' => 'getHostname',
      'scheme' => 'getScheme',
      'status' => 'status',
      'weight' => 'getWeight',
      'is_default' => 'isDefault',
      'url' => 'getUrl',
    ];
  }

}
