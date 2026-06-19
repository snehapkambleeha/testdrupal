# Domain Source

El módulo Domain Source permite asignar un dominio canónico al
contenido para la generación de URLs. Domain Source garantiza que el
contenido que aparece en múltiples dominios siempre enlace a una
única URL.

## Cómo funciona

Cuando una entidad de contenido tiene un valor
`field_domain_source` diferente al dominio activo,
`DomainSourcePathProcessor` (prioridad 310) establece
`$options['domain']` en la URL y delega la reescritura real de la URL
a `DomainPathProcessor` (prioridad 80) en el módulo Domain principal.

Esto significa que todas las funcionalidades de URL entre dominios
proporcionadas por el módulo Domain (negociación de idioma, manejo del
parámetro destination) se aplican automáticamente a las reescrituras
de Domain Source.

## Token

Domain Source proporciona un token
`[node:canonical-source-domain-url]` que devuelve la URL absoluta
canónica de un nodo en su dominio fuente.

Si el nodo no tiene un dominio fuente asignado, el token recurre a la
URL canónica predeterminada.

### Uso con el módulo Metatag

El módulo Metatag reemplaza la etiqueta `<link rel="canonical">` de
Drupal core con la suya propia, resuelta a partir de un token
configurable (generalmente `[current-page:url]`). Como Metatag resuelve
su URL canónica a través del sistema de tokens en lugar de
`$entity->toUrl()`, **`DomainSourcePathProcessor` no la intercepta**
— la etiqueta canónica puede apuntar al dominio actual en lugar del
dominio fuente, incluso cuando canonical no está excluido.

Para solucionar esto, configure el campo **Canonical URL** en la
configuración de Metatag (`/admin/config/search/metatag`) con
`[node:canonical-source-domain-url]` para los tipos de contenido de
nodo. Esto garantiza que la etiqueta `<link rel="canonical">` siempre
apunte a la URL del dominio fuente.

### Uso con rutas canónicas excluidas

Este token también es útil cuando la ruta `canonical` se añade a la
configuración **Excluded entity route suffixes**
(`/admin/config/domain/domain_source`). Cuando canonical está excluido,
`DomainSourcePathProcessor` ya no reescribe las URLs canónicas al
dominio fuente — lo que reduce el cambio de enlaces entre dominios en
el contenido renderizado. El token permite generar la URL correcta del
dominio fuente donde sea necesario, por ejemplo en sitemaps XML o
notificaciones por correo electrónico.

## Funcionalidades entre dominios

Las siguientes funcionalidades se aplican a todas las reescrituras de
URL entre dominios, incluyendo las activadas por Domain Source. Se
configuran en la página de configuración del módulo Domain
(`/admin/config/domain/settings`) bajo **Experimental features**:

- **Language negotiation for cross-domain URLs** — garantiza que las
  URLs de salida usen la configuración de negociación de idioma de su
  dominio de destino.
- **Domain-scoped destination redirects** — garantiza que los usuarios
  sean redirigidos al dominio correcto después de completar una acción
  en un dominio diferente.

Consulte la [documentación del módulo Domain](../domain/index.md) para
más detalles sobre estas funcionalidades.

## Issues relacionados

- [#3570178: Language negotiation for cross-domain URLs](https://www.drupal.org/i/3570178)
- [#3570210: Domain-scoped destination redirects](https://www.drupal.org/i/3570210)
- [#3574800: Allow Url objects to specify the Domain as an option](https://www.drupal.org/i/3574800)
