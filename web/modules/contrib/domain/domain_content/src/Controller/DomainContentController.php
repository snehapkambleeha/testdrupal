<?php

namespace Drupal\domain_content\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\domain\Controller\DomainControllerBase;
use Drupal\domain\DomainInterface;
use Drupal\domain_access\DomainAccessManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines domain content pages.
 */
class DomainContentController extends DomainControllerBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainAccessManagerInterface $manager,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('domain_access.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Builds the list of domains and relevant entities.
   *
   * @param array $options
   *   A list of variables required to build editor or content pages.
   *
   * @return array
   *   A Drupal page build array.
   *
   * @see contentList()
   */
  public function buildList(array $options) {
    $account = $this->currentUser;
    $build = [
      '#theme' => 'table',
      '#header' => [$this->t('Domain'), $options['column_header']],
    ];
    if ($account->hasPermission($options['all_permission'])) {
      $url = Url::fromRoute('view.' . $options['view_name'] . '.page_2', []);
      $build['#rows'][] = [
        Link::fromTextAndUrl($this->t('All affiliates'), $url),
        $this->getCount($options['type']),
      ];
    }
    // Loop through domains.
    $domains = $this->domainStorage->loadMultipleSorted();
    foreach ($domains as $domain) {
      if ($account->hasPermission($options['all_permission']) || $this->manager->hasDomainPermissions($account, $domain, [$options['permission']])) {
        $url = Url::fromRoute('view.' . $options['view_name'] . '.page_1', ['arg_0' => $domain->id()]);
        $row = [
          Link::fromTextAndUrl($domain->label(), $url),
          $this->getCount($options['type'], $domain),
        ];
        $build['#rows'][] = $row;
      }
    }
    return $build;
  }

  /**
   * Generates a list of content by domain.
   */
  public function contentList() {
    $options = [
      'type' => 'node',
      'column_header' => $this->t('Content count'),
      'permission' => 'publish to any assigned domain',
      'all_permission' => 'publish to any domain',
      'view_name' => 'affiliated_content',
    ];

    return $this->buildList($options);
  }

  /**
   * Generates a list of editors by domain.
   */
  public function editorsList() {
    $options = [
      'type' => 'user',
      'column_header' => $this->t('Editor count'),
      'permission' => 'assign domain editors',
      'all_permission' => 'assign editors to any domain',
      'view_name' => 'affiliated_editors',
    ];

    return $this->buildList($options);
  }

  /**
   * Counts the content for a domain.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to query. If passed NULL, checks status for all affiliates.
   *
   * @return int
   *   The content count for the given domain.
   */
  protected function getCount($entity_type = 'node', ?DomainInterface $domain = NULL) {
    if (is_null($domain)) {
      $field = DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD;
      $value = 1;
    }
    else {
      $field = DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD;
      $value = $domain->id();
    }
    // Note that we ignore node access so these queries work on any domain.
    $query = $this->entityTypeManager->getStorage($entity_type)->getQuery()
      ->condition($field, $value)
      ->accessCheck(FALSE);

    return count($query->execute());
  }

}
