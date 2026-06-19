<?php

namespace Drupal\domain_alias\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DomainAliasPattern constraint.
 */
class DomainAliasPatternConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a DomainAliasPatternConstraintValidator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof DomainAliasPatternConstraint);

    if (!is_string($value) || $value === '') {
      return;
    }
    $pattern = $value;

    // 1) Check for at least one dot or the use of 'localhost'.
    $localhost_check = explode(':', $pattern);
    if (substr_count($pattern, '.') === 0 && $localhost_check[0] !== 'localhost') {
      $this->context->addViolation($constraint->noDotMessage);
    }

    // 2) Check that the alias only has one wildcard.
    $count = substr_count($pattern, '*') + substr_count($pattern, '?');
    if ($count > 1) {
      $this->context->addViolation($constraint->multipleWildcardsMessage);
    }

    // 3) Only one colon allowed, and it must be followed by
    // an integer or wildcard.
    $colon_count = substr_count($pattern, ':');
    if ($colon_count > 1) {
      $this->context->addViolation($constraint->tooManyColonsMessage);
    }
    elseif ($colon_count === 1) {
      $port = substr($pattern, strpos($pattern, ':') + 1);
      if (!is_numeric($port) && $port !== '*') {
        $this->context->addViolation($constraint->invalidPortMessage);
      }
    }

    // 4) Check for valid characters, unless using non-ASCII.
    $non_ascii = (bool) $this->configFactory
      ->get('domain.settings')
      ->get('allow_non_ascii');
    if (!$non_ascii) {
      if (!preg_match('/^[a-z0-9\.\+\-\*\?:]*$/', $pattern)) {
        $this->context->addViolation($constraint->invalidCharactersMessage);
      }
    }

    // 5) The pattern cannot begin with a dot.
    if (str_starts_with($pattern, '.')) {
      $this->context->addViolation($constraint->startsWithDotMessage);
    }

    // 6) The pattern cannot end with a dot.
    if (str_ends_with($pattern, '.')) {
      $this->context->addViolation($constraint->endsWithDotMessage);
    }

    // 7) Check that the pattern is not an exact match for a
    // registered domain.
    if (preg_match('/^[a-z0-9\.\+\-:]*$/', $pattern)) {
      $domain = $this->entityTypeManager
        ->getStorage('domain')
        ->loadByHostname($pattern);
      if ($domain !== NULL) {
        $this->context->addViolation($constraint->matchesDomainMessage);
      }
    }
  }

}
