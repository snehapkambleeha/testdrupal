<?php

namespace Drupal\domain_access\Plugin\views\filter;

use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles matching of current domain.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('domain_access_current_all_filter')]
class DomainAccessCurrentAllFilter extends BooleanOperator {

  /**
   * The Domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiationContext
   */
  protected $domainNegotiationContext;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->domainNegotiationContext = $container->get('domain.negotiation_context');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    unset($operators['!=']);
    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    if (method_exists($this->query, 'addTable')) {
      if (strpos($this->table, DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD) !== FALSE) {
        $base_entity = explode('__', $this->table, 2)[0];
        $all_table = $this->query->addTable($base_entity . '__' . DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD, $this->relationship);
        $all_field = $all_table . '.' . DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD . '_value';
        $real_field = $this->tableAlias . '.' . $this->realField;

        $current_domain = $this->domainNegotiationContext->getDomain();
        $current_domain_id = $current_domain->id();

        if (is_null($this->value) || intval($this->value) === 0) {
          $where = "(($real_field <> '$current_domain_id' OR $real_field IS NULL) AND ($all_field = 0 OR $all_field IS NULL))";
          if ($current_domain->isDefault()) {
            $where = "($real_field <> '$current_domain_id' AND ($all_field = 0 OR $all_field IS NULL))";
          }
        }
        else {
          $where = "($real_field = '$current_domain_id' OR $all_field = 1)";
          if ($current_domain->isDefault()) {
            $where = "(($real_field = '$current_domain_id' OR $real_field IS NULL) OR $all_field = 1)";
          }
        }

        if (method_exists($this->query, 'addWhereExpression')) {
          $this->query->addWhereExpression($this->options['group'], $where);
        }
        // This filter causes duplicates.
        $this->query->options['distinct'] = TRUE;
      }
    }
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
