# Domain Config UI

The Domain Config UI module provides a lightweight way to enable per-domain
configuration overrides directly from existing configuration forms. When
viewing a supported configuration form within a domain context, privileged
users will see an inline toggle to enable (or remove) a domain-specific
override for that configuration object.

## How it works

- When a user with the appropriate permissions visits an admin configuration
  form on a domain host (e.g., `https://one.example.com`), the module
  inspects the form to identify the underlying configuration object(s).
- If the configuration is allowed to be overridden per domain, an inline
  action link appears at the top of the form:
    - Enable domain configuration
    - Remove domain configuration (after it has been enabled)
- Once enabled, changes submitted on that form are stored as per-domain
  overrides, without altering the global configuration.

## Disallowed configurations

You can explicitly prevent certain configuration objects from being
overridden per domain. When a configuration name is disallowed, the inline
toggle link is hidden on the corresponding form.

### Where to configure

- UI: Administration > Configuration > Domain > Domain Config UI
  (`/admin/config/domain/config-ui`)
- Config key: `domain_config_ui.settings: disallowed_configurations`

### Example

To forbid domain-level overrides for the Site Information form
(`system.site`) and the Theme settings (`system.theme`), set the following
in your configuration (YAML), or via the settings form:

```yaml
domain_config_ui.settings:
  disallowed_configurations:
    - system.site
    - system.theme
```

With the above, the "Enable domain configuration" link will no longer
appear on:

- Site Information: `/admin/config/system/site-information` (`system.site`)
- Appearance and theme settings pages that map to `system.theme`

### Notes

- The module also prevents overriding its own settings by default.
- The check is enforced server-side via the configuration factory, not just
  hidden in the UI.

### Related issue

- [#3562763](https://www.drupal.org/project/domain/issues/3562763)

## Programmatic control

Two alter hooks allow advanced control over where the toggle is shown and
which configuration objects are eligible for per-domain overrides.

### Disallowed configurations alter

Prevent domain overrides for specific configuration names globally:

```php
/**
 * Implements hook_domain_config_ui_disallowed_configurations_alter().
 */
function mymodule_domain_config_ui_disallowed_configurations_alter(array &$disallowed): void {
  // Disallow domain overrides for image toolkit settings site-wide.
  $disallowed[] = 'system.image';
}
```

### Disallowed routes alter

Hide the toggle entirely on specific routes (even if the underlying config
would normally be allowed):

```php
/**
 * Implements hook_domain_config_ui_disallowed_routes_alter().
 */
function mymodule_domain_config_ui_disallowed_routes_alter(array &$routes): void {
  // Do not show the toggle on the account settings page.
  $routes[] = 'entity.user.admin_form';
}
```

## Permissions overview

Common permissions used by this module include:

- `use domain config ui` — see and use the inline toggle on allowed forms
- `administer domain config ui` — manage settings
- `set default domain configuration` — manage default vs. domain-specific
  values
- `translate domain configuration` — manage language-specific overrides per
  domain

Ensure users operate within a domain context (i.e., visiting the site on a
domain's host) for the toggle to be available.

## Testing references

The repository includes functional and JavaScript tests that illustrate
expected behavior:

- `DomainConfigUISettingsTest` — enabling/removing overrides from common
  forms
- `DomainConfigUIDisallowedConfigurationsTest` — verifies that adding
  `system.site` to `disallowed_configurations` hides the toggle on Site
  Information
- `DomainConfigUIOptionsTest` and `DomainConfigUIPermissionsTest` —
  permissions and options coverage

These tests can serve as examples when integrating the feature in custom
modules.
