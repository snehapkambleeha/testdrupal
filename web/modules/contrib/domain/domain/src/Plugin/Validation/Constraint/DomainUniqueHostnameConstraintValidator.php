<?php

namespace Drupal\domain\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DomainUniqueHostname constraint.
 */
class DomainUniqueHostnameConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a DomainUniqueHostnameConstraintValidator.
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
    assert($constraint instanceof DomainUniqueHostnameConstraint);

    if (!is_string($value) || $value === '') {
      return;
    }

    // Get domain_id and path_prefix from the root config data.
    $root = $this->context->getObject()->getRoot();
    $domain_id = $root->get('domain_id')->getValue();
    $current_prefix = '';
    $elements = $root->getElements();
    if (isset($elements['path_prefix'])) {
      $current_prefix = $elements['path_prefix']->getValue() ?? '';
    }

    $storage = $this->entityTypeManager->getStorage('domain');
    /** @var \Drupal\domain\DomainInterface[] $existing */
    $existing = $storage->loadByProperties(['hostname' => $value]);
    foreach ($existing as $domain) {
      if ($domain_id === $domain->getDomainId()) {
        continue;
      }
      if ($current_prefix === $domain->getPathPrefix()) {
        if ($current_prefix === '') {
          $this->context->addViolation($constraint->message, [
            '@hostname' => $value,
          ]);
        }
        else {
          $this->context->addViolation($constraint->prefixMessage, [
            '@hostname' => $value,
            '@prefix' => $current_prefix,
          ]);
        }
        return;
      }
    }
  }

}
