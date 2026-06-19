<?php

namespace Drupal\domain;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User interface for the domain overview screen.
 */
class DomainListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'domains';

  /**
   * {@inheritdoc}
   */
  protected const SORT_KEY = 'weight';

  /**
   * The Domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $storage;

  /**
   * The User storage handler.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_user'),
      $container->get('redirect.destination'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('domain.element_manager'),
      $container->getParameter('domain.path_prefix'),
    );
  }

  public function __construct(
    EntityTypeInterface $entity_type,
    DomainStorageInterface $domain_storage,
    protected AccountInterface $currentUser,
    protected RedirectDestinationInterface $destinationHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    protected DomainElementManagerInterface $domainElementManager,
    protected bool $pathPrefixEnabled = FALSE,
  ) {
    parent::__construct($entity_type, $domain_storage);
    $this->moduleHandler = $moduleHandler;
    $this->userStorage = $entityTypeManager->getStorage('user');
    // DraggableListBuilder sets this to FALSE, which cancels any pagination.
    $this->limit = 50;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_admin_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    if (!($entity instanceof DomainInterface)) {
      return $operations;
    }
    $default = $entity->isDefault();
    $id = $entity->id();

    // If the user cannot edit domains, none of these actions are permitted.
    if ($entity->access('update', return_as_object: TRUE)->isForbidden()) {
      return $operations;
    }

    $super_admin = $this->currentUser->hasPermission('administer domains');
    if ($super_admin || $this->currentUser->hasPermission('access inactive domains')) {
      if ($entity->status() && !$default) {
        $operations['disable'] = [
          'title' => $this->t('Disable'),
          'url' => $this->ensureDestination(Url::fromRoute('domain.inline_action',
                   ['op' => 'disable', 'domain' => $id])),
          'weight' => 50,
        ];
      }
      elseif (!$default) {
        $operations['enable'] = [
          'title' => $this->t('Enable'),
          'url' => $this->ensureDestination(Url::fromRoute('domain.inline_action',
                   ['op' => 'enable', 'domain' => $id])),
          'weight' => 40,
        ];
      }
    }
    if (!$default && $super_admin) {
      $operations['default'] = [
        'title' => $this->t('Make default'),
        'url' => $this->ensureDestination(Url::fromRoute('domain.inline_action',
                 ['op' => 'default', 'domain' => $id])),
        'weight' => 30,
      ];
    }
    $operations += $this->moduleHandler
      ->invokeAll('domain_operations', [$entity, $this->currentUser]);

    $default = $this->storage->loadDefaultDomain();

    // Deleting the site default domain is not allowed.
    if ($default instanceof DomainInterface && $id === $default->id()) {
      unset($operations['delete']);
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['label'] = $this->t('Name');
    $header['id'] = ['data' => $this->t('ID')];
    $header['hostname'] = $this->t('Hostname');
    $header['status'] = $this->t('Status');
    $header['is_default'] = $this->t('Default');
    $header['scheme'] = $this->t('Scheme');
    $header += parent::buildHeader();
    if (!$this->currentUser->hasPermission('administer domains')) {
      unset($header['weight']);
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    // If the user cannot view the domain, none of these actions are permitted.
    if (!($entity instanceof DomainInterface) || $entity->access('view', return_as_object: TRUE)->isForbidden()) {
      return $row;
    }

    $row['label'] = $entity->label();
    $row['id'] = ['#markup' => $entity->id()];
    $row['hostname'] = ['#markup' => $entity->getLink()];
    if ($entity->isActive()) {
      $row['hostname']['#prefix'] = '<strong>';
      $row['hostname']['#suffix'] = '</strong>';
    }
    $row['status'] = ['#markup' => $entity->status() ? $this->t('Active') : $this->t('Inactive')];
    $row['is_default'] = ['#markup' => ($entity->isDefault() ? $this->t('Yes') : $this->t('No'))];
    $row['scheme'] = ['#markup' => $entity->getRawScheme()];
    $row += parent::buildRow($entity);

    if ($entity->getRawScheme() === 'variable') {
      $row['scheme']['#markup'] .= ' (' . $entity->getScheme(FALSE) . ')';
    }

    if (!$this->currentUser->hasPermission('administer domains')) {
      unset($row['weight']);
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form[$this->entitiesKey]['#domains'] = $this->entities;
    $form['actions']['submit']['#value'] = $this->t('Save configuration');
    // Only super-admins may sort domains.
    if (!$this->currentUser->hasPermission('administer domains')) {
      $form['actions']['submit']['#access'] = FALSE;
      unset($form['#tabledrag']);
    }
    // Delta is set after each row is loaded.
    $count = count($this->storage->loadMultiple()) + 1;
    foreach (Element::children($form['domains']) as $key) {
      if (isset($form['domains'][$key]['weight'])) {
        $form['domains'][$key]['weight']['#delta'] = $count;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides the parent method to prevent saving bad data.
   *
   * @link https://www.drupal.org/project/domain/issues/2925798
   * @link https://www.drupal.org/project/domain/issues/2925629
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue($this->entitiesKey) as $id => $value) {
      $entity = $this->entities[$id] ?? NULL;
      if ($entity instanceof DomainInterface && $entity->get($this->weightKey) !== $value['weight']) {
        // Reset weight properly.
        $entity->set($this->weightKey, $value['weight']);
        // Do not allow accidental hostname rewrites.
        $entity->set('hostname', $entity->getCanonical());
        $entity->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as a form with pagination. This method overrides
   * both Drupal\Core\Config\Entity\DraggableListBuilder::render() and
   * Drupal\Core\Entity\EntityListBuilder::render().
   */
  public function render() {
    // Build the default form, which includes weights.
    $form = $this->formBuilder->getForm($this);

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $form['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $query = parent::getEntityListQuery();
    $query->accessCheck(FALSE);

    if (version_compare(\Drupal::VERSION, '10.4', '<')) {
      $query->sort($this->entityType->getKey(static::SORT_KEY));
    }

    // If the user cannot administer domains, we must filter the query further
    // by assigned IDs. We don't have to check permissions here, because that is
    // handled by the route system and buildRow(). There are two permissions
    // that allow users to view the entire list.
    if (!$this->currentUser->hasPermission('administer domains') && !$this->currentUser->hasPermission('view domain list')) {
      $user = $this->userStorage->load($this->currentUser->id());
      $allowed = $this->domainElementManager->getFieldValues($user, DomainInterface::DOMAIN_ADMIN_FIELD);
      $query->condition('id', array_keys($allowed), 'IN');
    }

    return $query;
  }

}
