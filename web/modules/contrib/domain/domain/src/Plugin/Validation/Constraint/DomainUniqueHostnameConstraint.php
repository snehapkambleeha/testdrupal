<?php

namespace Drupal\domain\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that a domain hostname is unique.
 *
 * @Constraint(
 *   id = "DomainUniqueHostname",
 *   label = @Translation("Unique domain hostname", context = "Validation"),
 *   type = {"string"}
 * )
 */
#[Constraint(
  id: 'DomainUniqueHostname',
  label: new TranslatableMarkup('Unique domain hostname', [], ['context' => 'Validation']),
  type: ['string']
)]
class DomainUniqueHostnameConstraint extends SymfonyConstraint {

  /**
   * The hostname is already registered (no path prefix).
   *
   * @var string
   */
  public string $message = 'The hostname (@hostname) is already registered.';

  /**
   * The hostname+prefix combination is already registered.
   *
   * @var string
   */
  public string $prefixMessage = 'The hostname (@hostname) with path prefix (@prefix) is already registered.';

}
