<?php

namespace Drupal\domain_config_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\domain_config\Config\DomainConfigFactoryOverrideInterface;
use Drupal\domain_config_ui\Controller\DomainConfigUIController;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for deleting domain-specific configuration.
 */
class DeleteForm extends FormBase {

  /**
   * The entity storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    RequestStack $requestStack,
    EntityTypeManagerInterface $entityTypeManager,
    protected DomainConfigFactoryOverrideInterface $domainConfigFactoryOverride,
    protected DomainConfigUIManagerInterface $domainConfigUiManager,
  ) {
    $this->requestStack = $requestStack;
    $this->domainStorage = $entityTypeManager->getStorage('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('domain.config_factory_override'),
      $container->get('domain_config_ui.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_ui_delete';
  }

  /**
   * Build configuration form with metadata and values.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $domain_id = NULL, $config_names = NULL) {
    if (empty($domain_id) || empty($config_names)) {
      $url = Url::fromRoute('domain_config_ui.list');
      return new RedirectResponse($url->toString());
    }

    $domain = $this->domainStorage->load($domain_id);
    $config_names_array = explode(',', $config_names);

    $configs = [];
    foreach ($config_names_array as $config_name) {
      $configs[$config_name] = $this->domainConfigFactoryOverride->getOverride($domain_id, $config_name)->getRawData();
    }

    $config_names_str = implode(', ', $config_names_array);
    $form['help'] = [
      '#type' => 'item',
      '#title' => Html::escape($config_names_str),
      '#markup' => $this->t('Are you sure you want to delete the configuration
        override(s) %config_names for the domain %domain as well as the associated translations?',
        ['%config_names' => $config_names_str, '%domain' => $domain->label()]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];
    $form['review'] = [
      '#type' => 'details',
      '#title' => $this->t('Review settings'),
      '#open' => FALSE,
    ];
    foreach ($config_names_array as $config_name) {
      $form['review'][$config_name] = [
        '#markup' => DomainConfigUIController::printArray($configs[$config_name]),
      ];
    }
    $form['config_names'] = ['#type' => 'value', '#value' => $config_names_array];
    $form['domain_id'] = ['#type' => 'value', '#value' => $domain_id];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete configuration'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->getCancelUrl(),
      '#attributes' => [
        'class' => [
          'button',
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domain_id = $form_state->getValue('domain_id');
    $config_names = $form_state->getValue('config_names');
    $config_names_str = implode(', ', $config_names);
    $message = $this->t(
      'Overridden configuration %label for domain %domain has been deleted with all its translations.',
      ['%label' => $config_names_str, '%domain' => $domain_id]
    );
    $this->messenger()->addMessage($message);
    $this->logger('domain_config')->notice($message);
    foreach ($config_names as $config_name) {
      $this->domainConfigUiManager->deleteConfigurationOverridesForDomain(
        $domain_id, $config_name, $this->isRemove()
      );
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function getCancelUrl() {
    $query = $this->requestStack->getCurrentRequest()->query;
    $destination = $query->get('destination');
    if (empty($destination)) {
      return new Url('domain_config_ui.list');
    }
    else {
      return Url::fromUserInput($destination);
    }
  }

  /**
   * Get the remove parameter.
   */
  protected function isRemove() {
    $query = $this->requestStack->getCurrentRequest()->query;
    return $query->has('remove');
  }

}
