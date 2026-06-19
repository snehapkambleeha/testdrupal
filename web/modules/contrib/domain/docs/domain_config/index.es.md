# Domain Config

El módulo Domain Config proporciona sobrescrituras de configuración por
dominio. Permite que cada dominio tenga su propio nombre de sitio,
configuración de tema, negociación de idioma o cualquier otra
configuración — todo desde una única instalación de Drupal.

## Arquitectura: 2.x vs 3.x

Domain Config 3.x es un rediseño completo de cómo se almacenan y
resuelven las sobrescrituras de configuración por dominio. La versión
2.x utilizaba una solución interna personalizada, mientras que 3.x
aprovecha la **Config Collections API** nativa de Drupal.

### El enfoque 2.x (legacy)

En Domain 2.x, las sobrescrituras por dominio se almacenaban como
**objetos de configuración separados** en la colección por defecto,
usando una convención de nombres:

```
domain.config.{domain_id}.{config_name}
domain.config.{domain_id}.{language_code}.{config_name}
```

Por ejemplo, sobrescribir el nombre del sitio en `one_example_com`:

```
domain.config.one_example_com.system.site
```

Y una sobrescritura específica para francés:

```
domain.config.one_example_com.fr.system.site
```

Estos objetos vivían junto a toda la demás configuración en el
almacenamiento por defecto. En tiempo de ejecución, un resolver
personalizado interceptaba las cargas de configuración, detectaba el
dominio activo, construía el nombre de la sobrescritura, intentaba
cargarla y fusionaba el resultado sobre la configuración base.

**Limitaciones de este enfoque:**

- Los objetos de configuración contaminaban el espacio de nombres de la
  colección por defecto.
- El esquema de nombres era frágil — la expresión regular para extraer
  el ID de dominio, el código de idioma y el nombre de configuración de
  una cadena plana era propensa a errores.
- Sin integración con `ConfigFactoryOverrideInterface` de Drupal — el
  mecanismo de sobrescritura era completamente personalizado.
- La exportación/importación de configuración no entendía estos
  elementos como sobrescrituras — aparecían como objetos de
  configuración regulares.
- Las sobrescrituras específicas de idioma debían gestionarse por
  separado del propio sistema `LanguageConfigOverride` de Drupal.
- Los eventos de configuración (save, delete, rename) requerían
  propagación manual.

### El enfoque 3.x (config collections)

Domain Config 3.x almacena las sobrescrituras en **colecciones de
configuración de Drupal** — una funcionalidad nativa de Drupal diseñada
exactamente para este propósito. Las colecciones son particiones
virtuales del almacenamiento de configuración que comparten el mismo
backend (sistema de archivos, base de datos, etc.) pero están
lógicamente separadas.

**Nomenclatura de colecciones:**

| Tipo | Formato | Ejemplo |
|------|---------|---------|
| Solo dominio | `domain.{domain_id}` | `domain.one_example_com` |
| Dominio + idioma | `domain.{domain_id}.language.{lang_code}` | `domain.one_example_com.language.fr` |

Dentro de una colección, el objeto de configuración conserva su nombre
original. Por ejemplo, la sobrescritura del nombre del sitio para
`one_example_com` se almacena como `system.site` dentro de la colección
`domain.one_example_com` — no como un nombre aplanado modificado.

**Beneficios clave:**

- Separación limpia entre la configuración base y las sobrescrituras.
- API estándar de Drupal (`ConfigFactoryOverrideInterface`).
- Soporte adecuado de exportación/importación de configuración — las
  colecciones se exportan como subdirectorios.
- Cascada automática: configuración base &rarr; sobrescritura de
  dominio &rarr; sobrescritura de dominio+idioma.
- Los eventos de configuración (save, delete, rename) se gestionan
  automáticamente.
- Integración con `LanguageConfigOverride` de Drupal para
  sobrescrituras específicas de idioma.

## Cómo funciona en tiempo de ejecución

### Resolución de sobrescrituras

Cuando Drupal carga un objeto de configuración (por ejemplo,
`system.site`), la config factory solicita a todos los servicios de
sobrescritura registrados que proporcionen sus sobrescrituras. Domain
Config registra dos servicios de sobrescritura, aplicados en orden:

1. **`domain.config_factory_override`** (prioridad -253) — carga la
   sobrescritura solo de dominio desde la colección
   `domain.{domain_id}`.
2. **`domain.language.config_factory_override`** (prioridad -252) —
   carga la sobrescritura de dominio+idioma desde la colección
   `domain.{domain_id}.language.{lang_code}`.

El resultado final fusionado sigue esta cascada:

```
Configuración base (colección por defecto)
  ↓ fusionada con
Sobrescritura de dominio (colección domain.{domain_id})
  ↓ fusionada con
Sobrescritura de dominio+idioma (colección domain.{domain_id}.language.{lang_code})
  = Configuración final en tiempo de ejecución
```

**Ejemplo** con `system.site` en el dominio `two_example_com`, idioma
`es`:

```yaml
# Configuración base (colección por defecto):
system.site:
  name: "My Site"

# Sobrescritura de dominio (colección domain.two_example_com):
system.site:
  name: "Two"        # sobrescribe "My Site" → "Two"

# Sobrescritura de dominio+idioma (colección domain.two_example_com.language.es):
system.site:
  name: "Dos"        # sobrescribe "Two" → "Dos"

# Resultado final en tiempo de ejecución: name = "Dos"
```

### Contexto de dominio

El dominio activo se determina mediante `DomainNegotiationContext`, que
se inyecta en ambos servicios de sobrescritura. El contexto se
establece durante el evento kernel request por `DomainSubscriber` y
también puede cambiarse programáticamente (por ejemplo, por Domain
Config al comparar configuraciones entre dominios).

Cuando no hay un dominio activo (por ejemplo, durante comandos Drush
sin contexto de dominio), no se aplican sobrescrituras y se utiliza la
configuración base.

!!! tip "Negociación temprana para middlewares"
    Si los middlewares de terceros necesitan sobrescrituras de
    domain_config antes de que se dispare el evento kernel request,
    instale el módulo **Domain Early Negotiation**
    (`domain_early_negotiation`) del proyecto
    [Domain Extras](https://www.drupal.org/project/domain_extras).
    Consulte la
    [documentación de Domain](../domain/index.md#negociacion-temprana-de-dominio)
    para más detalles.

### Cache

Cada servicio de sobrescritura proporciona un **sufijo de cache**
basado en el ID del dominio actual (y el código de idioma para la
sobrescritura de idioma). Esto garantiza que los objetos de
configuración almacenados en cache para un dominio no se sirvan
a otro.

Los metadatos de cache incluyen el cache context `domain`, por lo que
el contenido renderizado que depende de configuración específica de
dominio varía correctamente.

## Eventos del ciclo de vida de configuración

Domain Config 3.x gestiona adecuadamente los eventos del ciclo de vida
de la configuración para mantener las sobrescrituras de dominio
sincronizadas con la configuración base:

| Evento | Comportamiento |
|--------|----------------|
| **Config save** | Para cada dominio, si existe una sobrescritura de dominio para la configuración guardada, se filtra para eliminar los valores idénticos a la nueva configuración base (conservando solo las sobrescrituras reales). |
| **Config delete** | Si se elimina la configuración base, la sobrescritura de dominio correspondiente se elimina de todas las colecciones de dominio. |
| **Config rename** | Si se renombra la configuración base, la sobrescritura se renombra en todas las colecciones de dominio para coincidir. |

## Exportación e importación de configuración

Como las sobrescrituras viven en colecciones de Drupal, se integran
con el sistema de exportación/importación de configuración:

**Estructura del directorio de exportación:**

El `FileStorage` de Drupal convierte los puntos en los nombres de
colección en separadores de directorio. La colección
`domain.one_example_com` se convierte en el directorio
`domain/one_example_com/`, y
`domain.one_example_com.language.fr` se convierte en
`domain/one_example_com/language/fr/`:

```
config/sync/
  system.site.yml                              # Configuración base
  domain/
    one_example_com/
      system.site.yml                          # Sobrescritura de dominio
      language/
        fr/
          system.site.yml                      # Sobrescritura dominio+idioma
    two_example_com/
      system.site.yml
```

Los módulos también pueden incluir sobrescrituras de dominio por
defecto usando la misma convención en su directorio
`config/install/`:

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

Cuando se crea una nueva entidad de dominio,
`installDomainOverrides()` llama a
`ConfigInstallerInterface::installCollectionDefaultConfig()` de Drupal
para instalar los valores por defecto proporcionados por los módulos
para la colección de ese dominio.

## Domain Config UI

El módulo opcional **Domain Config UI** proporciona una interfaz de
usuario para gestionar las sobrescrituras por dominio directamente
desde los formularios de configuración existentes.

Consulte la
[documentación de Domain Config UI](../domain_config_ui/index.es.md)
para más detalles sobre:

- Activar/desactivar sobrescrituras por configuración y por dominio.
- El enlace de acción en línea en los formularios de administración.
- Configuraciones no permitidas.
- Control programático mediante alter hooks.

## Migración de 2.x a 3.x

Se proporciona una migración automática mediante
`domain_config_update_10001()`. El servicio de migración
(`DomainConfigMigration`) realiza los siguientes pasos:

1. **Escanea los objetos de configuración legacy** — encuentra todas
   las entradas `domain.config.{domain_id}.*` en la colección por
   defecto.
2. **Analiza el nombre legacy** — extrae el ID de dominio, el código
   de idioma opcional y el nombre de configuración usando el patrón:
   ```
   /^domain\.config\.{domain_id}(?:\.([a-z]{2}))?\.([^.]+\.[^.]+)$/
   ```
3. **Escribe en las colecciones** — copia los datos en la colección
   `domain.{domain_id}` o
   `domain.{domain_id}.language.{lang_code}` correspondiente.
4. **Actualiza el registro** — si Domain Config UI está instalado,
   actualiza la configuración `overridable_configurations` en
   `domain_config_ui.settings`.
5. **Limpieza** — elimina los objetos legacy `domain.config.*` de la
   colección por defecto.

La migración se ejecuta automáticamente durante `drush updatedb`. Si
algún dominio falla en la migración, el update hook lanza una
`UpdateException` con los detalles.

## Servicios

| Servicio | Clase | Función |
|----------|-------|---------|
| `domain.config_factory_override` | `DomainConfigFactoryOverride` | Sobrescrituras de configuración solo de dominio (prioridad -253) |
| `domain.language.config_factory_override` | `DomainLanguageConfigFactoryOverride` | Sobrescrituras de configuración de dominio+idioma (prioridad -252) |
| `domain.language_manager` | `DomainConfigLanguageManager` | Decora `language_manager` para integrar las sobrescrituras de idioma de dominio |
| `domain_config.library.discovery.collector` | `DomainConfigLibraryDiscoveryCollector` | Decora el descubrimiento de librerías para variar por dominio |
| `domain_config.config_migration` | `DomainConfigMigration` | Servicio de migración 2.x → 3.x |

## Issues relacionados

- [Domain Config collections](https://www.drupal.org/project/domain/issues/3221779)
