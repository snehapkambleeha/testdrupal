<?php

namespace Drupal\domain\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\DomainStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets a base class for injecting domain information into controllers.
 *
 * This class is useful in cases where your controller needs to respond to
 * a domain argument. Drupal doesn't do that natively, so we use this base
 * class to allow router arguments to be passed a domain object.
 */
abstract class DomainControllerBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected DomainStorageInterface $domainStorage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->domainStorage = $this->entityTypeManager->getStorage('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

}
