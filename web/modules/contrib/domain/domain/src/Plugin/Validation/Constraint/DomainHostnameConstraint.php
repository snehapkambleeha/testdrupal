<?php

namespace Drupal\domain\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that a domain hostname is well-formed.
 *
 * @Constraint(
 *   id = "DomainHostname",
 *   label = @Translation("Domain hostname", context = "Validation"),
 *   type = {"string"}
 * )
 */
#[Constraint(
  id: 'DomainHostname',
  label: new TranslatableMarkup('Domain hostname', [], ['context' => 'Validation']),
  type: ['string']
)]
class DomainHostnameConstraint extends SymfonyConstraint {

  /**
   * Hostname must contain at least one dot (unless localhost).
   *
   * @var string
   */
  public string $noDotMessage = 'At least one dot (.) is required, except when using <em>localhost</em>.';

  /**
   * Only one colon is allowed.
   *
   * @var string
   */
  public string $tooManyColonsMessage = 'Only one colon (:) is allowed.';

  /**
   * Port must be an integer.
   *
   * @var string
   */
  public string $portNotNumericMessage = 'The port protocol must be an integer.';

  /**
   * Hostname must not begin with a dot.
   *
   * @var string
   */
  public string $startsWithDotMessage = 'The domain must not begin with a dot (.)';

  /**
   * Hostname must not end with a dot.
   *
   * @var string
   */
  public string $endsWithDotMessage = 'The domain must not end with a dot (.)';

  /**
   * Only valid ASCII characters are allowed.
   *
   * @var string
   */
  public string $invalidCharactersMessage = 'Only alphanumeric characters, dashes, and a colon are allowed.';

  /**
   * Hostname must be lower-case.
   *
   * @var string
   */
  public string $notLowercaseMessage = 'Only lower-case characters are allowed.';

  /**
   * Hostname must not use a www prefix.
   *
   * @var string
   */
  public string $wwwPrefixMessage = 'WWW prefix handling: Domains must be registered without the www. prefix.';

}
