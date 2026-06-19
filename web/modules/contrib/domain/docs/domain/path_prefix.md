# Path prefix

The Domain module supports an optional **path prefix** on domain records,
allowing multiple domains to share a single hostname while being distinguished
by the first segment of the URL path.

This is useful for sites that cannot add new hostnames (e.g., corporate
firewalls, shared hosting, single-origin CDN) but need separate domain
contexts for different audiences, regions, or brands.

## How it works

Each domain record has an optional `path_prefix` property (empty string by
default). When multiple domains share the same hostname with different
prefixes, the negotiator disambiguates them by matching the request path
against each domain's prefix.

### Example

Given three domain records:

| Domain name | Hostname | Path prefix | Purpose |
|-------------|----------|-------------|---------|
| Default | `example.com` | *(empty)* | Main site |
| Belgium NL | `example.com` | `benl` | Belgian Dutch |
| France | `example.com` | `fr` | French |

A request to `example.com/benl/fr/about-us` is processed as follows:

1. **Domain negotiation** -- the negotiator loads all domains matching
   `example.com`, finds three candidates, and matches the prefix `benl`
   against the request path `/benl/fr/about-us`.
2. **Inbound path processing** -- the `DomainPrefixPathProcessor` (priority
   350) strips the domain prefix, yielding `/fr/about-us`.
3. **Language negotiation** -- core's language negotiation strips the language
   prefix `fr`, yielding `/about-us`.
4. **Path alias resolution** -- Drupal resolves `/about-us` to the internal
   path (e.g., `/node/1`).

Outbound URL generation reverses the process:

1. **Path alias** -- `/node/1` becomes `/about-us`.
2. **Language processor** -- adds `fr/` prefix.
3. **Domain prefix processor** (priority 50) -- prepends `benl/` to the URL
   prefix, producing `benl/fr/about-us`.

### Prefix matching rules

- **Longest prefix first** -- when prefixes overlap (e.g., `fr` and `fr-be`),
  the longest matching prefix wins. A request to `/fr-be/page` matches
  `fr-be`, not `fr`.
- **Empty prefix as fallback** -- the domain with no prefix acts as the
  fallback when no prefix matches the request path.
- **Exact segment matching** -- the prefix must match a full path segment.
  `/france/page` does **not** match prefix `fr`.

## Configuration

### Enabling path prefix support

Path prefix support is disabled by default. To enable it, go to the Domain
settings page (`/admin/config/domain/settings`), expand **Experimental
features**, and check **Enable path prefix support**.

When disabled, all path prefix components are removed from the container
(zero runtime overhead), the path prefix field is hidden on the domain form,
and the prefix column is hidden on the domain list.

### Adding a path prefix to a domain

Once path prefix support is enabled, the domain add or edit form
(`/admin/config/domain/add` or `/admin/config/domain/edit/{domain}`)
displays a **Path prefix** field. The value should be a simple string
without leading or trailing slashes (e.g., `fr`, `benl`, `asia-pacific`).

### Uniqueness constraint

The combination of hostname and path prefix must be unique. Two domains may
share the same hostname only if their path prefixes differ. Attempting to
save two domains with the same hostname and the same prefix (including both
empty) will trigger a validation error.

### Backward compatibility

Existing domain records default to an empty path prefix. The feature is
disabled by default and must be enabled in `/admin/config/domain/settings`.

## Interaction with other modules

### Language negotiation (URL prefixes)

The domain prefix is the **outermost** path segment, placed before any
language prefix. The processing order is:

| Direction | Priority | Processor | Action |
|-----------|----------|-----------|--------|
| Inbound | 350 | `DomainPrefixPathProcessor` | Strips domain prefix |
| Inbound | 300 | `LanguageNegotiationUrl` | Strips language prefix |
| Outbound | 100 | `LanguageNegotiationUrl` | Adds language prefix |
| Outbound | 50 | `DomainPrefixPathProcessor` | Prepends domain prefix |

A URL like `/benl/fr/about-us` is decomposed as:

```
/benl/fr/about-us
 ^^^^           → domain prefix (stripped first inbound, added last outbound)
      ^^        → language prefix
         ^^^^^^^^ → path alias
```

### Domain Access

Domain Access assigns content visibility per domain. Path-prefixed domains
are full domain entities, so Domain Access field values and node grants
work identically -- each prefixed domain can have its own content
assignments.

### Domain Config / Domain Config UI

Domain Config provides per-domain configuration overrides. Each prefixed
domain is a distinct config entity, so it receives its own configuration
overrides as expected.

### Domain Alias

Domain Alias provides alternate hostnames for a domain. Aliases match by
hostname, not by path prefix.

**Important:** when multiple domains share the same hostname with different
path prefixes, you only need to create aliases on the **non-prefixed
(default) domain** for that hostname. The alias resolves the hostname; the
prefix negotiation then selects the correct domain based on the URL path.
Creating the same alias pattern on a prefixed domain will fail because
alias patterns are globally unique.

For example, if `example.com` (no prefix) and `example.com` (prefix `fr`)
share a hostname, add `*.example.com` as an alias on the non-prefixed
domain only. Requests to `something.example.com/fr/page` will resolve the
alias to `example.com`, then the prefix negotiation picks the `fr` domain.

### Domain Source

Domain Source assigns a canonical domain to content for URL generation. When
a content entity's source domain has a path prefix, the generated URL
automatically includes the prefix.

### Domain Path

Domain Path operates on internal paths (after inbound prefix stripping), so
it works without modification.

### Domain Content

Domain Content provides per-domain content overview pages. Each prefixed
domain appears as a separate entry in the domain filter.

## Technical details

### Programmatic usage

```php
// Get the path prefix of a domain entity.
$prefix = $domain->getPathPrefix();

// Set the path prefix.
$domain->setPathPrefix('benl');
$domain->save();

// Load all domains sharing a hostname.
$storage = \Drupal::entityTypeManager()->getStorage('domain');
$domains = $storage->loadMultipleByHostname('example.com');

// getBasePath() returns scheme + hostname + base_path (no prefix).
// Use this when building base URLs for outbound path processors.
$base = $domain->getBasePath();
// e.g. "http://example.com/"

// getPath() returns the full path including the prefix.
// Use this for display and direct linking.
$path = $domain->getPath();
// e.g. "http://example.com/fr/"
```

### Outbound URL generation

The `DomainPrefixPathProcessor` prepends the prefix to the `prefix` option
used by Drupal's URL generator. For URLs generated with the `domain` option
(see [Cross-domain URL rewriting](index.md#cross-domain-url-rewriting)),
the target domain's prefix is used. For all other URLs, the active domain's
prefix is used.

```php
use Drupal\Core\Url;

// URL targeting a prefixed domain includes the prefix automatically.
$domain = $storage->load('example_com_fr');
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
$url->setOption('domain', $domain);
// Generates: http://example.com/fr/node/1
```

### Subdirectory installs

Path prefix support works correctly when Drupal runs in a subdirectory
(e.g., `example.com/drupal/`). The `setUrl()` and `setPath()` methods
use Symfony's `Request::getPathInfo()` and `Request::getBasePath()` to
separate the subdirectory base path from the route path before prefix
manipulation. The resulting URL preserves the correct order:
`scheme + hostname + base_path + prefix + route_path`.

For example, with base path `/drupal/` and prefix `fr`, a request to
`/drupal/fr/admin/config` produces the URL
`http://example.com/drupal/fr/admin/config`.

### Performance

The feature has no measurable performance impact:

- **When disabled** -- the path prefix path processor, language negotiation
  override, and prefix negotiation logic are completely removed from the
  container. Zero runtime overhead.
- **When enabled but no domain uses a prefix** -- all code paths
  early-return on empty string checks.
- **When prefixes are active** -- the negotiator sorts a small in-memory
  array (typically 2-5 entries) and does one string comparison per candidate.
  No additional storage queries are issued.
- **No new cache contexts** -- the outbound processor adds the `domain`
  cache context, which is already present on every page of a domain-aware
  site. No additional cache fragmentation is introduced.

### Non-ASCII prefixes

The **Allow non-ASCII characters** setting on the Domain settings page
(`/admin/config/domain/settings`) also applies to path prefixes. When
enabled, Unicode lowercase letters and numbers are accepted in prefixes
(e.g., `belgië`, `日本`). When disabled (the default), only ASCII
lowercase `a-z`, digits `0-9`, hyphens, and underscores are permitted.

### Config schema

The `path_prefix` field is declared in `domain.schema.yml` as a `string`
property on `domain.record.*` with a `Regex` constraint using Unicode
character classes (`\p{L}`, `\p{N}`) as a permissive baseline:

```yaml
domain.record.*:
  type: config_entity
  mapping:
    # ... existing fields ...
    path_prefix:
      type: string
      label: 'Path prefix'
      constraints:
        Regex:
          pattern: '/^([\p{L}\p{N}][\p{L}\p{N}_\-]*)?$/u'
          message: 'The path prefix may only contain ...'
```

The stricter ASCII-only check is enforced at form validation and entity
`preSave()` time when the **Allow non-ASCII characters** setting is
disabled. The schema regex serves as a baseline that catches completely
invalid values during config imports.

This makes the `domain.record.*` schema fully validatable via
`TypedConfigManager::createFromNameAndData()->validate()`, catching
invalid values during config imports and form validation without
requiring a save.

## Related issues

- [#3575947: Support path-prefix-based domain separation on a single hostname](https://www.drupal.org/i/3575947)
