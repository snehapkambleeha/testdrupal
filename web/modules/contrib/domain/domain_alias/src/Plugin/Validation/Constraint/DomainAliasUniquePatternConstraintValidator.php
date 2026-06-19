<?php

namespace Drupal\domain_alias\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DomainAliasUniquePattern constraint.
 */
class DomainAliasUniquePatternConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a DomainAliasUniquePatternConstraintValidator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof DomainAliasUniquePatternConstraint);

    if (!is_string($value) || $value === '') {
      return;
    }

    // Get id from the root config data to exclude self.
    $root = $this->context->getObject()->getRoot();
    $alias_id = $root->get('id')->getValue();

    /** @var \Drupal\domain_alias\DomainAliasStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('domain_alias');
    $existing = $storage->loadByPattern($value);
    if ($existing !== NULL && $existing->id() !== $alias_id) {
      $this->context->addViolation($constraint->message);
    }
  }

}
