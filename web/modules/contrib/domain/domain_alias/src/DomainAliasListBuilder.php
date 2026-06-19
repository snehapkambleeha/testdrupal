<?php

namespace Drupal\domain_alias;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\DraggableListBuilderTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\domain\DomainInterface;

/**
 * User interface for the domain alias overview screen.
 */
class DomainAliasListBuilder extends DraggableListBuilder {

  use DraggableListBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected const SORT_KEY = 'weight';

  /**
   * A domain object loaded from the controller.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $domain;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_alias_admin_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Pattern'),
      'redirect' => $this->t('Redirect'),
      'environment' => $this->t('Environment'),
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    // We only care about DomainAlias entities.
    if ($entity instanceof DomainAliasInterface) {
      $row['label'] = $entity->label();
      $redirect = $entity->getRedirect();
      $row['redirect'] = ['#plain_text' => empty($redirect) ? $this->t('None') : $redirect];
      $row['environment'] = ['#plain_text' => $entity->getEnvironment()];
    }
    $row += parent::buildRow($entity);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $query = parent::getEntityListQuery();
    $query->accessCheck(FALSE);
    $query->condition('domain_id', $this->getDomainId());
    return $query;
  }

  /**
   * Sets the domain context for this list.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to set as context for the list.
   */
  public function setDomain(DomainInterface $domain) {
    $this->domain = $domain;
  }

  /**
   * Gets the domain context for this list.
   *
   * @return int|string|null
   *   The domain that is context for this list.
   */
  public function getDomainId() {
    // @todo check for a use-case where we might need to derive the id?
    return $this->domain instanceof DomainInterface ? $this->domain->id() : NULL;
  }

}
