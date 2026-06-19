# Prefijo de ruta

El módulo Domain soporta un **prefijo de ruta** opcional en los
registros de dominio, permitiendo que múltiples dominios compartan un
único hostname distinguiéndose por el primer segmento de la ruta URL.

Esto es útil para sitios que no pueden añadir nuevos hostnames (por
ejemplo, firewalls corporativos, hosting compartido, CDN de origen
único) pero necesitan contextos de dominio separados para diferentes
audiencias, regiones o marcas.

## Cómo funciona

Cada registro de dominio tiene una propiedad `path_prefix` opcional
(cadena vacía por defecto). Cuando múltiples dominios comparten el
mismo hostname con diferentes prefijos, el negociador los desambigua
comparando la ruta de la solicitud con el prefijo de cada dominio.

### Ejemplo

Dados tres registros de dominio:

| Nombre del dominio | Hostname | Prefijo de ruta | Propósito |
|--------------------|----------|-----------------|-----------|
| Default | `example.com` | *(vacío)* | Sitio principal |
| Belgium NL | `example.com` | `benl` | Holandés belga |
| France | `example.com` | `fr` | Francés |

Una solicitud a `example.com/benl/fr/about-us` se procesa así:

1. **Negociación de dominio** — el negociador carga todos los dominios
   que coinciden con `example.com`, encuentra tres candidatos y compara
   el prefijo `benl` con la ruta de la solicitud
   `/benl/fr/about-us`.
2. **Procesamiento de ruta entrante** — el
   `DomainPrefixPathProcessor` (prioridad 350) elimina el prefijo de
   dominio, obteniendo `/fr/about-us`.
3. **Negociación de idioma** — la negociación de idioma del core
   elimina el prefijo de idioma `fr`, obteniendo `/about-us`.
4. **Resolución de alias de ruta** — Drupal resuelve `/about-us` a
   la ruta interna (por ejemplo, `/node/1`).

La generación de URLs de salida invierte el proceso:

1. **Alias de ruta** — `/node/1` se convierte en `/about-us`.
2. **Procesador de idioma** — añade el prefijo `fr/`.
3. **Procesador de prefijo de dominio** (prioridad 50) — antepone
   `benl/` al prefijo de la URL, produciendo `benl/fr/about-us`.

### Reglas de coincidencia de prefijo

- **Prefijo más largo primero** — cuando los prefijos se solapan (por
  ejemplo, `fr` y `fr-be`), el prefijo coincidente más largo gana. Una
  solicitud a `/fr-be/page` coincide con `fr-be`, no con `fr`.
- **Prefijo vacío como respaldo** — el dominio sin prefijo actúa como
  respaldo cuando ningún prefijo coincide con la ruta de la solicitud.
- **Coincidencia exacta de segmento** — el prefijo debe coincidir con
  un segmento de ruta completo. `/france/page` **no** coincide con el
  prefijo `fr`.

## Configuración

### Activar el soporte de prefijo de ruta

El soporte de prefijo de ruta está desactivado por defecto. Para
activarlo, vaya a la página de configuración de Domain
(`/admin/config/domain/settings`), expanda **Experimental features** y
marque **Enable path prefix support**.

Cuando está desactivado, todos los componentes de prefijo de ruta se
eliminan del contenedor (sin sobrecarga en tiempo de ejecución), el
campo de prefijo de ruta se oculta en el formulario de dominio y la
columna de prefijo se oculta en la lista de dominios.

### Añadir un prefijo de ruta a un dominio

Una vez activado el soporte de prefijo de ruta, el formulario de
creación o edición de dominio
(`/admin/config/domain/add` o `/admin/config/domain/edit/{domain}`)
muestra un campo **Path prefix**. El valor debe ser una cadena simple
sin barras al inicio ni al final (por ejemplo, `fr`, `benl`,
`asia-pacific`).

### Constraint de unicidad

La combinación de hostname y prefijo de ruta debe ser única. Dos
dominios pueden compartir el mismo hostname solo si sus prefijos de
ruta difieren. Intentar guardar dos dominios con el mismo hostname y
el mismo prefijo (incluyendo ambos vacíos) disparará un error de
validación.

### Compatibilidad hacia atrás

Los registros de dominio existentes tienen un prefijo de ruta vacío
por defecto. La funcionalidad está desactivada por defecto y debe
activarse en `/admin/config/domain/settings`.

## Interacción con otros módulos

### Negociación de idioma (prefijos URL)

El prefijo de dominio es el segmento de ruta **más externo**, colocado
antes de cualquier prefijo de idioma. El orden de procesamiento es:

| Dirección | Prioridad | Procesador | Acción |
|-----------|-----------|------------|--------|
| Entrante | 350 | `DomainPrefixPathProcessor` | Elimina el prefijo de dominio |
| Entrante | 300 | `LanguageNegotiationUrl` | Elimina el prefijo de idioma |
| Saliente | 100 | `LanguageNegotiationUrl` | Añade el prefijo de idioma |
| Saliente | 50 | `DomainPrefixPathProcessor` | Antepone el prefijo de dominio |

Una URL como `/benl/fr/about-us` se descompone así:

```
/benl/fr/about-us
 ^^^^           → prefijo de dominio (eliminado primero entrante, añadido último saliente)
      ^^        → prefijo de idioma
         ^^^^^^^^ → alias de ruta
```

### Domain Access

Domain Access asigna la visibilidad del contenido por dominio. Los
dominios con prefijo de ruta son entidades de dominio completas, por lo
que los valores de campo de Domain Access y los grants de nodo
funcionan de manera idéntica — cada dominio con prefijo puede tener sus
propias asignaciones de contenido.

### Domain Config / Domain Config UI

Domain Config proporciona sobrescrituras de configuración por dominio.
Cada dominio con prefijo es una entidad de configuración distinta, por
lo que recibe sus propias sobrescrituras de configuración como es de
esperar.

### Domain Alias

Domain Alias proporciona hostnames alternativos para un dominio. Los
alias coinciden por hostname, no por prefijo de ruta.

**Importante:** cuando múltiples dominios comparten el mismo hostname
con diferentes prefijos de ruta, solo necesita crear alias en el
**dominio sin prefijo (por defecto)** para ese hostname. El alias
resuelve el hostname; la negociación de prefijo selecciona después el
dominio correcto basándose en la ruta URL. Crear el mismo patrón de
alias en un dominio con prefijo fallará porque los patrones de alias
son globalmente únicos.

Por ejemplo, si `example.com` (sin prefijo) y `example.com` (prefijo
`fr`) comparten un hostname, añada `*.example.com` como alias en el
dominio sin prefijo únicamente. Las solicitudes a
`something.example.com/fr/page` resolverán el alias a `example.com`,
y luego la negociación de prefijo seleccionará el dominio `fr`.

### Domain Source

Domain Source asigna un dominio canónico al contenido para la
generación de URLs. Cuando el dominio fuente de una entidad de
contenido tiene un prefijo de ruta, la URL generada incluye
automáticamente el prefijo.

### Domain Path

Domain Path opera sobre rutas internas (después de la eliminación del
prefijo entrante), por lo que funciona sin modificación.

### Domain Content

Domain Content proporciona páginas de resumen de contenido por dominio.
Cada dominio con prefijo aparece como una entrada separada en el filtro
de dominio.

## Detalles técnicos

### Uso programático

```php
// Obtener el prefijo de ruta de una entidad de dominio.
$prefix = $domain->getPathPrefix();

// Establecer el prefijo de ruta.
$domain->setPathPrefix('benl');
$domain->save();

// Cargar todos los dominios que comparten un hostname.
$storage = \Drupal::entityTypeManager()->getStorage('domain');
$domains = $storage->loadMultipleByHostname('example.com');

// getBasePath() devuelve esquema + hostname + base_path (sin prefijo).
// Utilícelo para construir URLs base para procesadores de ruta saliente.
$base = $domain->getBasePath();
// ej. "http://example.com/"

// getPath() devuelve la ruta completa incluyendo el prefijo.
// Utilícelo para visualización y enlaces directos.
$path = $domain->getPath();
// ej. "http://example.com/fr/"
```

### Generación de URLs de salida

El `DomainPrefixPathProcessor` antepone el prefijo a la opción
`prefix` utilizada por el generador de URLs de Drupal. Para las URLs
generadas con la opción `domain` (ver
[Reescritura de URLs entre dominios](index.md#reescritura-de-urls-entre-dominios)),
se utiliza el prefijo del dominio de destino. Para todas las demás
URLs, se utiliza el prefijo del dominio activo.

```php
use Drupal\Core\Url;

// Una URL apuntando a un dominio con prefijo incluye el prefijo
// automáticamente.
$domain = $storage->load('example_com_fr');
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
$url->setOption('domain', $domain);
// Genera: http://example.com/fr/node/1
```

### Instalaciones en subdirectorio

El soporte de prefijo de ruta funciona correctamente cuando Drupal se
ejecuta en un subdirectorio (por ejemplo, `example.com/drupal/`). Los
métodos `setUrl()` y `setPath()` utilizan `Request::getPathInfo()` y
`Request::getBasePath()` de Symfony para separar la ruta base del
subdirectorio de la ruta de la ruta antes de la manipulación del
prefijo. La URL resultante preserva el orden correcto:
`esquema + hostname + base_path + prefijo + ruta_de_ruta`.

Por ejemplo, con ruta base `/drupal/` y prefijo `fr`, una solicitud a
`/drupal/fr/admin/config` produce la URL
`http://example.com/drupal/fr/admin/config`.

### Rendimiento

La funcionalidad no tiene un impacto medible en el rendimiento:

- **Cuando está desactivada** — el procesador de ruta de prefijo de
  ruta, la sobrescritura de negociación de idioma y la lógica de
  negociación de prefijo se eliminan completamente del contenedor. Sin
  sobrecarga en tiempo de ejecución.
- **Cuando está activada pero ningún dominio usa un prefijo** — todas
  las rutas de código retornan tempranamente con verificaciones de
  cadena vacía.
- **Cuando los prefijos están activos** — el negociador ordena un
  pequeño array en memoria (generalmente 2-5 entradas) y hace una
  comparación de cadena por candidato. No se emiten consultas de
  almacenamiento adicionales.
- **Sin nuevos cache contexts** — el procesador de salida añade el
  cache context `domain`, que ya está presente en cada página de un
  sitio con dominio. No se introduce fragmentación de cache adicional.

### Prefijos no ASCII

La configuración **Allow non-ASCII characters** en la página de
configuración de Domain (`/admin/config/domain/settings`) también se
aplica a los prefijos de ruta. Cuando está activada, se aceptan letras
Unicode en minúsculas y números en los prefijos (por ejemplo,
`belgië`, `日本`). Cuando está desactivada (por defecto), solo se
permiten letras ASCII en minúsculas `a-z`, dígitos `0-9`, guiones y
guiones bajos.

### Config schema

El campo `path_prefix` se declara en `domain.schema.yml` como una
propiedad `string` en `domain.record.*` con una constraint `Regex`
usando clases de caracteres Unicode (`\p{L}`, `\p{N}`) como base
permisiva:

```yaml
domain.record.*:
  type: config_entity
  mapping:
    # ... campos existentes ...
    path_prefix:
      type: string
      label: 'Path prefix'
      constraints:
        Regex:
          pattern: '/^([\p{L}\p{N}][\p{L}\p{N}_\-]*)?$/u'
          message: 'The path prefix may only contain ...'
```

La verificación más estricta de solo ASCII se aplica en la validación
del formulario y en `preSave()` de la entidad cuando la configuración
**Allow non-ASCII characters** está desactivada. La regex del schema
sirve como base que captura valores completamente inválidos durante las
importaciones de configuración.

Esto hace que el schema `domain.record.*` sea completamente validable
mediante `TypedConfigManager::createFromNameAndData()->validate()`,
capturando valores inválidos durante las importaciones de configuración
y la validación de formularios sin requerir un guardado.

## Issues relacionados

- [#3575947: Support path-prefix-based domain separation on a single hostname](https://www.drupal.org/i/3575947)
