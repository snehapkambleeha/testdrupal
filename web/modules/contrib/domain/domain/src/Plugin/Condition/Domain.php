<?php

namespace Drupal\domain\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainNegotiationContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Domain' condition.
 */
#[Condition(
  id: 'domain',
  label: new TranslatableMarkup('Domain'),
  context_definitions: [
    'domain' => new EntityContextDefinition(
      data_type: 'entity:domain',
      label: new TranslatableMarkup('Domain'),
      required: TRUE,
    ),
  ]
)]
class Domain extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DomainNegotiationContext $domainNegotiationContext,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('domain.negotiation_context'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Use the domain labels. They will be sanitized below.
    // @todo Set the optionsList as a property.
    $domains = $this->entityTypeManager->getStorage('domain')->loadOptionsList();

    $form['domains'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('When the following domains are active'),
      '#default_value' => $this->configuration['domains'],
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', $domains),
      '#description' => $this->t('If you select no domains, the condition will evaluate to TRUE for all requests.'),
      '#attached' => [
        'library' => [
          'domain/drupal.domain',
        ],
      ],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'domains' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['domains'] = array_filter($form_state->getValue('domains'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    // Use the domain labels. They will be sanitized below.
    $domains = array_intersect_key($this->entityTypeManager->getStorage('domain')->loadOptionsList(), $this->configuration['domains']);

    if (count($domains) > 1) {
      $domains = implode(', ', $domains);
    }
    else {
      $domains = reset($domains);
    }
    if ($this->isNegated()) {
      return $this->t('Active domain is not @domains', ['@domains' => $domains]);
    }
    else {
      return $this->t('Active domain is @domains', ['@domains' => $domains]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $domains = $this->configuration['domains'];
    if ($domains === [] && !$this->isNegated()) {
      return TRUE;
    }
    // If the context did not load, derive from the request.
    if (!$this->getContext('domain')->hasContextValue()) {
      $this->setContextValue('domain', $this->domainNegotiationContext->getDomain());
    }
    $domain = $this->getContextValue('domain');
    // No domain available.
    if (is_null($domain)) {
      return FALSE;
    }
    // NOTE: The context system handles negation for us.
    return in_array($domain->id(), $domains, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'domain';
    return $contexts;
  }

}
