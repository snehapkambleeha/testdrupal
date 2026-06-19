<?php

namespace Drupal\domain_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides helper methods for domain access field management.
 */
class DomainAccessHelper implements DomainAccessHelperInterface {

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('domain_access');
  }

  /**
   * {@inheritdoc}
   */
  public function confirmFields(string $entity_type, string $bundle, array $text = []): void {
    try {
      $text['node'] = [
        'name' => 'content',
        'label' => 'Send to all affiliates',
        'description' => 'Make this content available on all domains.',
      ];
      $text['user'] = [
        'name' => 'user',
        'label' => 'Editor for all affiliates',
        'description' => 'Make this user an editor on all domains.',
      ];

      $id = $entity_type . '.' . $bundle . '.' . DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD;

      $field_storage = $this->entityTypeManager->getStorage('field_config');
      $field = $field_storage->load($id);
      if (is_null($field)) {
        $field = [
          'field_name' => DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD,
          'entity_type' => $entity_type,
          'label' => 'Domain Access',
          'bundle' => $bundle,
          // Users should not be required to be a domain editor.
          'required' => $entity_type !== 'user',
          'description' => 'Select the affiliate domain(s) for this ' . $text[$entity_type]['name'],
          'settings' => [
            'handler' => 'default:domain',
            // Handler_settings are deprecated but seem necessary.
            'handler_settings' => [
              'target_bundles' => NULL,
              'sort' => [
                'field' => 'weight',
                'direction' => 'ASC',
              ],
            ],
            'target_bundles' => NULL,
            'sort' => [
              'field' => 'weight',
              'direction' => 'ASC',
            ],
          ],
        ];
        $field_config = $field_storage->create($field);
        $field_config->setThirdPartySetting('domain_access', 'add_current_domain', TRUE);
        $field_config->save();
      }
      // Assign the all affiliates field.
      $id = $entity_type . '.' . $bundle . '.' . DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD;
      $field = $field_storage->load($id);
      if (is_null($field)) {
        $field = [
          'field_name' => DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD,
          'entity_type' => $entity_type,
          'label' => $text[$entity_type]['label'],
          'bundle' => $bundle,
          'required' => FALSE,
          'description' => $text[$entity_type]['description'],
        ];
        $field_config = $field_storage->create($field);
        $field_config->save();
      }
      // Tell the form system how to behave. Default to radio buttons.
      /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $display */
      $display = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->load($entity_type . '.' . $bundle . '.default');
      if ($display instanceof EntityInterface) {
        $display->setComponent(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD, [
          'type' => 'options_buttons',
          'weight' => 40,
        ])->setComponent(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD, [
          'type' => 'boolean_checkbox',
          'settings' => ['display_label' => 1],
          'weight' => 41,
        ])->save();
      }
    }
    catch (\Exception $e) {
      $this->logger->notice('Field installation failed.');
    }
  }

}
