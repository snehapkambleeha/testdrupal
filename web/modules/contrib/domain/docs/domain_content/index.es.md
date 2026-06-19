# Domain Content

El módulo Domain Content proporciona páginas de resumen de contenido y
editores por dominio, para que los administradores puedan revisar y
gestionar las entidades asignadas a dominios específicos.

## Páginas de resumen

El módulo añade dos páginas bajo `/admin/content/`:

| Página | Ruta | Permiso |
|--------|------|---------|
| **Contenido afiliado** | `/admin/content/domain-content` | `access domain content` |
| **Editores afiliados** | `/admin/content/domain-editors` | `access domain content editors` |

Cada página muestra una tabla que lista todos los dominios con un
contador de contenido o editores. Al hacer clic en el nombre de un
dominio se abre un listado detallado basado en Views.

Los usuarios con el permiso *publish to any domain* (o *assign editors
to any domain*) también ven una fila **All affiliates** que enlaza a
las entidades marcadas como visibles en todos los dominios.

## Permisos

| Permiso | Descripción |
|---------|-------------|
| `access domain content` | Ver el resumen de contenido afiliado. |
| `access domain content editors` | Ver el resumen de editores afiliados. |

## Views

Domain Content incluye dos Views opcionales:

### affiliated_content

Muestra los nodos asignados a un dominio determinado.

- **Page 1** — `/admin/content/domain-content/{domain_id}`: nodos de
  un dominio específico con filtros expuestos para estado, tipo de
  contenido, título e idioma.
- **Page 2** — `/admin/content/domain-content/all_affiliates`: nodos
  marcados como *all affiliates*, con un filtro de dominio expuesto
  adicional.

Ambas páginas muestran operaciones masivas, título, tipo de contenido,
autor, dominios asignados, indicador all-affiliates, estado, fecha de
actualización y operaciones.

### affiliated_editors

Muestra los usuarios asignados a un dominio determinado.

- **Page 1** — `/admin/content/domain-editors/{domain_id}`: editores
  de un dominio específico con filtros expuestos para estado, dominio,
  nombre de usuario, correo electrónico e idioma.
- **Page 2** — `/admin/content/domain-editors/all_affiliates`:
  editores marcados como *all affiliates*.

Ambas páginas muestran operaciones masivas, nombre de usuario, correo
electrónico, dominios asignados, indicador all-affiliates, estado,
fecha de creación y operaciones.

Las cuatro presentaciones utilizan un paginador de 50 elementos.

## Plugins de acceso de Views

El módulo proporciona dos plugins de acceso de Views que extienden
Domain Access:

| Plugin | ID | Requisito |
|--------|----|-----------|
| `DomainContentAccess` | `domain_content_editor` | `access domain content` + asignación de dominio |
| `DomainEditorAccess` | `domain_content_admin` | `access domain content editors` + asignación de dominio |

## Operaciones de dominio

Domain Content añade enlaces de operación **Content** y **Editors** a
la lista de dominios en `/admin/config/domain`. Estos enlaces se
muestran de forma condicional según los permisos del usuario actual y
sus asignaciones de dominio.

## Verificación de requisitos

En tiempo de ejecución, el módulo verifica que `field_domain_access` y
`field_domain_all_affiliates` existen en todos los tipos de nodo y en
la entidad de usuario. Se reporta un error de estado en
`/admin/reports/status` si falta algún campo.
