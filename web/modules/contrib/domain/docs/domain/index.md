# Domain

The Domain module is the core of the Domain module suite. It provides domain
entity management, negotiation, and cross-domain URL rewriting.

## Cross-domain URL rewriting

Any code can target a specific domain when building a URL by setting the
`domain` option on a `Url` object. The `DomainPathProcessor` (outbound path
processor, priority 80) will then rewrite the URL to point to that domain.

### Usage

The `domain` option requires a `DomainInterface` entity object, similar to how
core's `language` option requires a `LanguageInterface`:

```php
use Drupal\Core\Url;

$domain = \Drupal::entityTypeManager()->getStorage('domain')->load('one_example_com');
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
$url->setOption('domain', $domain);
```

This produces an absolute URL pointing to the target domain, e.g.
`http://one.example.com/node/1`.

### How it works

When `DomainPathProcessor::processOutbound()` encounters a `domain` option, it:

1. **Validates the domain** -- checks that the option is a `DomainInterface`
   entity.
2. **Applies cross-domain features** (language negotiation, destination
   parameter -- see below).
3. **Rewrites the URL** by setting `base_url` to the target domain's path and
   forcing `absolute = TRUE`.
4. **Adds cache metadata** -- the domain entity as a cacheable dependency
   (for invalidation when the domain changes), and the `domain` cache
   context when the destination parameter feature is active.

### Integration with Domain Source

The Domain Source module uses this mechanism internally. When a content entity
has a source domain that differs from the active domain,
`DomainSourcePathProcessor` (priority 310) sets `$options['domain'] = $source`
and lets `DomainPathProcessor` handle the actual URL rewriting.

This means all cross-domain features described below apply automatically to
Domain Source rewrites as well as to any custom code that sets the `domain`
option.

## Experimental features

The following features are available under **Experimental features** on the
Domain settings page (`/admin/config/domain/settings`). All are disabled by
default.

### Path prefix support

The Domain module supports an optional **path prefix** on domain records,
allowing multiple domains to share a single hostname while being
distinguished by the first segment of the URL path (e.g.,
`example.com/fr/...` vs `example.com/benl/...`).

Check **"Enable path prefix support"** in the Domain settings to activate
this feature. When disabled, all path prefix components are removed from
the container for zero runtime overhead.

See the [Path prefix documentation](path_prefix.md) for full details.

- Config key: `domain.settings:path_prefix`
- Related issue: [#3575947](https://www.drupal.org/i/3575947)

### Language negotiation for cross-domain URLs

When a site uses multiple domains with different language negotiation settings
(e.g., one domain uses path prefixes like `/fr/...` while another uses
domain-based negotiation), outbound URLs need to be processed using the
language negotiation settings of their *target* domain, not the current one.

Check **"Enable language negotiation for cross-domain URLs"** in the Domain
settings to activate this feature.

When enabled, `DomainPathProcessor` will:

1. Compare the `language.negotiation` URL configuration between the active
   domain and the target domain (using Domain Config overrides).
2. If the configurations differ, re-run the `LanguageNegotiationUrl` outbound
   processor in the context of the target domain.
3. This ensures path prefixes and other URL-based negotiation methods are
   correctly applied for the target domain.

!!! note
    This triggers an additional language-negotiation pass only for URLs whose
    target domain has a different language negotiation configuration. URLs
    staying on the same domain or targeting a domain with identical
    configuration are unaffected.

- Config key: `domain.settings:language_negotiation`
- Related issue: [#3570178](https://www.drupal.org/i/3570178)

### Domain-scoped destination redirects

When a user follows a cross-domain link that includes a `destination` query
parameter (e.g., to log in or edit content on another domain), the standard
relative `destination` path would redirect them back to the *target* domain
rather than the *original* domain.

Check **"Allow domain-scoped destination redirects"** in the Domain settings
to activate this feature.

When enabled, `DomainPathProcessor` will:

1. Detect cross-domain links that include a `destination` query parameter
   matching the current request path.
2. Add a `destination_domain` query parameter containing the current domain's
   base URL (scheme + host).
3. On the target domain, the `DomainSubscriber` event subscriber reconstructs
   an absolute `destination` URL from both parameters, ensuring the user is
   redirected back to the correct page on the original domain.

**Example flow:**

1. User is on `http://example.com/admin/content`.
2. They click an edit link rewritten to `http://one.example.com/node/1/edit`.
3. With this feature enabled, the link becomes:
   `http://one.example.com/node/1/edit?destination=/admin/content&destination_domain=http://example.com`
4. After saving, the user is redirected to
   `http://example.com/admin/content`.

- Config key: `domain.settings:allow_destination_domain`
- Related issue: [#3570210](https://www.drupal.org/i/3570210)

### Early domain negotiation

If third-party middlewares need domain_config overrides before the kernel
request event, install the **Domain Early Negotiation** module
(`domain_early_negotiation`) from the
[Domain Extras](https://www.drupal.org/project/domain_extras) project. It
provides a `DomainNegotiationMiddleware` that negotiates the active domain
early in the middleware stack. Enabling the module activates the feature;
the middleware priority is configurable at
`/admin/config/domain/early-negotiation`.

## Drush commands

The Domain module provides Drush commands for managing domain records from
the command line.

### domain:list

Lists all domain records with their status and HTTP response.

```bash
drush domain:list
drush domain:list --inactive
drush domain:list --active
```

Aliases: `domains`, `domain-list`

```
 Machine name          Name      Hostname     Path prefix  Scheme  Status  Default  Response
 example_com           Default   example.com               https   Active  Default  200 - OK
 example_com_fr        French    example.com  fr           https   Active           200 - OK
 shop_example_com      Shop      shop.com                  https   Active           200 - OK
```

### domain:info

Displays general information about domains on the site.

```bash
drush domain:info
```

Aliases: `domain-info`, `dinf`

```
 All Domains              3
 Active Domains           3
 Default Domain ID        example_com
 Default Domain hostname  example.com
 Fields in Domain entity  id, domain_id, hostname, path_prefix, name, ...
 Domain admin entities    node, user
```

### domain:add

Creates a new domain record.

```bash
drush domain:add example.com 'My Site'
drush domain:add example.com 'My Site' --scheme=https
drush domain:add example.com 'My Site' --weight=10
drush domain:add example.com 'My Site' --inactive
drush domain:add example.com 'My Site' --is_default
drush domain:add example.com 'My Site' --validate
drush domain:add example.com 'French Site' --path-prefix=fr
```

```
Created the example.com with machine id example_com.
```

Aliases: `domain-add`

Options:

| Option | Description |
|--------|-------------|
| `--scheme` | `http`, `https`, or `variable`. Defaults to `http`. |
| `--weight` | Sort order for the domain. |
| `--inactive` | Create the domain as inactive. |
| `--is_default` | Set as the default domain. |
| `--validate` | Check URL response before saving. |
| `--path-prefix` | Path prefix for hostname sharing (see [Path prefix](path_prefix.md)). |

### domain:delete

Deletes a domain record and optionally reassigns its content and users.

```bash
drush domain:delete example.com
drush domain:delete example.com --content-assign=ignore
drush domain:delete example.com --users-assign=example_net
drush domain:delete all
drush domain:delete example.com --dryrun
```

Aliases: `domain-delete`

The default domain cannot be deleted. Use `domain:default` to set a new
default first. When deleting, you are prompted to reassign users to another
domain unless `--users-assign` is specified.

### domain:default

Sets a domain as the default.

```bash
drush domain:default example.com
drush domain:default example_org --validate
```

```
example_com set to primary domain.
```

Aliases: `domain-default`

### domain:enable / domain:disable

Activates or deactivates a domain.

```bash
drush domain:enable example.com
drush domain:disable example.com
```

```
example.com has been disabled.
```

Aliases: `domain-enable`, `domain-disable`

### domain:name

Changes a domain's label.

```bash
drush domain:name example.com 'New Name'
```

```
Renamed example.com to New Name.
```

Aliases: `domain-name`

### domain:scheme

Changes a domain's URL scheme.

```bash
drush domain:scheme example.com https
```

```
Scheme is now to "https." for example_com
```

Aliases: `domain-scheme`

Without a scheme argument, prompts for selection.

### domain:test

Tests domains for proper HTTP response.

```bash
drush domain:test
drush domain:test example.com
```

```
 Machine name      URL                          Response
 example_com       https://example.com          200 - OK
 example_com_fr    https://example.com          200 - OK
 shop_example_com  https://shop.example.com     200 - OK
```

Aliases: `domain-test`

### domain:replace

Replaces a string in all domain hostnames. Performs a dry run by default;
use `--force` to apply changes.

```bash
drush domain:replace "old.com" "new.com"
drush domain:replace "old.com" "new.com" --force
```

```
 Name     Current            New
 Default  example.old.com    example.new.com
 Shop     shop.old.com       shop.new.com
```

Aliases: `domain-replace`

### domain:generate

Generates test domains for development. Creates subdomains of the given
primary hostname.

```bash
drush domain:generate example.com
drush domain:generate example.com --count=25
drush domain:generate example.com --count=25 --empty
drush domain:generate example.com --scheme=https
```

Aliases: `gend`, `domgen`, `domain-generate`

Options:

| Option | Description |
|--------|-------------|
| `--count` | Number of domains to generate. Defaults to 15. |
| `--empty` | Truncate all domains before generating. |
| `--scheme` | `http`, `https`, or `variable`. |

### Domain identifier resolution

All commands that accept a `domain_id` argument resolve it in the
following order:

1. Machine name (e.g., `example_com`)
2. Hostname (e.g., `example.com`)

## Related issues

- [#3574800: Allow Url objects to specify the Domain as an option](https://www.drupal.org/i/3574800)
