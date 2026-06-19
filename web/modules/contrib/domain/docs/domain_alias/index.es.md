# Domain Alias

El módulo Domain Alias permite que múltiples hostnames apunten a un
único registro de dominio. Un alias puede coincidir con un hostname
exacto o usar patrones con comodines, y puede opcionalmente redirigir
al dominio padre.

## Principio clave: los registros de dominio contienen hostnames canónicos

Los registros de dominio deben usar sus **hostnames canónicos de
producción** — los que desea en las URLs generadas, sitemaps y
metadatos SEO. Los alias sirven entonces para dos propósitos:

1. **Mapeo de entorno** — hostnames de desarrollo local, staging y CI
   que se resuelven a los registros de dominio de producción.
2. **Redirecciones de producción** — hostnames de producción
   alternativos (por ejemplo, `www.example.com`) que redirigen al
   hostname canónico mediante 301/302.

### Ejemplo

Si su sitio de producción funciona en `example.com` y
`shop.example.com`, cree dos registros de dominio con esos hostnames.
Luego añada alias:

| Patrón de alias | Dominio padre | Ent. | Redirección |
|-----------------|---------------|------|-------------|
| `www.example.com` | `example.com` | default | 301 |
| `www.shop.example.com` | `shop.example.com` | default | 301 |
| `example.local` | `example.com` | local | -- |
| `shop.example.local` | `shop.example.com` | local | -- |
| `example.staging.acme.com` | `example.com` | staging | -- |
| `shop.staging.acme.com` | `shop.example.com` | staging | -- |

Este enfoque garantiza que:

- **Las URLs de producción son siempre canónicas** — los enlaces
  generados, sitemaps y metadatos SEO usan los hostnames reales de
  producción.
- **Los hostnames alternativos redirigen limpiamente** — los visitantes
  que acceden a `www.example.com` son redirigidos a `example.com` con
  el código de estado HTTP apropiado.
- **La reescritura de entorno funciona correctamente** — al visitar un
  alias en un entorno no predeterminado, todos los hostnames de dominio
  se reescriben a sus alias correspondientes del entorno (ver
  [Entornos](#entornos)).
- **Las sobrescrituras de configuración son predecibles** — las
  sobrescrituras de Domain Config se indexan por el ID del registro
  de dominio, que se deriva del hostname canónico.

**No** cree registros de dominio con hostnames de desarrollo o staging
y luego haga alias del hostname de producción hacia ellos. Esto
invierte la relación prevista y rompe la reescritura de entorno, la
generación de URLs y las sobrescrituras de configuración.

## Propiedades del alias

Cada alias es una entidad de configuración con los siguientes campos:

| Campo | Descripción |
|-------|-------------|
| **Pattern** | El patrón de hostname a coincidir (máximo 80 caracteres). |
| **Redirect** | `0` = sin redirección, `301` = permanente, `302` = temporal. |
| **Environment** | El entorno de servidor al que pertenece este alias. |
| **Weight** | Orden de clasificación para la coincidencia (menor = mayor prioridad). |

Los alias se gestionan por dominio en
`/admin/config/domain/alias/{domain}`.

## Coincidencia de patrones

Cuando llega una solicitud que no coincide exactamente con ningún
registro de dominio, Domain Alias busca un patrón de alias coincidente.

### Orden de coincidencia

1. **Registro de dominio exacto** — gestionado por el módulo Domain
   base.
2. **Alias exacto** — un alias sin comodines que coincide con el
   hostname.
3. **Alias con comodines** — ordenados por especificidad (menos
   comodines primero, patrones más largos primero).

### Sintaxis de comodines

El carácter `*` coincide con uno o más caracteres dentro de un
segmento de hostname. Se permite un máximo de un comodín por alias.

```
*.example.com        coincide con one.example.com, two.example.com
example.*.com        coincide con example.dev.com
example.*            coincide con example.com, example.local
*.com                coincide con anything.com
```

### Coincidencia de puertos

Los puertos pueden incluirse en los patrones de alias. Las reglas son:

- **Puertos por defecto (80, 443)**: una solicitud en estos puertos
  coincide con alias con o sin especificador de puerto. Por ejemplo,
  `example.com:80` coincide tanto con `example.com` como con
  `example.com:80`.
- **Puertos no predeterminados**: una solicitud en el puerto 8080 solo
  coincide con alias que incluyan explícitamente un puerto.
  `example.com:8080` coincide con `example.com:8080` y
  `example.com:*`, pero **no** con `example.com`.

```
example.com:8080     coincide solo con example.com:8080
example.com:*        coincide con example.com en cualquier puerto
*.com:*              coincide con anything.com en cualquier puerto
```

## Redirección

Cuando un alias tiene un valor de redirección de `301` o `302`, el
usuario es redirigido al dominio padre con el código de estado HTTP
correspondiente. Esto es útil para consolidar el tráfico de hostnames
alternativos a un dominio canónico.

Para alias de entornos no predeterminados con redirección, el destino
de la redirección se resuelve al primer alias sin redirección en el
mismo entorno en lugar del hostname canónico. Esto evita redirigir el
tráfico de desarrollo a URLs de producción.

## Interacción con prefijo de ruta

Cuando el [soporte de prefijo de ruta](../domain/path_prefix.md) está
activado, múltiples dominios pueden compartir el mismo hostname (por
ejemplo, `example.com` con prefijos `fr`, `benl`, etc.). Los alias
resuelven **solo hostnames** — la negociación de prefijo de ruta ocurre
automáticamente después.

### Cómo funciona

1. El alias resuelve un hostname a su registro de dominio padre.
2. Si el soporte de prefijo de ruta está activado, se cargan todos los
   dominios que comparten el hostname resuelto.
3. `negotiateByPathPrefix()` selecciona el dominio correcto basándose
   en la ruta de la solicitud actual.

### Dónde añadir alias

Si múltiples dominios comparten un hostname con diferentes prefijos de
ruta, solo necesita añadir alias a **uno** de ellos — generalmente el
dominio sin prefijo. El formulario de alias muestra una advertencia
cuando el dominio padre usa un prefijo de ruta, sugiriendo añadir los
alias al dominio sin prefijo.

### Reescritura de entorno

Durante la reescritura de entorno, los dominios que comparten el mismo
hostname canónico que el dominio activo (es decir, que solo difieren
por prefijo de ruta) se reescriben directamente usando el hostname de
la solicitud actual — no se necesita una búsqueda de alias adicional.

## Entornos

Los alias pueden etiquetarse con un **entorno** para soportar flujos
de trabajo de desarrollo multi-entorno. Cuando la solicitud activa
coincide con un alias en un entorno no predeterminado, todos los
hostnames de dominio se reescriben a sus alias correspondientes
específicos del entorno. Esto garantiza que los enlaces generados
permanezcan dentro del entorno actual.

### Entornos predeterminados

- `default` — URLs canónicas, no ocurre reescritura.
- `local` — desarrollo local.
- `development` — servidor de integración.
- `staging` — servidor de pre-despliegue.
- `testing` — entornos de CI.

La lista puede sobrescribirse en `settings.php` (ver
[Configuración](#configuracion)). El proyecto
[Domain Extras](https://www.drupal.org/project/domain_extras) incluye
un submódulo **Domain Alias Extras** que proporciona una interfaz de
usuario para personalizar esta lista.

!!! warning "Los entornos de preview y CI deben usar un entorno no predeterminado"

    Si utiliza alias con comodines para entornos de preview o CI (por
    ejemplo, `*.tugboatqa.com`, `*.ci-host.com`), asígnelos a un
    entorno no predeterminado como `local` o `testing`. Con el entorno
    `default`, la reescritura de hostname se omite, por lo que los
    enlaces generados (incluyendo en la lista de administración de
    dominios) apuntarán al hostname canónico de producción en lugar de
    la URL real del preview.

### Ejemplo de configuración

Considere un sitio con tres dominios de producción:

| Dominio | Hostname |
|---------|----------|
| Primary | `example.com` |
| Foo | `foo.example.com` |
| Bar | `bar.example.com` |

Para desarrollo local, cree alias etiquetados como `local`:

| Alias | Dominio padre | Entorno |
|-------|---------------|---------|
| `example.local` | `example.com` | local |
| `foo.example.local` | `foo.example.com` | local |
| `bar.example.local` | `bar.example.com` | local |

Cuando un desarrollador visita `foo.example.local`:

1. Ningún registro de dominio exacto coincide.
2. El alias `foo.example.local` coincide, apuntando a
   `foo.example.com`.
3. Como el alias está en el entorno `local`, todos los dominios tienen
   sus hostnames reescritos: `example.com` se convierte en
   `example.local`, `bar.example.com` se convierte en
   `bar.example.local`.
4. Todos los enlaces generados en la página usan dominios `.local`.

### Entornos con comodines

Los alias con comodines también funcionan con entornos. Al colocar el
comodín en la posición del TLD, un único conjunto de alias cubre
múltiples entornos (`.local`, `.dev`, `.test`, etc.) sin duplicación:

| Alias | Dominio padre | Entorno |
|-------|---------------|---------|
| `example.*` | `example.com` | local |
| `foo.example.*` | `foo.example.com` | local |
| `bar.example.*` | `bar.example.com` | local |

Cuando un desarrollador visita `foo.example.local`:

1. El alias `foo.example.*` coincide, capturando `local`.
2. Para otros dominios, se cargan sus alias `local` y los comodines se
   reemplazan con el valor capturado: `example.*` se convierte en
   `example.local`, `bar.example.*` se convierte en
   `bar.example.local`.

Los mismos alias también funcionan para `foo.example.dev`,
`foo.example.test`, etc. — todos resueltos a través del entorno
`local`.

## Reglas de validación

Los registros de alias se validan mediante plugins de constraint de
Symfony declarados en el esquema de configuración
(`domain_alias.schema.yml`). Estas constraints se ejecutan
automáticamente al guardar a través del formulario de administración o
los comandos Drush.

**Pattern** (constraints `DomainAliasPattern` +
`DomainAliasUniquePattern`):

1. Se requiere al menos un punto (excepto `localhost`).
2. Solo un comodín (`*` o `?`) por patrón.
3. Solo dos puntos (`:`) para especificar el puerto.
4. Después de los dos puntos, solo se permite un entero o `*`.
5. Sin puntos al inicio ni al final.
6. Solo caracteres ASCII (a menos que
   `domain.settings:allow_non_ascii` esté activado).
7. No puede coincidir con un hostname de dominio existente.
8. Debe ser único entre todos los alias.

**Redirect** (constraint `Choice`):

Debe ser uno de `0` (sin redirección), `301` o `302`.

**Environment** (constraint `DomainAliasEnvironment`):

Debe ser uno de los valores definidos en
`domain_alias.settings:environments`.

## Eliminación en cascada

Cuando se elimina un registro de dominio, todos sus alias se eliminan
automáticamente.

## Permisos

| Permiso | Descripción |
|---------|-------------|
| `administer domain aliases` | Control total sobre todos los alias. |
| `create domain aliases` | Crear alias (limitado a los dominios asignados). |
| `edit domain aliases` | Editar alias (limitado a los dominios asignados). |
| `delete domain aliases` | Eliminar alias (limitado a los dominios asignados). |
| `view domain aliases` | Ver alias (limitado a los dominios asignados). |

## Comandos Drush

### domain-alias:list

Lista los alias con filtros opcionales.

```bash
drush domain-alias:list
drush domain-alias:list --hostname=example.com
drush domain-alias:list --environment=local
drush domain-alias:list --redirect=301
```

```
 Machine name              Alias                  Domain       Environment  Redirect
 example_local             example.local          example_com  local        0: Do not redirect
 shop_example_local        shop.example.local     shop_com     local        0: Do not redirect
 www_example_com           www.example.com        example_com  default      301: Moved Permanently
```

Aliases: `domain-aliases`, `domain-alias-list`

### domain-alias:add

Crea un nuevo alias para un dominio.

```bash
drush domain-alias:add example.com test.example.com
drush domain-alias:add example.com test.example.com --environment=local
drush domain-alias:add example.com test.example.com --redirect=301
drush domain-alias:add example.com '*.example.local' --environment=local
```

```
Created the alias test.example.com with machine id test_example_com.
```

Aliases: `domain-alias-add`

Opciones:

| Opción | Descripción |
|--------|-------------|
| `--machine_name` | Sobrescribir el machine name autogenerado. |
| `--redirect` | `0` (sin redirección), `301` o `302`. Por defecto `0`. |
| `--environment` | Etiqueta de entorno. Por defecto `default`. |

### domain-alias:update

Actualiza un alias existente.

```bash
drush domain-alias:update test.example.com --environment=local
drush domain-alias:update test.example.com --pattern=test2.example.com
drush domain-alias:update test.example.com --redirect=301
```

```
Domain Alias updated successfully.
```

Aliases: `domain-alias-update`

Opciones:

| Opción | Descripción |
|--------|-------------|
| `--pattern` | Cambiar el patrón del alias. |
| `--redirect` | `0`, `301` o `302`. |
| `--environment` | Cambiar la etiqueta de entorno. |

### domain-alias:delete

Elimina un alias individual por patrón.

```bash
drush domain-alias:delete test.example.com
```

```
Domain Alias test.example.com with id test_example_com deleted.
```

Aliases: `domain-alias-delete`

### domain-alias:delete-bulk

Elimina múltiples alias de un dominio, con filtros opcionales.

```bash
drush domain-alias:delete-bulk example.com
drush domain-alias:delete-bulk example.com --environment=local
drush domain-alias:delete-bulk example.com --redirect=301
```

```
Aliases Deleted Successfully: (example_local) example.local, (star_example_local) *.example.local
```

Aliases: `domain-alias-delete-bulk`

## Rendimiento

Domain Alias se ejecuta durante cada solicitud como parte de la
negociación de dominio. A continuación se presenta un desglose
detallado de lo que sucede y cuándo.

### Cuando el hostname coincide con un registro de dominio (caso más común)

En un sitio de producción, las solicitudes entrantes generalmente
coinciden directamente con un registro de dominio. En ese caso Domain
Alias no hace casi nada:

1. El módulo Domain base encuentra una coincidencia exacta de hostname
   y establece el tipo de coincidencia a `DOMAIN_MATCHED_EXACT`.
2. Se dispara `hook_domain_request_alter()`. Domain Alias verifica el
   tipo de coincidencia, ve `DOMAIN_MATCHED_EXACT` y **retorna
   inmediatamente** — no se realiza ninguna búsqueda de alias.

**Costo:** una comparación contra la constante del tipo de coincidencia.
Despreciable.

### Cuando el hostname no coincide con ningún registro de dominio

Cuando el hostname de la solicitud no coincide con ningún registro de
dominio (por ejemplo, un hostname de desarrollo o staging), Domain
Alias realiza lo siguiente:

1. **Generación de patrones** — el hostname se divide en segmentos y
   se generan todas las combinaciones de comodines posibles (por
   ejemplo, `dev.example.com` produce `*.example.com`,
   `dev.*.com`, `dev.example.*`, etc.). Se añaden variantes de
   puerto si corresponde. Esto es pura manipulación de cadenas sobre
   un array pequeño (generalmente 3-4 segmentos).

2. **Búsqueda de patrones** — cada patrón generado se verifica contra
   el almacenamiento de entidades de configuración de alias mediante
   `loadByProperties()`. Las entidades de configuración se cargan
   desde la cache de configuración de Drupal (en memoria después de la
   primera lectura en una solicitud), **no** desde la base de datos.
   La búsqueda se detiene en la primera coincidencia, por lo que en
   el mejor caso solo se necesitan una o dos consultas contra la cache
   en memoria.

3. **Carga de dominio** — el dominio padre del alias coincidente se
   carga por ID. El almacenamiento de entidades de configuración usa
   una cache estática, por lo que si el dominio ya fue cargado
   anteriormente en la solicitud, esto es un no-op.

4. **Desambiguación por prefijo de ruta** — si el hostname resuelto es
   compartido por múltiples dominios con diferentes prefijos de ruta,
   el negociador ordena los candidatos (generalmente 2-5 entradas) por
   longitud de prefijo y realiza una verificación `str_starts_with()`
   por candidato. Sin consultas de almacenamiento adicionales.

5. **Reescritura de entorno** (solo entornos no predeterminados) —
   cuando el alias coincidente pertenece a un entorno no
   predeterminado (por ejemplo, `local`), todas las entidades de
   dominio tienen sus hostnames reescritos al cargarse mediante
   `hook_domain_load()`. Los dominios que comparten el mismo hostname
   canónico que el dominio activo (es decir, que solo difieren por
   prefijo de ruta) se reescriben directamente sin ninguna búsqueda
   de alias. Otros dominios requieren cargar alias por dominio y por
   entorno y resolver los patrones con comodines. Los resultados se
   almacenan en cache en memoria durante la duración de la solicitud,
   por lo que las cargas repetidas del mismo dominio no disparan
   búsquedas adicionales.


### Características de rendimiento

- **Sin consultas a la base de datos** — todas las búsquedas de alias
  y dominio se realizan a través del almacenamiento de entidades de
  configuración, que lee desde la cache de configuración de Drupal
  (cargada una vez por solicitud desde la base de datos o APCu/Redis
  si hay un backend de cache configurado).
- **Sin llamadas HTTP externas** — la resolución de alias es
  completamente local.
- **Sin cache contexts adicionales** — Domain Alias no añade cache
  contexts más allá de lo que el módulo Domain base ya proporciona
  (`domain`).

### Consideraciones de escalabilidad

El número de entidades de alias afecta el tamaño de la cache de
configuración pero no el costo por solicitud, porque
`loadByProperties()` filtra en memoria. Los sitios con cientos de
alias no deberían ver una diferencia medible respecto a los sitios con
unos pocos.

El factor principal es el número de **dominios** (no alias). La
reescritura de entorno en `hook_domain_load()` se ejecuta una vez por
entidad de dominio cargada por solicitud. Para la mayoría de los sitios
(menos de 20 dominios) esto es despreciable. Los sitios con un número
muy grande de dominios deberían monitorear el impacto de la reescritura
de entorno y considerar si todos los dominios necesitan alias de
entorno.

## Configuración

- Añada alias en `/admin/config/domain/alias/{domain}`.
- Todos los hostnames de alias deben listarse en
  `trusted_host_patterns` en `settings.php`.
- Sobrescriba la lista de entornos en `settings.php` si es necesario:

```php
$config['domain_alias.settings']['environments'] = [
  'default',
  'local',
  'development',
  'staging',
  'testing',
  'production',
];
```
