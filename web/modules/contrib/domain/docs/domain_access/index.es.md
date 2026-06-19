# Domain Access

El módulo Domain Access proporciona controles de acceso a nodos basados
en asignaciones de dominio. Permite que el contenido se publique en uno
o más dominios, restringe la edición por dominio y asigna usuarios como
editores por dominio.

## Campos

Domain Access crea automáticamente dos campos en cada tipo de nodo y en
la entidad de usuario:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `field_domain_access` | Referencia de entidad (domain) | Asigna la entidad a uno o más dominios. Obligatorio en nodos, opcional en usuarios. |
| `field_domain_all_affiliates` | Booleano | Cuando está marcado, la entidad es visible en (o el usuario puede editar en) **todos** los dominios. |

Una configuración de terceros en `field_domain_access` controla si las
nuevas entidades reciben automáticamente el dominio actual como valor
predeterminado.

## Sistema de acceso a nodos

Domain Access implementa el sistema de grants de acceso a nodos de
Drupal para controlar la visibilidad y la edición.

### Realms de grants

| Realm | Alcance |
|-------|---------|
| `domain_id` | Contenido publicado en un dominio específico |
| `domain_unpublished` | Contenido no publicado en un dominio específico |
| `domain_site` | Contenido publicado asignado a all affiliates (solo lectura) |

Cuando la configuración experimental **per-bundle grants** está
activada, se utilizan realms adicionales como
`domain_id:{bundle}` y `domain_unpublished:{bundle}` para un control
más granular.

### Cómo se asignan los grants

**Acceso de lectura:**

- Todos los visitantes reciben un grant para el dominio activo
  (`domain_id`) y el realm global (`domain_site`).
- Los usuarios con el permiso *view unpublished domain content* y
  acceso al dominio también reciben el grant `domain_unpublished`.

**Acceso de actualización y eliminación:**

- Requiere el permiso *edit domain content* o *delete domain content*.
- El usuario debe estar asignado al dominio (o tener *all affiliates*
  marcado).
- Con per-bundle grants, se verifican permisos por bundle como *update
  {bundle} content on assigned domains*.

!!! note
    Después de cambiar la configuración de acceso, reconstruya los
    permisos en `/admin/reports/status/rebuild`.

## Permisos

### Publicación de contenido

| Permiso | Descripción |
|---------|-------------|
| `publish to any domain` | Publicar contenido en todos los dominios y editar el campo *all affiliates*. |
| `publish to any assigned domain` | Publicar contenido en los dominios asignados al usuario. |
| `create domain content` | Crear contenido en los dominios asignados. |
| `create {bundle} content on assigned domains` | Control de creación por bundle. |
| `edit domain content` | Editar contenido en los dominios asignados. |
| `update {bundle} content on assigned domains` | Control de edición por bundle. |
| `delete domain content` | Eliminar contenido en los dominios asignados. |
| `delete {bundle} content on assigned domains` | Control de eliminación por bundle. |
| `view unpublished domain content` | Ver contenido no publicado en los dominios asignados. |

### Asignación de editores

| Permiso | Descripción |
|---------|-------------|
| `assign domain editors` | Asignar editores a los dominios asignados al usuario. |
| `assign editors to any domain` | Asignar editores a cualquier dominio. |

## Ajustes

El formulario de ajustes se encuentra en
`/admin/config/domain/domain_access`.

| Ajuste | Descripción |
|--------|-------------|
| **Move domain fields to advanced tab** | Coloca los campos de dominio en la barra lateral avanzada del formulario de nodos. |
| **Keep advanced tab open** | Abre la pestaña avanzada por defecto. |
| **Allow field removal** (experimental) | Permite eliminar los campos de Domain Access de tipos de entidad específicos. Requiere reconstruir los permisos. |
| **Per-bundle grants** (experimental) | Activa los grants de acceso a nodos por bundle para un control más granular. |

## Integración con Views

Domain Access registra varios plugins de Views:

| Tipo de plugin | ID | Descripción |
|----------------|----|-------------|
| Field | `domain_access_field` | Muestra los dominios asignados como enlaces a la entidad en cada dominio. |
| Filter | `domain_access_filter` | Filtra por asignación de dominio (ManyToOne, soporta OR/NOT). |
| Filter | `domain_access_current_all_filter` | Filtra el contenido disponible en el dominio actual o marcado como *all affiliates*. |
| Argument | `domain_access_argument` | Acepta un ID de dominio como filtro contextual. |
| Access | `domain_access_editor` | Restringe una presentación de Views a los usuarios que pueden editar contenido en sus dominios. |
| Access | `domain_access_admin` | Restringe una presentación de Views a los usuarios que pueden gestionar editores en sus dominios. |

## Acciones masivas

Cuando se crea un nuevo dominio, Domain Access registra automáticamente
cuatro configuraciones de acciones masivas:

- **Add content to {domain}** / **Remove content from {domain}**
- **Add editors to {domain}** / **Remove editors from {domain}**

Cuatro acciones globales adicionales están siempre disponibles:

- **Assign to all affiliates** / **Remove from all affiliates** (tanto
  para contenido como para editores).

Estas acciones se eliminan cuando el dominio correspondiente es
eliminado.

## Plugin de condición

El plugin de condición `domain_access` evalúa si un nodo está asignado
a uno o más dominios seleccionados. Puede utilizarse en Rules, Block
Visibility y sistemas similares. La condición soporta negación.

## Hooks

### Alter hooks

`hook_domain_references_alter(&$query, $account, $context)` — filtra
la lista de dominios disponibles en los widgets de referencia de
entidad según los permisos del usuario actual y sus asignaciones de
dominio.

### Ciclo de vida de entidades

Domain Access limpia su cache estática interna en presave y predelete
de entidades, y crea campos automáticamente cuando se añade un nuevo
tipo de nodo.

### Acceso a campos

El módulo restringe quién puede editar los campos de dominio:

- `field_domain_access` en **nodos** requiere *publish to any domain*
  o *publish to any assigned domain*.
- `field_domain_access` en **usuarios** requiere *assign domain
  editors* o *assign editors to any domain*.
- `field_domain_all_affiliates` requiere el permiso global
  correspondiente.

## Servicios

| Servicio | Clase | Función |
|----------|-------|---------|
| `domain_access.manager` | `DomainAccessManager` | Verificación central de acceso: grants de dominio, verificación de permisos, URLs de contenido. |
| `domain_access.helper` | `DomainAccessHelper` | Ayudante para la creación e instalación de campos. |
