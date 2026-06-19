# Domain Access

The Domain Access module provides node access controls based on domain
assignments. It allows content to be published to one or more domains,
restricts editing by domain, and assigns users as per-domain editors.

## Fields

Domain Access automatically creates two fields on every node type and on the
user entity:

| Field | Type | Description |
|-------|------|-------------|
| `field_domain_access` | Entity reference (domain) | Assigns the entity to one or more domains. Required on nodes, optional on users. |
| `field_domain_all_affiliates` | Boolean | When checked, the entity is visible on (or the user can edit on) **all** domains. |

A third-party setting on `field_domain_access` controls whether new entities
automatically receive the current domain as a default value.

## Node access system

Domain Access implements Drupal's node access grant system to control
visibility and editing.

### Grant realms

| Realm | Scope |
|-------|-------|
| `domain_id` | Published content on a specific domain |
| `domain_unpublished` | Unpublished content on a specific domain |
| `domain_site` | Published content assigned to all affiliates (view only) |

When the experimental **per-bundle grants** setting is enabled, additional
realms like `domain_id:{bundle}` and `domain_unpublished:{bundle}` are used
for more granular control.

### How grants are assigned

**View access:**

- All visitors receive a grant for the active domain (`domain_id`) and the
  global realm (`domain_site`).
- Users with the *view unpublished domain content* permission and domain
  access also receive the `domain_unpublished` grant.

**Update and delete access:**

- Requires the *edit domain content* or *delete domain content* permission.
- The user must be assigned to the domain (or have *all affiliates* checked).
- With per-bundle grants, per-bundle permissions like *update {bundle} content
  on assigned domains* are checked instead.

!!! note
    After changing access settings, rebuild permissions at
    `/admin/reports/status/rebuild`.

## Permissions

### Content publishing

| Permission | Description |
|------------|-------------|
| `publish to any domain` | Publish content to all domains and edit the *all affiliates* field. |
| `publish to any assigned domain` | Publish content to the user's assigned domains. |
| `create domain content` | Create content on assigned domains. |
| `create {bundle} content on assigned domains` | Per-bundle creation control. |
| `edit domain content` | Edit content on assigned domains. |
| `update {bundle} content on assigned domains` | Per-bundle editing control. |
| `delete domain content` | Delete content on assigned domains. |
| `delete {bundle} content on assigned domains` | Per-bundle deletion control. |
| `view unpublished domain content` | View unpublished content on assigned domains. |

### Editor assignment

| Permission | Description |
|------------|-------------|
| `assign domain editors` | Assign editors to the user's assigned domains. |
| `assign editors to any domain` | Assign editors to any domain. |

## Settings

The settings form is at `/admin/config/domain/domain_access`.

| Setting | Description |
|---------|-------------|
| **Move domain fields to advanced tab** | Places domain fields in the node form's advanced sidebar. |
| **Keep advanced tab open** | Opens the advanced tab by default. |
| **Allow field removal** (experimental) | Allows removing domain access fields from specific entity types. Requires a permission rebuild. |
| **Per-bundle grants** (experimental) | Enables per-bundle node access grants for more granular control. |

## Views integration

Domain Access registers several Views plugins:

| Plugin type | ID | Description |
|-------------|----|-------------|
| Field | `domain_access_field` | Displays assigned domains as links to the entity on each domain. |
| Filter | `domain_access_filter` | Filters by domain assignment (ManyToOne, supports OR/NOT). |
| Filter | `domain_access_current_all_filter` | Filters to content available on the current domain or marked as *all affiliates*. |
| Argument | `domain_access_argument` | Accepts a domain ID as a contextual filter. |
| Access | `domain_access_editor` | Restricts a Views display to users who can edit content on their domains. |
| Access | `domain_access_admin` | Restricts a Views display to users who can manage editors on their domains. |

## Bulk actions

When a new domain is created, Domain Access automatically registers four
bulk action configurations:

- **Add content to {domain}** / **Remove content from {domain}**
- **Add editors to {domain}** / **Remove editors from {domain}**

Four additional global actions are always available:

- **Assign to all affiliates** / **Remove from all affiliates** (for both
  content and editors).

These actions are deleted when the corresponding domain is removed.

## Condition plugin

The `domain_access` condition plugin evaluates whether a node is assigned to
one or more selected domains. It can be used in Rules, Block Visibility, and
similar systems. The condition supports negation.

## Hooks

### Alter hooks

`hook_domain_references_alter(&$query, $account, $context)` — filters the
list of available domains in entity reference widgets based on the current
user's permissions and domain assignments.

### Entity lifecycle

Domain Access clears its internal static cache on entity presave and
predelete, and auto-creates fields when a new node type is added.

### Field access

The module restricts who can edit the domain fields:

- `field_domain_access` on **nodes** requires *publish to any domain* or
  *publish to any assigned domain*.
- `field_domain_access` on **users** requires *assign domain editors* or
  *assign editors to any domain*.
- `field_domain_all_affiliates` requires the corresponding global permission.

## Services

| Service | Class | Role |
|---------|-------|------|
| `domain_access.manager` | `DomainAccessManager` | Core access checking: domain grants, permission checks, content URLs. |
| `domain_access.helper` | `DomainAccessHelper` | Field creation and installation helper. |
