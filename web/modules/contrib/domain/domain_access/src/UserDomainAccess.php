<?php

namespace Drupal\domain_access;

/**
 * Represents the domain access level for a user account.
 *
 * This enum defines the three possible states of domain access:
 * - None: User has no domain access permissions
 * - All: User has access to all domains (via "all affiliates" field)
 * - Domain: User has access to specific assigned domains.
 */
enum UserDomainAccess {
  case None;
  case All;
  case Domain;
}
