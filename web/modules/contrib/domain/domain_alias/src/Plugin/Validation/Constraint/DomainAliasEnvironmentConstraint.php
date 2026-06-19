<?php

namespace Drupal\domain_alias\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that a domain alias environment is in the allowed list.
 *
 * @Constraint(
 *   id = "DomainAliasEnvironment",
 *   label = @Translation("Domain alias environment", context = "Validation"),
 *   type = {"string"}
 * )
 */
#[Constraint(
  id: 'DomainAliasEnvironment',
  label: new TranslatableMarkup('Domain alias environment', [], ['context' => 'Validation']),
  type: ['string']
)]
class DomainAliasEnvironmentConstraint extends SymfonyConstraint {

  /**
   * The environment is not in the allowed list.
   *
   * @var string
   */
  public string $message = 'The environment "@environment" is not valid. Allowed values: @allowed.';

}
