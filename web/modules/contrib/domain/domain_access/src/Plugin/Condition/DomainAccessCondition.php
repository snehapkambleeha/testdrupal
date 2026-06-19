<?php

namespace Drupal\domain_access\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain_access\DomainAccessManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Domain Access' condition.
 */
#[Condition(
  id: 'domain_access',
  label: new TranslatableMarkup('Domain Access'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup('Content'),
      required: TRUE,
    ),
  ]
)]
class DomainAccessCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['domains'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('At least one of the following domains is assigned to the content'),
      '#default_value' => $this->configuration['domains'],
      '#options' => array_map('\Drupal\Component\Utility\Html::escape',
        $this->entityTypeManager->getStorage('domain')->loadOptionsList()),
      '#description' => $this->t('If you select no domains, the condition will evaluate to TRUE for all requests.'),
      '#attached' => [
        'library' => [
          'domain_access/drupal.domain_access',
        ],
      ],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'domains' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['domains'] = array_filter($form_state->getValue('domains'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): string {
    // Use the domain labels. They will be sanitized below.
    $domainStorage = $this->entityTypeManager->getStorage('domain');
    $domains = array_intersect_key($domainStorage->loadOptionsList(), $this->configuration['domains']);

    if (empty($domains)) {
      return $this->isNegated()
        ? $this->t('Content is not assigned to any domain')
        : $this->t('Content is assigned to any domain');
    }

    $formatted_domains = count($domains) > 1 ? implode(', ', $domains) : reset($domains);

    return $this->isNegated()
      ? $this->t('Content is not assigned to @domains', ['@domains' => $formatted_domains])
      : $this->t('Content is assigned to @domains', ['@domains' => $formatted_domains]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $domains = $this->configuration['domains'];
    if (empty($domains) && !$this->isNegated()) {
      return TRUE;
    }

    // Work with context if available.
    $node = $this->getContextValue('node');
    if ($node) {
      // Early return if node doesn't have the field.
      if (!$node->hasField(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)) {
        return FALSE;
      }
      // Check domain in the field.
      $domains_node = $node->get(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD)->getValue();
      foreach ($domains_node as $domain_node) {
        if (isset($domain_node['target_id']) && in_array($domain_node['target_id'], $domains, TRUE)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'domain';
    return $contexts;
  }

}
