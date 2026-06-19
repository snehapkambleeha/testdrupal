<?php

namespace Drupal\domain_alias\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that a domain alias pattern is well-formed.
 *
 * @Constraint(
 *   id = "DomainAliasPattern",
 *   label = @Translation("Domain alias pattern", context = "Validation"),
 *   type = {"string"}
 * )
 */
#[Constraint(
  id: 'DomainAliasPattern',
  label: new TranslatableMarkup('Domain alias pattern', [], ['context' => 'Validation']),
  type: ['string']
)]
class DomainAliasPatternConstraint extends SymfonyConstraint {

  /**
   * Pattern must contain at least one dot (unless localhost).
   *
   * @var string
   */
  public string $noDotMessage = 'At least one dot (.) is required, except when using <em>localhost</em>.';

  /**
   * Only one wildcard is allowed.
   *
   * @var string
   */
  public string $multipleWildcardsMessage = 'You may only have one wildcard character in each alias.';

  /**
   * Only one colon is allowed.
   *
   * @var string
   */
  public string $tooManyColonsMessage = 'You may only have one colon ":" character in each alias.';

  /**
   * Port must be an integer or wildcard.
   *
   * @var string
   */
  public string $invalidPortMessage = 'A colon may only be followed by an integer indicating the proper port or the wildcard character (*).';

  /**
   * Only valid characters are allowed.
   *
   * @var string
   */
  public string $invalidCharactersMessage = 'The pattern contains invalid characters.';

  /**
   * Pattern must not begin with a dot.
   *
   * @var string
   */
  public string $startsWithDotMessage = 'The pattern cannot begin with a dot.';

  /**
   * Pattern must not end with a dot.
   *
   * @var string
   */
  public string $endsWithDotMessage = 'The pattern cannot end with a dot.';

  /**
   * Pattern must not match an existing domain hostname.
   *
   * @var string
   */
  public string $matchesDomainMessage = 'The pattern matches an existing domain record.';

}
