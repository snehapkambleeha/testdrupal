<?php

namespace Drupal\domain_alias\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that a domain alias pattern is unique.
 *
 * @Constraint(
 *   id = "DomainAliasUniquePattern",
 *   label = @Translation("Unique domain alias pattern", context = "Validation"),
 *   type = {"string"}
 * )
 */
#[Constraint(
  id: 'DomainAliasUniquePattern',
  label: new TranslatableMarkup('Unique domain alias pattern', [], ['context' => 'Validation']),
  type: ['string']
)]
class DomainAliasUniquePatternConstraint extends SymfonyConstraint {

  /**
   * The pattern already exists.
   *
   * @var string
   */
  public string $message = 'The pattern already exists.';

}
