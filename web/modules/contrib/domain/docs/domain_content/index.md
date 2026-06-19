# Domain Content

The Domain Content module provides per-domain content and editor overview
pages so that administrators can review and manage entities assigned to
specific domains.

## Overview pages

The module adds two pages under `/admin/content/`:

| Page | Path | Permission |
|------|------|------------|
| **Affiliated content** | `/admin/content/domain-content` | `access domain content` |
| **Affiliated editors** | `/admin/content/domain-editors` | `access domain content editors` |

Each page displays a table listing every domain with a content or editor
count. Clicking a domain name opens a detailed Views-powered listing.

Users with the *publish to any domain* (or *assign editors to any domain*)
permission also see an **All affiliates** row that links to entities marked
as visible on all domains.

## Permissions

| Permission | Description |
|------------|-------------|
| `access domain content` | View the affiliated content overview. |
| `access domain content editors` | View the affiliated editors overview. |

## Views

Domain Content ships two optional Views:

### affiliated_content

Displays nodes assigned to a given domain.

- **Page 1** — `/admin/content/domain-content/{domain_id}`: nodes on a
  specific domain with exposed filters for status, content type, title,
  and language.
- **Page 2** — `/admin/content/domain-content/all_affiliates`: nodes marked
  as *all affiliates*, with an additional exposed domain filter.

Both pages show bulk operations, title, content type, author, assigned
domains, all-affiliates flag, status, updated date, and operations.

### affiliated_editors

Displays users assigned to a given domain.

- **Page 1** — `/admin/content/domain-editors/{domain_id}`: editors on a
  specific domain with exposed filters for status, domain, username, email,
  and language.
- **Page 2** — `/admin/content/domain-editors/all_affiliates`: editors
  marked as *all affiliates*.

Both pages show bulk operations, username, email, assigned domains,
all-affiliates flag, status, creation date, and operations.

All four displays use a pager of 50 items.

## Views access plugins

The module provides two Views access plugins that extend Domain Access:

| Plugin | ID | Requirement |
|--------|----|-------------|
| `DomainContentAccess` | `domain_content_editor` | `access domain content` + domain assignment |
| `DomainEditorAccess` | `domain_content_admin` | `access domain content editors` + domain assignment |

## Domain operations

Domain Content adds **Content** and **Editors** operation links to the domain
list at `/admin/config/domain`. These links are displayed conditionally based
on the current user's permissions and domain assignments.

## Requirements check

At runtime, the module verifies that `field_domain_access` and
`field_domain_all_affiliates` exist on all node types and the user entity. A
status error is reported at `/admin/reports/status` if any field is missing.
