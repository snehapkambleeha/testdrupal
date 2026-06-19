<?php

namespace Drupal\domain_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;

/**
 * Hook implementations for domain_test.
 */
class DomainTestHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_domain_load().
   */
  #[Hook('domain_load')]
  public function domainLoad(array $domains) {
    foreach ($domains as $domain) {
      $domain->addProperty('foo', 'bar');
    }
  }

  /**
   * Implements hook_domain_validate_alter().
   */
  #[Hook('domain_validate_alter')]
  public function domainValidateAlter(&$error_list, $hostname) {
    // Deliberate test fail.
    if ($hostname === 'fail.example.com') {
      $error_list[] = 'Fail.example.com cannot be registered';
    }
  }

  /**
   * Implements hook_domain_request_alter().
   */
  #[Hook('domain_request_alter')]
  public function domainRequestAlter(DomainInterface &$domain) {
    $domain->addProperty('foo1', 'bar1');
  }

  /**
   * Implements hook_domain_operations().
   */
  #[Hook('domain_operations')]
  public function domainOperations(DomainInterface $domain) {
    $operations = [];
    $id = $domain->id();
    $operations['domain_test'] = [
      'title' => $this->t('Test'),
      'url' => Url::fromRoute('entity.domain.edit_form', ['domain' => $id]),
      'weight' => 80,
    ];
    return $operations;
  }

  /**
   * Implements hook_domain_references_alter().
   */
  #[Hook('domain_references_alter')]
  public function domainReferencesAlter($query, $account, $context) {
    if ($context['entity_type'] === 'node') {
      $test = 'Test string';
      $query->addMetadata('domain_test', $test);
    }
  }

}
