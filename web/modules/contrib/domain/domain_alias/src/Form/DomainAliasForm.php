<?php

namespace Drupal\domain_alias\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form controller for domain alias edit forms.
 */
class DomainAliasForm extends EntityForm {

  /**
   * The domain entity access control handler.
   *
   * @var \Drupal\domain\DomainAccessControlHandler
   */
  protected $accessHandler;

  /**
   * The domain storage manager.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * The domain alias storage manager.
   *
   * @var \Drupal\domain_alias\DomainAliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  public function __construct(
    protected TypedConfigManagerInterface $typedConfigManager,
    protected ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entityTypeManager,
    protected RendererInterface $renderer,
    MessengerInterface $messenger,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aliasStorage = $entityTypeManager->getStorage('domain_alias');
    $this->domainStorage = $entityTypeManager->getStorage('domain');
    // Not loaded directly since it is not an interface.
    $this->accessHandler = $this->entityTypeManager->getAccessControlHandler('domain');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.typed'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\domain_alias\DomainAliasInterface $alias */
    $alias = $this->entity;

    $form['domain_id'] = [
      '#type' => 'value',
      '#value' => $alias->getDomainId(),
    ];
    // Show a hint when adding an alias to a prefixed domain.
    $parent_domain = $this->domainStorage->load($alias->getDomainId());
    if ($parent_domain && $parent_domain->getPathPrefix() !== '') {
      $form['prefix_notice'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => [
          '#markup' => $this->t('This domain uses the path prefix %prefix. Aliases resolve hostnames only — path prefix negotiation happens automatically afterward. If another domain shares the same hostname without a prefix, add aliases there instead.', [
            '%prefix' => $parent_domain->getPathPrefix(),
          ]),
        ],
      ];
    }
    $form['pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#size' => 40,
      '#maxlength' => 80,
      '#default_value' => $alias->getPattern(),
      '#description' => $this->t('The matching pattern for this alias.'),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $alias->id(),
      '#machine_name' => [
        'source' => ['pattern'],
        'exists' => '\Drupal\domain_alias\Entity\DomainAlias::load',
      ],
    ];
    $form['redirect'] = [
      '#type' => 'select',
      '#title' => $this->t('Redirect'),
      '#options' => $this->redirectOptions(),
      '#default_value' => $alias->getRedirect(),
      '#description' => $this->t('Set an optional redirect directive when this alias is invoked.'),
    ];
    $environments = $this->environmentOptions();
    $form['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#options' => $environments,
      '#default_value' => $alias->getEnvironment(),
      '#description' => $this->t('Map the alias to a development environment.'),
    ];
    $form['environment_help'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#collapsed' => TRUE,
      '#title' => $this->t('Environment list'),
      '#description' => $this->t('The table below shows the registered aliases for each environment.'),
    ];

    $domains = $this->domainStorage->loadMultipleSorted();
    $rows = [];
    foreach ($domains as $domain) {
      // If the user cannot edit the domain, then don't show in the list.
      $access = $this->accessHandler->checkAccess($domain, 'update');
      if ($access->isForbidden()) {
        continue;
      }
      $row = [];
      $row[] = $domain->label();
      foreach ($environments as $environment) {
        $match_output = [];
        if ($environment === 'default') {
          $match_output[] = $domain->getCanonical();
        }
        $matches = $this->aliasStorage->loadByEnvironmentMatch($domain, $environment);
        foreach ($matches as $match) {
          $match_output[] = $match->getPattern();
        }
        $output = [
          '#items' => $match_output,
          '#theme' => 'item_list',
        ];
        $row[] = $this->renderer->render($output);
      }
      $rows[] = $row;
    }

    $form['environment_help']['table'] = [
      '#type' => 'table',
      '#header' => array_merge([$this->t('Domain')], $environments),
      '#rows' => $rows,
    ];

    return parent::form($form, $form_state);
  }

  /**
   * Returns a list of valid redirect options for the form.
   *
   * @return array
   *   A list of valid redirect options.
   */
  public function redirectOptions() {
    return [
      0 => $this->t('Do not redirect'),
      301 => $this->t('301 redirect: Moved Permanently'),
      302 => $this->t('302 redirect: Found'),
    ];
  }

  /**
   * Returns a list of valid environment options for the form.
   *
   * @return array
   *   A list of valid environment options.
   */
  public function environmentOptions() {
    $list = $this->config->get('domain_alias.settings')->get('environments');
    $environments = [];
    foreach ($list as $item) {
      $environments[$item] = $item;
    }
    return $environments;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Trim whitespace from pattern to prevent hard-to-debug issues where
    // invisible leading/trailing spaces cause alias matching to fail.
    $pattern = trim($form_state->getValue('pattern'));
    $form_state->setValue('pattern', $pattern);

    // HTML selects submit strings; cast redirect to the integer
    // the schema Choice constraint expects.
    $form_state->setValue('redirect', (int) $form_state->getValue('redirect'));

    // Rebuild entity with the corrected form values.
    $entity = $this->buildEntity($form, $form_state);

    // Validate via config schema constraints.
    $violations = $this->typedConfigManager
      ->createFromNameAndData(
        $entity->getConfigDependencyName(),
        $entity->toArray()
      )
      ->validate();

    // Map violations to form elements by property path.
    foreach ($violations as $violation) {
      $property_path = $violation->getPropertyPath();
      if (isset($form[$property_path])) {
        $form_state->setErrorByName(
          $property_path,
          $violation->getMessage()
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\domain_alias\DomainAliasInterface $alias */
    $alias = $this->entity;
    $edit_link = $alias->toLink($this->t('Edit'), 'edit-form')->toString();
    $result = $alias->save();
    if ($result === SAVED_NEW) {
      $this->messenger->addMessage($this->t('Created new domain alias.'));
      $this->logger('domain_alias')->notice('Created new domain alias %name.', [
        '%name' => $alias->label(),
        'link' => $edit_link,
      ]);
    }
    else {
      $this->messenger->addMessage($this->t('Updated domain alias.'));
      $this->logger('domain_alias')->notice('Updated domain alias %name.', [
        '%name' => $alias->label(),
        'link' => $edit_link,
      ]);
    }
    // Warn when a wildcard alias with a non-default environment
    // matches a registered domain's canonical hostname. The exact
    // hostname match short-circuits alias negotiation, so the
    // alias environment would never apply for that hostname.
    $pattern = $alias->getPattern();
    if ($alias->getEnvironment() !== 'default'
      && (str_contains($pattern, '*') || str_contains($pattern, '?'))
    ) {
      $hostname_pattern = explode(':', $pattern)[0];
      $regex = '/^' . str_replace(
        ['\*', '\?'],
        ['[^.:]+', '[^.:]'],
        preg_quote($hostname_pattern, '/')
      ) . '$/';
      foreach ($this->domainStorage->loadMultiple() as $domain) {
        if (preg_match($regex, $domain->getCanonical())) {
          $this->messenger->addWarning($this->t(
            'The pattern matches the canonical hostname %hostname. The alias environment will not apply to that hostname.',
            ['%hostname' => $domain->getCanonical()]
          ));
        }
      }
    }

    $form_state->setRedirect('domain_alias.admin', [
      'domain' => $alias->getDomainId(),
    ]);

    return $result;
  }

}
