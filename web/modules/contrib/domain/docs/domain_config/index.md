# Domain Config

The Domain Config module provides per-domain configuration overrides. It allows
each domain to have its own site name, theme settings, language negotiation, or
any other configuration -- all from a single Drupal installation.

## Architecture: 2.x vs 3.x

Domain Config 3.x is a complete redesign of how per-domain configuration
overrides are stored and resolved. The 2.x version used a custom in-house
solution, while 3.x leverages Drupal's native **Config Collections API**.

### The 2.x approach (legacy)

In Domain 2.x, per-domain overrides were stored as **separate configuration
objects** in the default collection, using a naming convention:

```
domain.config.{domain_id}.{config_name}
domain.config.{domain_id}.{language_code}.{config_name}
```

For example, overriding the site name on `one_example_com`:

```
domain.config.one_example_com.system.site
```

And a French-specific override:

```
domain.config.one_example_com.fr.system.site
```

These objects lived alongside all other configuration in the default storage.
At runtime, a custom resolver would intercept configuration loads, detect the
active domain, construct the override name, attempt to load it, and merge the
result on top of the base configuration.

**Limitations of this approach:**

- Config objects polluted the default collection namespace.
- The naming scheme was fragile -- the regex to parse domain ID, language code,
  and config name out of a flat string was error-prone.
- No integration with Drupal's `ConfigFactoryOverrideInterface` -- the override
  mechanism was entirely custom.
- Config export/import did not understand these as overrides -- they appeared as
  regular configuration objects.
- Language-specific overrides had to be handled separately from Drupal's own
  `LanguageConfigOverride` system.
- Config events (save, delete, rename) required manual propagation.

### The 3.x approach (config collections)

Domain Config 3.x stores overrides in **Drupal config collections** -- a
first-class Drupal feature designed exactly for this purpose. Collections are
virtual partitions of the config storage that share the same backend (file
system, database, etc.) but are logically separate.

**Collection naming:**

| Type | Format | Example |
|------|--------|---------|
| Domain-only | `domain.{domain_id}` | `domain.one_example_com` |
| Domain + language | `domain.{domain_id}.language.{lang_code}` | `domain.one_example_com.language.fr` |

Within a collection, the configuration object keeps its original name. For
example, the site name override for `one_example_com` is stored as
`system.site` inside the `domain.one_example_com` collection -- not as a
mangled flat name.

**Key benefits:**

- Clean separation between base config and overrides.
- Standard Drupal API (`ConfigFactoryOverrideInterface`).
- Proper config export/import support -- collections are exported as
  subdirectories.
- Automatic cascade: base config &rarr; domain override &rarr; domain+language
  override.
- Config events (save, delete, rename) are handled automatically.
- Integration with Drupal's `LanguageConfigOverride` for language-specific
  overrides.

## How it works at runtime

### Override resolution

When Drupal loads a configuration object (e.g., `system.site`), the config
factory asks all registered override services to provide their overrides. Domain
Config registers two override services, applied in order:

1. **`domain.config_factory_override`** (priority -253) -- loads the
   domain-only override from the `domain.{domain_id}` collection.
2. **`domain.language.config_factory_override`** (priority -252) -- loads the
   domain+language override from the
   `domain.{domain_id}.language.{lang_code}` collection.

The final merged result follows this cascade:

```
Base config (default collection)
  ↓ merged with
Domain override (domain.{domain_id} collection)
  ↓ merged with
Domain+language override (domain.{domain_id}.language.{lang_code} collection)
  = Final runtime config
```

**Example** with `system.site` on domain `two_example_com`, language `es`:

```yaml
# Base config (default collection):
system.site:
  name: "My Site"

# Domain override (domain.two_example_com collection):
system.site:
  name: "Two"        # overrides "My Site" → "Two"

# Domain+language override (domain.two_example_com.language.es collection):
system.site:
  name: "Dos"        # overrides "Two" → "Dos"

# Final result at runtime: name = "Dos"
```

### Domain context

The active domain is determined by `DomainNegotiationContext`, which is injected
into both override services. The context is set during the kernel request event
by `DomainSubscriber` and can also be switched programmatically (e.g., by Domain
Config itself when comparing configurations across domains).

When no domain is active (e.g., during Drush commands without a domain
context), no overrides are applied and the base configuration is used.

!!! tip "Early negotiation for middlewares"
    If third-party middlewares need domain_config overrides before the kernel
    request event fires, install the **Domain Early Negotiation** module
    (`domain_early_negotiation`) from the
    [Domain Extras](https://www.drupal.org/project/domain_extras) project.
    See the [Domain documentation](../domain/index.md#early-domain-negotiation)
    for details.

### Caching

Each override service provides a **cache suffix** based on the current domain
ID (and language code for the language override). This ensures that config
objects cached for one domain are not served to another.

The cache metadata includes the `domain` cache context, so rendered output
that depends on domain-specific configuration is properly varied.

## Config lifecycle events

Domain Config 3.x properly handles configuration lifecycle events to keep
domain overrides in sync with base configuration:

| Event | Behavior |
|-------|----------|
| **Config save** | For each domain, if a domain override exists for the saved config, it is filtered to remove values that are identical to the new base config (keeping only actual overrides). |
| **Config delete** | If the base config is deleted, the corresponding domain override is deleted from all domain collections. |
| **Config rename** | If the base config is renamed, the override is renamed in all domain collections to match. |

## Config export and import

Because overrides live in proper Drupal collections, they integrate with the
config export/import system:

**Export directory structure:**

Drupal's `FileStorage` converts dots in collection names to directory
separators. The collection `domain.one_example_com` becomes the directory
`domain/one_example_com/`, and `domain.one_example_com.language.fr` becomes
`domain/one_example_com/language/fr/`:

```
config/sync/
  system.site.yml                              # Base config
  domain/
    one_example_com/
      system.site.yml                          # Domain override
      language/
        fr/
          system.site.yml                      # Domain+language override
    two_example_com/
      system.site.yml
```

Modules can also ship default domain overrides using the same convention in
their `config/install/` directory:

```
mymodule/config/install/
  domain/
    one_example_com/
      system.site.yml
    two_example_com/
      language/
        en/
          system.site.yml
```

When a new domain entity is created, `installDomainOverrides()` calls Drupal's
`ConfigInstallerInterface::installCollectionDefaultConfig()` to install any
module-provided defaults for that domain's collection.

## Domain Config UI

The optional **Domain Config UI** module provides a user interface for managing
per-domain overrides directly from existing configuration forms.

See the [Domain Config UI documentation](../domain_config_ui/index.md) for
details on:

- Enabling/disabling overrides per configuration per domain.
- The inline toggle on admin forms.
- Disallowed configurations.
- Programmatic control via alter hooks.

## Migration from 2.x to 3.x

An automatic migration is provided via `domain_config_update_10001()`. The
migration service (`DomainConfigMigration`) performs the following steps:

1. **Scans legacy config objects** -- finds all `domain.config.{domain_id}.*`
   entries in the default collection.
2. **Parses the legacy name** -- extracts the domain ID, optional language
   code, and config name using the pattern:
   ```
   /^domain\.config\.{domain_id}(?:\.([a-z]{2}))?\.([^.]+\.[^.]+)$/
   ```
3. **Writes to collections** -- copies the data into the appropriate
   `domain.{domain_id}` or `domain.{domain_id}.language.{lang_code}`
   collection.
4. **Updates the registry** -- if Domain Config UI is installed, updates the
   `overridable_configurations` setting in `domain_config_ui.settings`.
5. **Cleans up** -- deletes the legacy `domain.config.*` objects from the
   default collection.

The migration runs automatically during `drush updatedb`. If any domain fails
to migrate, the update hook throws an `UpdateException` with details.

## Services

| Service | Class | Role |
|---------|-------|------|
| `domain.config_factory_override` | `DomainConfigFactoryOverride` | Domain-only config overrides (priority -253) |
| `domain.language.config_factory_override` | `DomainLanguageConfigFactoryOverride` | Domain+language config overrides (priority -252) |
| `domain.language_manager` | `DomainConfigLanguageManager` | Decorates `language_manager` to integrate domain language overrides |
| `domain_config.library.discovery.collector` | `DomainConfigLibraryDiscoveryCollector` | Decorates library discovery to vary by domain |
| `domain_config.config_migration` | `DomainConfigMigration` | 2.x &rarr; 3.x migration service |

## Related issues

- [Domain Config collections](https://www.drupal.org/project/domain/issues/3221779)
