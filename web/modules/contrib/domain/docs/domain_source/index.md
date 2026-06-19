# Domain Source

The Domain Source module allows content to be assigned a canonical domain when
writing URLs. Domain Source will ensure that content that appears on multiple
domains always links to one URL.

## How it works

When a content entity has a `field_domain_source` value that differs from the
active domain, `DomainSourcePathProcessor` (priority 310) sets
`$options['domain']` on the URL and delegates the actual URL rewriting to
`DomainPathProcessor` (priority 80) in the core Domain module.

This means that all cross-domain URL features provided by the Domain module
(language negotiation, destination parameter handling) apply automatically to
Domain Source rewrites.

## Token

Domain Source provides a `[node:canonical-source-domain-url]` token that
returns the absolute canonical URL for a node on its source domain.

If the node has no source domain assigned, the token falls back to the default
canonical URL.

### Usage with the Metatag module

The Metatag module replaces Drupal core's `<link rel="canonical">` tag with
its own, resolved from a configurable token (typically `[current-page:url]`).
Because Metatag resolves its canonical URL through the token system rather
than through `$entity->toUrl()`, **`DomainSourcePathProcessor` does not
intercept it** -- the canonical tag may point to the current domain instead of
the source domain, even when canonical is not excluded.

To fix this, set the **Canonical URL** field in the Metatag configuration
(`/admin/config/search/metatag`) to `[node:canonical-source-domain-url]` for
node content types. This ensures the `<link rel="canonical">` tag always
points to the source domain URL.

### Usage with excluded canonical routes

This token is also useful when the `canonical` route is added to the
**Excluded entity route suffixes** setting
(`/admin/config/domain/domain_source`). When canonical is excluded,
`DomainSourcePathProcessor` no longer rewrites canonical URLs to the source
domain -- which reduces cross-domain link switching in rendered content. The
token lets you still generate the correct source domain URL where it matters,
for example in XML sitemaps or email notifications.

## Cross-domain features

The following features apply to all cross-domain URL rewrites, including those
triggered by Domain Source. They are configured in the Domain module settings
(`/admin/config/domain/settings`) under **Experimental features**:

- **Language negotiation for cross-domain URLs** -- ensures outbound URLs use
  the language negotiation settings of their target domain.
- **Domain-scoped destination redirects** -- ensures users are redirected back
  to the correct domain after completing an action on a different domain.

See the [Domain module documentation](../domain/index.md) for full details on
these features.

## Related issues

- [#3570178: Language negotiation for cross-domain URLs](https://www.drupal.org/i/3570178)
- [#3570210: Domain-scoped destination redirects](https://www.drupal.org/i/3570210)
- [#3574800: Allow Url objects to specify the Domain as an option](https://www.drupal.org/i/3574800)
