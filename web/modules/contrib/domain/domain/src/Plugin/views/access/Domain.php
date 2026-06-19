<?php

namespace Drupal\domain\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain\DomainStorageInterface;
use Drupal\views\Attribute\ViewsAccess;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides domain-based access control.
 */
#[ViewsAccess(
  id: 'domain',
  title: new TranslatableMarkup('Domain'),
  help: new TranslatableMarkup('Access will be granted when accessed from an allowed domain.')
)]
class Domain extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DomainStorageInterface $domainStorage,
    protected DomainNegotiationContext $domainNegotiationContext,
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
      $container->get('entity_type.manager')->getStorage('domain'),
      $container->get('domain.negotiation_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $id = $this->domainNegotiationContext->getDomainId();
    $options = array_filter($this->options['domain']);
    return isset($options[$id]);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    if ($this->options['domain']) {
      $route->setRequirement('_domain', implode('+', $this->options['domain']));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $count = count($this->options['domain']);
    if ($count < 1) {
      return $this->t('No domain(s) selected');
    }
    elseif ($count > 1) {
      return $this->t('Multiple domains');
    }
    else {
      $domains = $this->domainStorage->loadOptionsList();
      $domain = reset($this->options['domain']);
      return $domains[$domain];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['domain'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['domain'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Domain'),
      '#default_value' => $this->options['domain'],
      '#options' => $this->domainStorage->loadOptionsList(),
      '#description' => $this->t('Only the checked domain(s) will be able to access this display.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $domain = $form_state->getValue(['access_options', 'domain']);
    $domain = array_filter($domain);

    if ($domain === []) {
      $form_state->setError($form['domain'], $this->t('You must select at least one domain if type is "by domain"'));
    }

    $form_state->setValue(['access_options', 'domain'], $domain);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach (array_keys($this->options['domain']) as $id) {
      $domain = $this->domainStorage->load($id);
      if ($domain instanceof DomainInterface) {
        $dependencies[$domain->getConfigDependencyKey()][] = $domain->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['domain'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
