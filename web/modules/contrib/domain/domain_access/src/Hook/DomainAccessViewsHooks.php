<?php

namespace Drupal\domain_access\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain_access\DomainAccessManagerInterface;

/**
 * Views hook implementations for domain_access.
 */
class DomainAccessViewsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data) {
    $entity_definitions = $this->entityTypeManager->getDefinitions();

    foreach ($entity_definitions as $entity_definition) {
      if ($entity_definition->entityClassImplements(ContentEntityInterface::class)) {
        $tables = [$entity_definition->id() . '__' . DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD];
        if ($entity_definition->isRevisionable()) {
          $tables[] = $entity_definition->id() . '_revision__' . DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD;
        }
        foreach ($tables as $table) {
          if (!isset($data[$table])) {
            continue;
          }
          $data[$table][DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD]['field']['id'] = 'domain_access_field';
          $data[$table][DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD . '_target_id']['filter']['id'] = 'domain_access_filter';
          $data[$table][DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD . '_target_id']['argument']['id'] = 'domain_access_argument';

          $data[$table]['current_all'] = [
            'title' => $this->t('Current domain'),
            'group' => $this->t('Domain'),
            'filter' => [
              'field' => DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD . '_target_id',
              'id' => 'domain_access_current_all_filter',
              'title' => $this->t('Available on current domain'),
              'help' => $this->t('Filters out @label available on current domain (published to current domain or all affiliates).', ['@label' => $entity_definition->getPluralLabel()]),
              'type' => 'yes-no',
            ],
          ];

          unset($data[$table][DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD]['relationship']);
        }
      }
    }
  }

}
