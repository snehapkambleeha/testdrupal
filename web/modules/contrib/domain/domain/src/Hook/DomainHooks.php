<?php

namespace Drupal\domain\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\DomainElementManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\Plugin\LanguageNegotiation\LanguageNegotiationDomainUrl;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * General hook implementations for domain.
 */
class DomainHooks {

  use StringTranslationTrait;

  public function __construct(
    protected DomainElementManagerInterface $elementManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(param: 'domain.path_prefix')]
    protected bool $pathPrefixEnabled = FALSE,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'domain.admin':
        $output = $this->t('<p>The following domains have been created for your site.  The currently active domain
                     <strong>is shown in boldface</strong>. You may click on a domain to change the currently active domain.
                     </p>');
        return $output;
    }
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
    $domain_storage = $this->entityTypeManager
      ->getStorage('domain');
    $domains = $domain_storage->loadMultiple();
    $ids = [];
    foreach ($domains as $domain) {
      $ids[$domain->getDomainId()][] = $domain->label();
    }
    $duplicates = [];
    foreach ($ids as $id => $names) {
      if (count($names) > 1) {
        $duplicates[] = $this->t(
          'ID @id is used by: @names',
          [
            '@id' => $id,
            '@names' => implode(', ', $names),
          ]
        );
      }
    }
    if (!empty($duplicates)) {
      $requirements['domain_duplicates'] = [
        'title' => $this->t('Domain duplicates'),
        'value' => $this->t('Duplicate domain IDs found.'),
        'description' => [
          '#theme' => 'item_list',
          '#items' => $duplicates,
          '#title' => $this->t(
            'The following duplicate domain IDs have been detected:'
          ),
        ],
        'severity' => REQUIREMENT_ERROR,
      ];
    }

    return $requirements;
  }

  /**
   * Implements hook_language_negotiation_info_alter().
   *
   * Swaps core's URL language negotiation plugin with a
   * domain-aware version that strips the domain path prefix
   * before detecting the language prefix.
   */
  #[Hook('language_negotiation_info_alter')]
  public function languageNegotiationInfoAlter(array &$negotiation_info): void {
    if (!$this->pathPrefixEnabled) {
      return;
    }
    if (isset($negotiation_info[LanguageNegotiationUrl::METHOD_ID])) {
      $negotiation_info[LanguageNegotiationUrl::METHOD_ID]['class'] = LanguageNegotiationDomainUrl::class;
    }
  }

  /**
   * Implements hook_domain_references_alter().
   */
  #[Hook('domain_references_alter')]
  public function domainReferencesAlter($query, $account, $context) {
    // Restrict domains by assignment, only act on admin field.
    if ($context['field_type'] === 'admin' && $context['entity_type'] === 'user') {
      if ($account->hasPermission('administer domains')) {
        // Do nothing.
      }
      elseif ($account->hasPermission('assign domain administrators')) {
        $allowed = $this->elementManager->getFieldValues($account, DomainInterface::DOMAIN_ADMIN_FIELD);
        $query->condition('id', array_keys($allowed), 'IN');
      }
      else {
        // Remove all options.
        $query->condition('id', '-no-possible-match-');
      }
    }
  }

}
