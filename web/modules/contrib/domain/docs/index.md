# Presentation

The Domain module suite lets you share users, content, and configuration across
a group of domains from a single installation and database.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/domain).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/domain).

## Requirements

This module requires no modules outside of Drupal core.

The 3.x version requires Drupal 10.2 or higher and is Drupal 11 compatible.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Included modules

- **Domain**
  The core module. Domain provides means for registering multiple domains within
  a single Drupal installation. It allows users to be assigned as domain
  administrators and provides a Block and Views display context. Domains can
  also share a hostname with different
  [path prefixes](domain/path_prefix.md) for path-based separation.

- **Domain Access**
  Provides node access controls based on domains. It allows users to be
  assigned as editors of content per-domain, sets content visibility rules,
  and provides Views integration for content. See the
  [Domain Access documentation](domain_access/index.md) for more information.

- **Domain Alias**
  Allows multiple hostnames to be pointed to a single registered domain. These
  aliases can include wildcards (such as *.example.com) and may be configured
  to redirect to their canonical domain. Domain Alias also allows developers
  to register aliases per `environment`, so that different hosts are used
  consistently across development environments. See the
  [Domain Alias documentation](domain_alias/index.md) for more information.

- **Domain Config**
  Provides a means for changing configuration settings on a per-domain basis.
  See the [Domain Config documentation](domain_config/index.md) for more
  information.

- **Domain Content**
  Provides content overview pages on a per-domain basis, so that editors may
  review content assigned to specific domains. See the
  [Domain Content documentation](domain_content/index.md) for more
  information.

- **Domain Source**
  Allows content to be assigned a canonical domain when writing URLs. Domain
  Source will ensure that content that appears on multiple domains always links
  to one URL. See the [Domain Source documentation](domain_source/index.md) for
  more information.

## Implementation Notes

### Cross-domain logins

To use cross-domain logins, you must set the **cookie_domain** value in
**sites/default/services.yml**.

To do so, clone `default.services.yml` to `services.yml` and change the
`cookie_domain` value to match the root hostname of your sites. Note that
cross-domain login requires the sharing of a top-level domain, so a setting
like `.example.com` will work for all `example.com` subdomains.

Example:

```
cookie_domain: '.example.com'
```

See [drupal.org/node/2391871](https://www.drupal.org/node/2391871).

### Cross-Site HTTP requests (CORS)

Drupal allows a particular site to enable CORS for responses served by Drupal.

In the case of Domain, allowing CORS may remove AJAX errors caused when using
some forms, particularly entity references, when the AJAX request goes to
another domain.

This feature is not enabled by default because there are security consequences.
See [drupal.org/node/2715637](https://www.drupal.org/node/2715637) for more
information and instructions.

To enable CORS for all domains, copy `default.services.yml` to `services.yml`
and enable the following lines:

``` yaml
   # Configure Cross-Site HTTP requests (CORS).
   # Read https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
   # for more information about the topic in general.
  cors.config:
    enabled: true
    # Specify allowed headers, like 'x-allowed-header'.
    allowedHeaders: []
    # Specify allowed request methods, specify ['*'] to allow all possible ones.
    allowedMethods: []
    # Configure requests allowed from specific origins.
    allowedOrigins: ['*']
    # Sets the Access-Control-Expose-Headers header.
    exposedHeaders: false
    # Sets the Access-Control-Max-Age header.
    maxAge: false
    # Sets the Access-Control-Allow-Credentials header.
    supportsCredentials: false
```

### Trusted host settings

If using the trusted host security setting, be sure to add each domain and
alias to the pattern list. For example:

``` php
$settings['trusted_host_patterns'] = [
  '^.+\.example\.org$',
  '^myexample\.com$',
  '^myexample\.dev$',
  '^localhost$',
];
```

We **strongly encourage** the use of trusted host settings. When Domain issues
a redirect, it will check the domain hostname against these settings. Any
redirect that does not match the trusted host settings will be denied and throw
an exception.

See [drupal.org/node/1992030](https://www.drupal.org/node/1992030) for more
information.

### Configuring domain records

To create a domain record, you must provide the following information:

- A unique **hostname**, which may include a port. (Therefore, example.com and
  example.com:8080 are considered different.) The hostname may only contain
  alphanumeric characters, dashes, dots, and one colon. If you wish to use
  international domain names, toggle the `Allow non-ASCII characters in domains
  and aliases.` setting.
- A **machine_name** that must be unique. This value will be autogenerated and
  cannot be edited once created.
- A **name** to be used in lists of domains.
- A URL scheme, used for writing links to the domain. The scheme may be `http`,
  `https`, or `variable`. If `variable` is used, the scheme will be inherited
  from the server or request settings. This option is good if your test
  environments do not have secure certificates but your production environment
  does.
- A **status** indicating `active` or `inactive`. Inactive domains may only be
  viewed by users with permission to `view inactive domains`; all other users
  will be redirected to the default domain (see below).
- The **weight** to be used when sorting domains. These values auto increment
  as new domains are created.
- Whether the domain is the **default** or not. Only one domain can be set as
  `default`. The default domain is used for redirects in cases where other
  domains are either restricted (inactive) or fail to load. This value can be
  reassigned after domains are created.
- An optional **path prefix** that allows multiple domains to share the same
  hostname while being distinguished by the first URL path segment. See the
  [Path prefix documentation](domain/path_prefix.md).

Domain records are **configuration entities**, which means they are not stored
in the database nor accessible to Views by default. They are, however,
exportable as part of your configuration.

### Validation rules

Domain records are validated via Symfony constraint plugins declared in the
configuration schema (`domain.schema.yml`). These constraints run
automatically when saving through the admin form or Drush commands.

**Hostname** (`DomainHostname` + `DomainUniqueHostname` constraints):

1. At least one dot required (except `localhost`).
2. Only one colon (`:`) for port specification.
3. After a colon, only an integer is allowed.
4. No leading or trailing dots.
5. ASCII characters only (unless `domain.settings:allow_non_ascii` is
   enabled).
6. Lowercase only.
7. No `www.` prefix when the `Ignore www prefix` setting is enabled.
8. The combination of hostname and path prefix must be unique. Two domains
   may share the same hostname only if their path prefixes differ.

**Scheme** (`Choice` constraint):

Must be one of `http`, `https`, or `variable`.

**Domain ID** (`Range` constraint):

Must be a non-negative integer (>= 0). The value is assigned automatically
in `preSave()` and should not be set manually.

**Extensibility**:

Modules can add custom hostname validation rules by implementing
`hook_domain_validate_alter(&$error_list, $hostname)`. Any string added to
`$error_list` will surface as a constraint violation.

### Domains and caching

If some variable changes are not picked up when the page renders, you may need
to add domain-sensitivity to the site's cache.

To do so, clone `default.services.yml` to `services.yml` (if you have not
already done so) and change the `required_cache_contexts` value to:

``` yaml
required_cache_contexts: [ 'languages:language_interface', 'theme', 'user.permissions', 'domain' ]
```

The addition of `domain` should provide the domain context that the cache
layer requires.

When using the Domain Access module, keep in mind that you may also need to
rebuild permissions (`/admin/reports/status/rebuild`) after configuration
changes.

For developers, see also the
[Domain Alias documentation](domain_alias/index.md).

### Contributing

For Drupal 10+, you can use the
[Domain DDEV](https://github.com/agentrickard/domain-ddev) project for
getting started quickly. It includes all the tools described below.

If you file a merge request, run the existing tests to check for failures.
Writing additional tests will greatly speed completion, as code is not merged
without test coverage.

New tests should be written in PHPUnit as Functional, FunctionalJavascript,
Kernel, or Unit tests.

To set up a proper environment locally, you need multiple or wildcard domains
configured to point to your Drupal instance. We use variants of
`example.local` for local tests. See `DomainTestBase` for documentation.
Domain testing should work with root hosts other than `example.com`, though
we also expect to find the subdomains `one.*, two.*, three.*, four.*, five.*`
in most test cases.
See `DomainTestBase::domainCreateTestDomains()` for the logic.

When running tests, you normally need to be on the default domain.

### Code linting

We use (and recommend) [PHPCBF](https://phpqa.io/projects/phpcbf.html),
[PHP Codesniffer](https://github.com/squizlabs/PHP_CodeSniffer),
and [phpstan](https://phpstan.org/) for code quality review.

The following commands are run before commit:

- `vendor/bin/phpcbf web/modules/contrib/domain --standard="Drupal,DrupalPractice" -n --extensions="php,module,inc,install,test,profile,theme"`
- `vendor/bin/phpcs web/modules/contrib/domain --standard="Drupal,DrupalPractice" -n --extensions="php,module,inc,install,test,profile,theme"`
- `vendor/bin/phpstan analyse web/modules/contrib/domain`

### phpstan config

We use the following `phpstan.neon` file:

```
parameters:
  level: 2
  ignoreErrors:
    # new static() is a best practice in Drupal, so we cannot fix that.
    - "#^Unsafe usage of new static#"
  drupal:
    entityMapping:
      domain:
        class: Drupal\domain\Entity\Domain
        storage: Drupal\domain\DomainStorage
      domain_alias:
          class: Drupal\domain_alias\Entity\DomainAlias
          storage: Drupal\domain_alias\DomainAliasStorage

```

The drupal entityMapping is also provided by `entity_mapping.neon` in the
project root, for use with other tests.

## Maintainers

- Ken Rickard - [agentrickard](https://www.drupal.org/u/agentrickard)
