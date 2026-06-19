<?php

namespace Drupal\domain_alias\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DomainAliasEnvironment constraint.
 */
class DomainAliasEnvironmentConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a DomainAliasEnvironmentConstraintValidator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof DomainAliasEnvironmentConstraint);

    if (!is_string($value) || $value === '') {
      return;
    }

    $environments = $this->configFactory
      ->get('domain_alias.settings')
      ->get('environments') ?? [];

    if (!in_array($value, $environments, TRUE)) {
      $this->context->addViolation($constraint->message, [
        '@environment' => $value,
        '@allowed' => implode(', ', $environments),
      ]);
    }
  }

}
