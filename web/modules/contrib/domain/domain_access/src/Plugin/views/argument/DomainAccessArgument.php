<?php

namespace Drupal\domain_access\Plugin\views\argument;

use Drupal\domain\DomainInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\StringArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler to find nodes by domain assignment.
 */
#[ViewsArgument(
  id: 'domain_access_argument',
)]
class DomainAccessArgument extends StringArgument {

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->domainStorage = $container->get('entity_type.manager')->getStorage('domain');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    $domain = $this->domainStorage->load($this->argument);
    if ($domain instanceof DomainInterface) {
      return $domain->label();
    }

    return parent::title();
  }

}
