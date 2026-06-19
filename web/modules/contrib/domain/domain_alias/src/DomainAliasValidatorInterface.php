<?php

namespace Drupal\domain_alias;

/**
 * Supplies validator methods for common domain alias requests.
 *
 * @deprecated in domain:3.0.0 and is removed from domain:4.0.0.
 *   Use entity constraint validation instead.
 * @see https://www.drupal.org/node/3575069
 */
interface DomainAliasValidatorInterface {

  /**
   * Validates the rules for a domain alias.
   *
   * @param \Drupal\domain_alias\DomainAliasInterface $alias
   *   The domain alias to validate.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The validation error message, if any.
   *
   * @deprecated in domain:3.0.0 and is removed from domain:4.0.0.
   *   Use entity constraint validation instead.
   * @see https://www.drupal.org/node/3575069
   */
  public function validate(DomainAliasInterface $alias);

}
