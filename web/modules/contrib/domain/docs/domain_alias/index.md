# Domain Alias

The Domain Alias module allows multiple hostnames to map to a single domain
record. An alias can match an exact hostname or use wildcard patterns, and can
optionally redirect to the parent domain.

## Key principle: domain records hold canonical hostnames

Domain records should use your **canonical production hostnames** -- the
ones you want in generated URLs, sitemaps, and SEO metadata. Aliases then
serve two purposes:

1. **Environment mapping** -- local development, staging, and CI hostnames
   that resolve back to production domain records.
2. **Production redirects** -- alternate production hostnames (e.g.,
   `www.example.com`) that redirect to the canonical hostname via 301/302.

### Example

If your production site runs on `example.com` and `shop.example.com`,
create two domain records with those hostnames. Then add aliases:

| Alias pattern | Parent domain | Env | Redirect |
|---------------|---------------|-----|----------|
| `www.example.com` | `example.com` | default | 301 |
| `www.shop.example.com` | `shop.example.com` | default | 301 |
| `example.local` | `example.com` | local | -- |
| `shop.example.local` | `shop.example.com` | local | -- |
| `example.staging.acme.com` | `example.com` | staging | -- |
| `shop.staging.acme.com` | `shop.example.com` | staging | -- |

This approach ensures that:

- **Production URLs are always canonical** -- generated links, sitemaps, and
  SEO metadata use the real production hostnames.
- **Alternate hostnames redirect cleanly** -- visitors hitting
  `www.example.com` are redirected to `example.com` with the proper HTTP
  status code.
- **Environment rewriting works correctly** -- when visiting an alias in a
  non-default environment, all domain hostnames are rewritten to their
  corresponding environment aliases (see [Environments](#environments)).
- **Configuration overrides are predictable** -- Domain Config overrides are
  keyed by the domain record ID, which is derived from the canonical
  hostname.

**Do not** create domain records with development or staging hostnames and
then alias the production hostname to them. This inverts the intended
relationship and breaks environment rewriting, URL generation, and
configuration overrides.

## Alias properties

Each alias is a configuration entity with the following fields:

| Field | Description |
|-------|-------------|
| **Pattern** | The hostname pattern to match (max 80 characters). |
| **Redirect** | `0` = no redirect, `301` = permanent, `302` = temporary. |
| **Environment** | The server environment this alias belongs to. |
| **Weight** | Sort order for matching (lower = higher priority). |

Aliases are managed per domain at
`/admin/config/domain/alias/{domain}`.

## Pattern matching

When a request arrives that does not exactly match any registered domain
record, Domain Alias searches for a matching alias pattern.

### Matching order

1. **Exact domain record** -- handled by the base Domain module.
2. **Exact alias** -- an alias with no wildcards that matches the hostname.
3. **Wildcard aliases** -- sorted by specificity (fewer wildcards first,
   longer patterns first).

### Wildcard syntax

The `*` character matches one or more characters within a hostname segment.
A maximum of one wildcard per alias is allowed.

```
*.example.com        matches one.example.com, two.example.com
example.*.com        matches example.dev.com
example.*            matches example.com, example.local
*.com                matches anything.com
```

### Port matching

Ports can be included in alias patterns. The rules are:

- **Default ports (80, 443)**: a request on these ports matches aliases with
  or without a port specifier. For example, `example.com:80` matches both
  `example.com` and `example.com:80`.
- **Non-default ports**: a request on port 8080 only matches aliases that
  explicitly include a port. `example.com:8080` matches `example.com:8080`
  and `example.com:*`, but **not** `example.com`.

```
example.com:8080     matches only example.com:8080
example.com:*        matches example.com on any port
*.com:*              matches anything.com on any port
```

## Redirect

When an alias has a redirect value of `301` or `302`, the user is redirected
to the parent domain with the corresponding HTTP status code. This is useful
for consolidating traffic from alternate hostnames to a canonical domain.

For non-default environment aliases with a redirect, the redirect target is
resolved to the first non-redirect alias in the same environment rather than
the canonical hostname. This prevents redirecting development traffic to
production URLs.

## Path prefix interaction

When [path prefix support](../domain/path_prefix.md) is enabled, multiple
domains can share the same hostname (e.g., `example.com` with prefixes `fr`,
`benl`, etc.). Aliases resolve **hostnames only** — path prefix negotiation
happens automatically afterward.

### How it works

1. The alias resolves a hostname to its parent domain record.
2. If path prefix support is enabled, all domains sharing the resolved
   hostname are loaded.
3. `negotiateByPathPrefix()` selects the correct domain based on the
   current request path.

### Where to add aliases

If multiple domains share a hostname with different path prefixes, you only
need to add aliases to **one** of them — typically the domain without a
prefix. The alias form displays a warning when the parent domain uses a
path prefix, suggesting to add aliases to the unprefixed domain instead.

### Environment rewriting

During environment rewriting, domains that share the same canonical hostname
as the active domain (i.e., they differ only by path prefix) are rewritten
directly using the current request hostname — no additional alias lookup is
needed.

## Environments

Aliases can be tagged with an **environment** to support multi-environment
development workflows. When the active request matches an alias in a
non-default environment, all domain hostnames are rewritten to their
corresponding environment-specific aliases. This ensures that generated links
stay within the current environment.

### Default environments

- `default` -- canonical URLs, no rewriting occurs.
- `local` -- local development.
- `development` -- integration server.
- `staging` -- pre-deployment server.
- `testing` -- CI environments.

The list can be overridden in `settings.php` (see
[Configuration](#configuration)). The
[Domain Extras](https://www.drupal.org/project/domain_extras) project includes
a **Domain Alias Extras** submodule that provides a UI for customizing this
list.

!!! warning "Preview and CI environments must use a non-default environment"

    If you use wildcard aliases for preview or CI environments (e.g.
    `*.tugboatqa.com`, `*.ci-host.com`), assign them to a non-default
    environment such as `local` or `testing`. With the `default` environment,
    hostname rewriting is skipped, so generated links (including in the domain
    admin list) will point to the canonical production hostname instead of the
    actual preview URL.

### Example setup

Consider a site with three production domains:

| Domain | Hostname |
|--------|----------|
| Primary | `example.com` |
| Foo | `foo.example.com` |
| Bar | `bar.example.com` |

For local development, create aliases tagged as `local`:

| Alias | Parent domain | Environment |
|-------|---------------|-------------|
| `example.local` | `example.com` | local |
| `foo.example.local` | `foo.example.com` | local |
| `bar.example.local` | `bar.example.com` | local |

When a developer visits `foo.example.local`:

1. No exact domain record matches.
2. The alias `foo.example.local` matches, pointing to `foo.example.com`.
3. Because the alias is in the `local` environment, all domains have their
   hostnames rewritten: `example.com` becomes `example.local`,
   `bar.example.com` becomes `bar.example.local`.
4. All generated links on the page use `.local` domains.

### Wildcard environments

Wildcard aliases work with environments too. By placing the wildcard in the
TLD position, a single set of aliases covers multiple environments (`.local`,
`.dev`, `.test`, etc.) without duplication:

| Alias | Parent domain | Environment |
|-------|---------------|-------------|
| `example.*` | `example.com` | local |
| `foo.example.*` | `foo.example.com` | local |
| `bar.example.*` | `bar.example.com` | local |

When a developer visits `foo.example.local`:

1. The alias `foo.example.*` matches, capturing `local`.
2. For other domains, their `local` aliases are loaded and wildcards are
   replaced with the captured value: `example.*` becomes `example.local`,
   `bar.example.*` becomes `bar.example.local`.

The same aliases also work for `foo.example.dev`, `foo.example.test`, etc.
-- all resolved through the `local` environment.

## Validation rules

Alias records are validated via Symfony constraint plugins declared in the
configuration schema (`domain_alias.schema.yml`). These constraints run
automatically when saving through the admin form or Drush commands.

**Pattern** (`DomainAliasPattern` + `DomainAliasUniquePattern` constraints):

1. At least one dot required (except `localhost`).
2. Only one wildcard (`*` or `?`) per pattern.
3. Only one colon (`:`) for port specification.
4. After a colon, only an integer or `*` is allowed.
5. No leading or trailing dots.
6. ASCII characters only (unless `domain.settings:allow_non_ascii` is
   enabled).
7. Cannot match an existing domain hostname.
8. Must be unique across all aliases.

**Redirect** (`Choice` constraint):

Must be one of `0` (no redirect), `301`, or `302`.

**Environment** (`DomainAliasEnvironment` constraint):

Must be one of the values defined in
`domain_alias.settings:environments`.

## Cascade deletion

When a domain record is deleted, all its aliases are automatically deleted.

## Permissions

| Permission | Description |
|------------|-------------|
| `administer domain aliases` | Full control over all aliases. |
| `create domain aliases` | Create aliases (scoped to assigned domains). |
| `edit domain aliases` | Edit aliases (scoped to assigned domains). |
| `delete domain aliases` | Delete aliases (scoped to assigned domains). |
| `view domain aliases` | View aliases (scoped to assigned domains). |

## Drush commands

### domain-alias:list

Lists aliases with optional filters.

```bash
drush domain-alias:list
drush domain-alias:list --hostname=example.com
drush domain-alias:list --environment=local
drush domain-alias:list --redirect=301
```

```
 Machine name              Alias                  Domain       Environment  Redirect
 example_local             example.local          example_com  local        0: Do not redirect
 shop_example_local        shop.example.local     shop_com     local        0: Do not redirect
 www_example_com           www.example.com        example_com  default      301: Moved Permanently
```

Aliases: `domain-aliases`, `domain-alias-list`

### domain-alias:add

Creates a new alias for a domain.

```bash
drush domain-alias:add example.com test.example.com
drush domain-alias:add example.com test.example.com --environment=local
drush domain-alias:add example.com test.example.com --redirect=301
drush domain-alias:add example.com '*.example.local' --environment=local
```

```
Created the alias test.example.com with machine id test_example_com.
```

Aliases: `domain-alias-add`

Options:

| Option | Description |
|--------|-------------|
| `--machine_name` | Override the auto-generated machine name. |
| `--redirect` | `0` (no redirect), `301`, or `302`. Defaults to `0`. |
| `--environment` | Environment tag. Defaults to `default`. |

### domain-alias:update

Updates an existing alias.

```bash
drush domain-alias:update test.example.com --environment=local
drush domain-alias:update test.example.com --pattern=test2.example.com
drush domain-alias:update test.example.com --redirect=301
```

```
Domain Alias updated successfully.
```

Aliases: `domain-alias-update`

Options:

| Option | Description |
|--------|-------------|
| `--pattern` | Change the alias pattern. |
| `--redirect` | `0`, `301`, or `302`. |
| `--environment` | Change the environment tag. |

### domain-alias:delete

Deletes a single alias by pattern.

```bash
drush domain-alias:delete test.example.com
```

```
Domain Alias test.example.com with id test_example_com deleted.
```

Aliases: `domain-alias-delete`

### domain-alias:delete-bulk

Deletes multiple aliases for a domain, with optional filters.

```bash
drush domain-alias:delete-bulk example.com
drush domain-alias:delete-bulk example.com --environment=local
drush domain-alias:delete-bulk example.com --redirect=301
```

```
Aliases Deleted Successfully: (example_local) example.local, (star_example_local) *.example.local
```

Aliases: `domain-alias-delete-bulk`

## Performance

Domain Alias runs during every request as part of domain negotiation. Here
is a detailed breakdown of what happens and when.

### When the hostname matches a domain record (most common case)

On a production site, incoming requests typically match a domain record
directly. In that case Domain Alias does almost nothing:

1. The base Domain module finds an exact hostname match and sets the match
   type to `DOMAIN_MATCHED_EXACT`.
2. `hook_domain_request_alter()` fires. Domain Alias checks the match type,
   sees `DOMAIN_MATCHED_EXACT`, and **returns immediately** -- no alias
   lookup is performed.

**Cost:** one comparison against the match type constant. Negligible.

### When the hostname does not match any domain record

When the request hostname does not match any domain record (e.g., a
development or staging hostname), Domain Alias performs the following:

1. **Pattern generation** -- the hostname is split into segments and all
   possible wildcard combinations are generated (e.g., `dev.example.com`
   produces `*.example.com`, `dev.*.com`, `dev.example.*`, etc.). Port
   variants are appended if applicable. This is pure string manipulation
   on a small array (typically 3-4 segments).

2. **Pattern lookup** -- each generated pattern is checked against the
   alias config entity storage via `loadByProperties()`. Config entities
   are loaded from Drupal's config cache (in-memory after the first read
   in a request), **not** from the database. The lookup stops at the first
   match, so in the best case only one or two queries against the
   in-memory cache are needed.

3. **Domain load** -- the matched alias's parent domain is loaded by ID.
   Config entity storage uses a static cache, so if the domain was already
   loaded earlier in the request, this is a no-op.

4. **Path prefix disambiguation** -- if the resolved hostname is shared by
   multiple domains with different path prefixes, the negotiator sorts the
   candidates (typically 2-5 entries) by prefix length and performs one
   `str_starts_with()` check per candidate. No additional storage queries.

5. **Environment rewriting** (non-default environments only) -- when the
   matched alias belongs to a non-default environment (e.g., `local`),
   all domain entities have their hostnames rewritten on load via
   `hook_domain_load()`. Domains that share the same canonical hostname
   as the active domain (i.e. differ only by path prefix) are rewritten
   directly without any alias lookup. Other domains require loading
   aliases per domain per environment and resolving wildcard patterns.
   Results are cached in memory for the duration of the request, so
   repeated loads of the same domain do not trigger additional lookups.


### Performance characteristics

- **No database queries** -- all alias and domain lookups go through
  config entity storage, which reads from Drupal's config cache (populated
  once per request from the database or APCu/Redis if a cache backend is
  configured).
- **No external HTTP calls** -- alias resolution is entirely local.
- **No additional cache contexts** -- Domain Alias does not add cache
  contexts beyond what the base Domain module already provides (`domain`).

### Scaling considerations

The number of alias entities affects the size of the config cache but not
the per-request cost, because `loadByProperties()` filters in memory.
Sites with hundreds of aliases should see no measurable difference from
sites with a handful.

The main factor is the number of **domains** (not aliases). Environment
rewriting in `hook_domain_load()` runs once per loaded domain entity per
request. For most sites (fewer than 20 domains) this is negligible. Sites
with a very large number of domains should monitor the impact of
environment rewriting and consider whether all domains need environment
aliases.

## Configuration

- Add aliases at `/admin/config/domain/alias/{domain}`.
- All alias hostnames should be listed in `trusted_host_patterns` in
  `settings.php`.
- Override the environment list in `settings.php` if needed:

```php
$config['domain_alias.settings']['environments'] = [
  'default',
  'local',
  'development',
  'staging',
  'testing',
  'production',
];
```
