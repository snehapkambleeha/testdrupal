# Domain

El módulo Domain es el núcleo de la suite del módulo Domain.
Proporciona gestión de entidades de dominio, negociación y reescritura
de URLs entre dominios.

## Reescritura de URLs entre dominios

Cualquier código puede apuntar a un dominio específico al construir una
URL estableciendo la opción `domain` en un objeto `Url`. El
`DomainPathProcessor` (procesador de ruta saliente, prioridad 80)
reescribirá entonces la URL para apuntar a ese dominio.

### Uso

La opción `domain` requiere un objeto de entidad `DomainInterface`,
similar a cómo la opción `language` del core requiere una
`LanguageInterface`:

```php
use Drupal\Core\Url;

$domain = \Drupal::entityTypeManager()->getStorage('domain')->load('one_example_com');
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
$url->setOption('domain', $domain);
```

Esto produce una URL absoluta apuntando al dominio de destino, por
ejemplo `http://one.example.com/node/1`.

### Cómo funciona

Cuando `DomainPathProcessor::processOutbound()` encuentra una opción
`domain`:

1. **Valida el dominio** — verifica que la opción es una entidad
   `DomainInterface`.
2. **Aplica funcionalidades entre dominios** (negociación de idioma,
   parámetro destination — ver más abajo).
3. **Reescribe la URL** estableciendo `base_url` a la ruta del dominio
   de destino y forzando `absolute = TRUE`.
4. **Añade metadatos de cache** — la entidad de dominio como
   dependencia cacheable (para invalidación cuando el dominio cambia),
   y el cache context `domain` cuando la funcionalidad del parámetro
   destination está activa.

### Integración con Domain Source

El módulo Domain Source utiliza este mecanismo internamente. Cuando una
entidad de contenido tiene un dominio fuente diferente al dominio
activo, `DomainSourcePathProcessor` (prioridad 310) establece
`$options['domain'] = $source` y deja que `DomainPathProcessor` se
encargue de la reescritura real de la URL.

Esto significa que todas las funcionalidades entre dominios descritas a
continuación se aplican automáticamente a las reescrituras de Domain
Source así como a cualquier código personalizado que establezca la
opción `domain`.

## Funcionalidades experimentales

Las siguientes funcionalidades están disponibles bajo
**Experimental features** en la página de configuración de Domain
(`/admin/config/domain/settings`). Todas están desactivadas por
defecto.

### Soporte de prefijo de ruta

El módulo Domain soporta un **prefijo de ruta** opcional en los
registros de dominio, permitiendo que múltiples dominios compartan un
único hostname distinguiéndose por el primer segmento de la ruta URL
(por ejemplo, `example.com/fr/...` vs `example.com/benl/...`).

Marque **"Enable path prefix support"** en la configuración de Domain
para activar esta funcionalidad. Cuando está desactivada, todos los
componentes de prefijo de ruta se eliminan del contenedor sin
sobrecarga en tiempo de ejecución.

Consulte la [documentación de prefijo de ruta](path_prefix.md) para
todos los detalles.

- Clave de configuración: `domain.settings:path_prefix`
- Issue relacionado:
  [#3575947](https://www.drupal.org/i/3575947)

### Negociación de idioma para URLs entre dominios

Cuando un sitio utiliza múltiples dominios con diferentes
configuraciones de negociación de idioma (por ejemplo, un dominio usa
prefijos de ruta como `/fr/...` mientras otro usa negociación basada
en dominio), las URLs de salida necesitan ser procesadas usando la
configuración de negociación de idioma de su dominio de *destino*,
no la del actual.

Marque **"Enable language negotiation for cross-domain URLs"** en la
configuración de Domain para activar esta funcionalidad.

Cuando está activada, `DomainPathProcessor`:

1. Compara la configuración URL de `language.negotiation` entre el
   dominio activo y el dominio de destino (usando sobrescrituras de
   Domain Config).
2. Si las configuraciones difieren, re-ejecuta el procesador de salida
   `LanguageNegotiationUrl` en el contexto del dominio de destino.
3. Esto garantiza que los prefijos de ruta y otros métodos de
   negociación basados en URL se apliquen correctamente para el dominio
   de destino.

!!! note
    Esto dispara un pase adicional de negociación de idioma solo para
    URLs cuyo dominio de destino tiene una configuración de negociación
    de idioma diferente. Las URLs que permanecen en el mismo dominio o
    apuntan a un dominio con configuración idéntica no se ven
    afectadas.

- Clave de configuración: `domain.settings:language_negotiation`
- Issue relacionado:
  [#3570178](https://www.drupal.org/i/3570178)

### Redirecciones de destination con alcance de dominio

Cuando un usuario sigue un enlace entre dominios que incluye un
parámetro de consulta `destination` (por ejemplo, para iniciar sesión
o editar contenido en otro dominio), la ruta `destination` relativa
estándar redirigiría al usuario de vuelta al dominio de *destino* en
lugar del dominio *original*.

Marque **"Allow domain-scoped destination redirects"** en la
configuración de Domain para activar esta funcionalidad.

Cuando está activada, `DomainPathProcessor`:

1. Detecta enlaces entre dominios que incluyen un parámetro de consulta
   `destination` que coincide con la ruta de la solicitud actual.
2. Añade un parámetro de consulta `destination_domain` que contiene la
   URL base del dominio actual (esquema + host).
3. En el dominio de destino, el event subscriber `DomainSubscriber`
   reconstruye una URL `destination` absoluta a partir de ambos
   parámetros, asegurando que el usuario sea redirigido de vuelta a la
   página correcta en el dominio original.

**Flujo de ejemplo:**

1. El usuario está en `http://example.com/admin/content`.
2. Hace clic en un enlace de edición reescrito a
   `http://one.example.com/node/1/edit`.
3. Con esta funcionalidad activada, el enlace se convierte en:
   `http://one.example.com/node/1/edit?destination=/admin/content&destination_domain=http://example.com`
4. Después de guardar, el usuario es redirigido a
   `http://example.com/admin/content`.

- Clave de configuración: `domain.settings:allow_destination_domain`
- Issue relacionado:
  [#3570210](https://www.drupal.org/i/3570210)

### Negociación temprana de dominio

Si los middlewares de terceros necesitan sobrescrituras de domain_config
antes del evento kernel request, instale el módulo **Domain Early
Negotiation** (`domain_early_negotiation`) del proyecto
[Domain Extras](https://www.drupal.org/project/domain_extras). Provee
un `DomainNegotiationMiddleware` que negocia el dominio activo
tempranamente en la pila de middlewares. Activar el módulo activa la
funcionalidad; la prioridad del middleware es configurable en
`/admin/config/domain/early-negotiation`.

## Comandos Drush

El módulo Domain proporciona comandos Drush para gestionar registros
de dominio desde la línea de comandos.

### domain:list

Lista todos los registros de dominio con su estado y respuesta HTTP.

```bash
drush domain:list
drush domain:list --inactive
drush domain:list --active
```

Aliases: `domains`, `domain-list`

```
 Machine name          Name      Hostname     Path prefix  Scheme  Status  Default  Response
 example_com           Default   example.com               https   Active  Default  200 - OK
 example_com_fr        French    example.com  fr           https   Active           200 - OK
 shop_example_com      Shop      shop.com                  https   Active           200 - OK
```

### domain:info

Muestra información general sobre los dominios del sitio.

```bash
drush domain:info
```

Aliases: `domain-info`, `dinf`

```
 All Domains              3
 Active Domains           3
 Default Domain ID        example_com
 Default Domain hostname  example.com
 Fields in Domain entity  id, domain_id, hostname, path_prefix, name, ...
 Domain admin entities    node, user
```

### domain:add

Crea un nuevo registro de dominio.

```bash
drush domain:add example.com 'My Site'
drush domain:add example.com 'My Site' --scheme=https
drush domain:add example.com 'My Site' --weight=10
drush domain:add example.com 'My Site' --inactive
drush domain:add example.com 'My Site' --is_default
drush domain:add example.com 'My Site' --validate
drush domain:add example.com 'French Site' --path-prefix=fr
```

```
Created the example.com with machine id example_com.
```

Aliases: `domain-add`

Opciones:

| Opción | Descripción |
|--------|-------------|
| `--scheme` | `http`, `https` o `variable`. Por defecto `http`. |
| `--weight` | Orden de clasificación para el dominio. |
| `--inactive` | Crear el dominio como inactivo. |
| `--is_default` | Establecer como dominio por defecto. |
| `--validate` | Verificar la respuesta URL antes de guardar. |
| `--path-prefix` | Prefijo de ruta para compartir hostname (ver [Prefijo de ruta](path_prefix.md)). |

### domain:delete

Elimina un registro de dominio y opcionalmente reasigna su contenido y
usuarios.

```bash
drush domain:delete example.com
drush domain:delete example.com --content-assign=ignore
drush domain:delete example.com --users-assign=example_net
drush domain:delete all
drush domain:delete example.com --dryrun
```

Aliases: `domain-delete`

El dominio por defecto no puede eliminarse. Use `domain:default` para
establecer un nuevo dominio por defecto primero. Al eliminar, se le
solicita reasignar los usuarios a otro dominio a menos que se
especifique `--users-assign`.

### domain:default

Establece un dominio como el dominio por defecto.

```bash
drush domain:default example.com
drush domain:default example_org --validate
```

```
example_com set to primary domain.
```

Aliases: `domain-default`

### domain:enable / domain:disable

Activa o desactiva un dominio.

```bash
drush domain:enable example.com
drush domain:disable example.com
```

```
example.com has been disabled.
```

Aliases: `domain-enable`, `domain-disable`

### domain:name

Cambia la etiqueta de un dominio.

```bash
drush domain:name example.com 'New Name'
```

```
Renamed example.com to New Name.
```

Aliases: `domain-name`

### domain:scheme

Cambia el esquema URL de un dominio.

```bash
drush domain:scheme example.com https
```

```
Scheme is now to "https." for example_com
```

Aliases: `domain-scheme`

Sin argumento de esquema, solicita una selección.

### domain:test

Prueba los dominios para verificar la respuesta HTTP correcta.

```bash
drush domain:test
drush domain:test example.com
```

```
 Machine name      URL                          Response
 example_com       https://example.com          200 - OK
 example_com_fr    https://example.com          200 - OK
 shop_example_com  https://shop.example.com     200 - OK
```

Aliases: `domain-test`

### domain:replace

Reemplaza una cadena en todos los hostnames de dominio. Realiza una
ejecución en seco por defecto; use `--force` para aplicar los cambios.

```bash
drush domain:replace "old.com" "new.com"
drush domain:replace "old.com" "new.com" --force
```

```
 Name     Current            New
 Default  example.old.com    example.new.com
 Shop     shop.old.com       shop.new.com
```

Aliases: `domain-replace`

### domain:generate

Genera dominios de prueba para desarrollo. Crea subdominios del
hostname primario dado.

```bash
drush domain:generate example.com
drush domain:generate example.com --count=25
drush domain:generate example.com --count=25 --empty
drush domain:generate example.com --scheme=https
```

Aliases: `gend`, `domgen`, `domain-generate`

Opciones:

| Opción | Descripción |
|--------|-------------|
| `--count` | Número de dominios a generar. Por defecto 15. |
| `--empty` | Truncar todos los dominios antes de generar. |
| `--scheme` | `http`, `https` o `variable`. |

### Resolución de identificador de dominio

Todos los comandos que aceptan un argumento `domain_id` lo resuelven
en el siguiente orden:

1. Machine name (por ejemplo, `example_com`)
2. Hostname (por ejemplo, `example.com`)

## Issues relacionados

- [#3574800: Allow Url objects to specify the Domain as an option](https://www.drupal.org/i/3574800)
