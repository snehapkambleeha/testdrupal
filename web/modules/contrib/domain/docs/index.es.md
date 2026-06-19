# Presentación

La suite del módulo Domain permite compartir usuarios, contenido y
configuración entre un grupo de dominios desde una única instalación
y base de datos.

Para una descripción completa del módulo, visite la
[página del proyecto](https://www.drupal.org/project/domain).

Envíe reportes de errores y sugerencias de funcionalidades, o haga
seguimiento de los cambios en la
[cola de issues](https://www.drupal.org/project/issues/domain).

## Requisitos

Este módulo no requiere módulos fuera del core de Drupal.

La versión 3.x requiere Drupal 10.2 o superior y es compatible con
Drupal 11.

## Instalación

Instale como normalmente instalaría un módulo contribuido de Drupal.
Para más información, consulte
[Instalar módulos de Drupal](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Módulos incluidos

- **Domain**
  El módulo principal. Domain proporciona los medios para registrar
  múltiples dominios dentro de una única instalación de Drupal. Permite
  asignar usuarios como administradores de dominio y proporciona un
  contexto de visualización para Block y Views. Los dominios también
  pueden compartir un hostname con diferentes
  [prefijos de ruta](domain/path_prefix.md) para una separación basada
  en rutas.

- **Domain Access**
  Proporciona controles de acceso a nodos basados en dominios. Permite
  asignar usuarios como editores de contenido por dominio, establece
  reglas de visibilidad de contenido y proporciona integración con
  Views para el contenido. Consulte la
  [documentación de Domain Access](domain_access/index.md) para más
  información.

- **Domain Alias**
  Permite apuntar múltiples hostnames a un único dominio registrado.
  Estos alias pueden incluir comodines (como *.example.com) y pueden
  configurarse para redirigir a su dominio canónico. Domain Alias
  también permite a los desarrolladores registrar alias por
  `environment`, para que diferentes hosts se utilicen de forma
  consistente entre los entornos de desarrollo. Consulte la
  [documentación de Domain Alias](domain_alias/index.md) para más
  información.

- **Domain Config**
  Proporciona un medio para cambiar la configuración en base a cada
  dominio. Consulte la
  [documentación de Domain Config](domain_config/index.md) para más
  información.

- **Domain Content**
  Proporciona páginas de resumen de contenido por dominio, para que los
  editores puedan revisar el contenido asignado a dominios específicos.
  Consulte la
  [documentación de Domain Content](domain_content/index.md) para más
  información.

- **Domain Source**
  Permite asignar un dominio canónico al contenido al generar URLs.
  Domain Source garantiza que el contenido que aparece en múltiples
  dominios siempre enlace a una única URL. Consulte la
  [documentación de Domain Source](domain_source/index.md) para más
  información.

## Notas de implementación

### Inicio de sesión entre dominios

Para utilizar el inicio de sesión entre dominios, debe configurar el
valor **cookie_domain** en **sites/default/services.yml**.

Para ello, clone `default.services.yml` a `services.yml` y cambie el
valor de `cookie_domain` para que coincida con el hostname raíz de sus
sitios. Tenga en cuenta que el inicio de sesión entre dominios requiere
compartir un dominio de nivel superior, por lo que una configuración
como `.example.com` funcionará para todos los subdominios de
`example.com`.

Ejemplo:

```
cookie_domain: '.example.com'
```

Consulte
[drupal.org/node/2391871](https://www.drupal.org/node/2391871).

### Solicitudes HTTP entre sitios (CORS)

Drupal permite que un sitio particular habilite CORS para las
respuestas servidas por Drupal.

En el caso de Domain, permitir CORS puede eliminar errores AJAX
causados al usar algunos formularios, particularmente las referencias
de entidad, cuando la solicitud AJAX va a otro dominio.

Esta funcionalidad no está habilitada por defecto porque tiene
consecuencias de seguridad. Consulte
[drupal.org/node/2715637](https://www.drupal.org/node/2715637) para
más información e instrucciones.

Para habilitar CORS para todos los dominios, copie
`default.services.yml` a `services.yml` y habilite las siguientes
líneas:

``` yaml
   # Configure Cross-Site HTTP requests (CORS).
   # Read https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
   # for more information about the topic in general.
  cors.config:
    enabled: true
    # Specify allowed headers, like 'x-allowed-header'.
    allowedHeaders: []
    # Specify allowed request methods, specify ['*'] to allow all possible ones.
    allowedMethods: []
    # Configure requests allowed from specific origins.
    allowedOrigins: ['*']
    # Sets the Access-Control-Expose-Headers header.
    exposedHeaders: false
    # Sets the Access-Control-Max-Age header.
    maxAge: false
    # Sets the Access-Control-Allow-Credentials header.
    supportsCredentials: false
```

### Configuración de hosts confiables

Si utiliza la configuración de seguridad de hosts confiables, asegúrese
de añadir cada dominio y alias a la lista de patrones. Por ejemplo:

``` php
$settings['trusted_host_patterns'] = [
  '^.+\.example\.org$',
  '^myexample\.com$',
  '^myexample\.dev$',
  '^localhost$',
];
```

**Recomendamos encarecidamente** el uso de la configuración de hosts
confiables. Cuando Domain emite una redirección, verifica el hostname
del dominio contra esta configuración. Cualquier redirección que no
coincida con los hosts confiables será denegada y lanzará una
excepción.

Consulte
[drupal.org/node/1992030](https://www.drupal.org/node/1992030) para
más información.

### Configuración de registros de dominio

Para crear un registro de dominio, debe proporcionar la siguiente
información:

- Un **hostname** único, que puede incluir un puerto. (Por lo tanto,
  example.com y example.com:8080 se consideran diferentes.) El hostname
  solo puede contener caracteres alfanuméricos, guiones, puntos y dos
  puntos. Si desea utilizar nombres de dominio internacionales, active
  la opción `Allow non-ASCII characters in domains and aliases.`.
- Un **machine_name** que debe ser único. Este valor se autogenera y no
  puede editarse una vez creado.
- Un **name** para usar en las listas de dominios.
- Un esquema de URL, utilizado para escribir enlaces al dominio. El
  esquema puede ser `http`, `https` o `variable`. Si se utiliza
  `variable`, el esquema se heredará de la configuración del servidor
  o la solicitud. Esta opción es útil si sus entornos de prueba no
  tienen certificados seguros pero su entorno de producción sí.
- Un **status** que indica `active` o `inactive`. Los dominios
  inactivos solo pueden ser vistos por usuarios con permiso para
  `view inactive domains`; todos los demás usuarios serán redirigidos
  al dominio por defecto (ver más abajo).
- El **weight** que se utilizará al ordenar los dominios. Estos valores
  se auto incrementan a medida que se crean nuevos dominios.
- Si el dominio es el **default** o no. Solo un dominio puede
  establecerse como `default`. El dominio por defecto se utiliza para
  redirecciones en casos donde otros dominios están restringidos
  (inactivos) o no se pueden cargar. Este valor puede reasignarse
  después de crear los dominios.
- Un **path prefix** opcional que permite a múltiples dominios
  compartir el mismo hostname distinguiéndose por el primer segmento
  de la ruta URL. Consulte la
  [documentación de prefijo de ruta](domain/path_prefix.md).

Los registros de dominio son **entidades de configuración**, lo que
significa que no se almacenan en la base de datos ni son accesibles
para Views por defecto. Sin embargo, son exportables como parte de su
configuración.

### Reglas de validación

Los registros de dominio se validan mediante plugins de constraint de
Symfony declarados en el esquema de configuración
(`domain.schema.yml`). Estas constraints se ejecutan automáticamente
al guardar a través del formulario de administración o los comandos
Drush.

**Hostname** (constraints `DomainHostname` + `DomainUniqueHostname`):

1. Se requiere al menos un punto (excepto `localhost`).
2. Solo se permite dos puntos (`:`) para especificar el puerto.
3. Después de los dos puntos, solo se permite un entero.
4. Sin puntos al inicio ni al final.
5. Solo caracteres ASCII (a menos que
   `domain.settings:allow_non_ascii` esté activado).
6. Solo minúsculas.
7. Sin prefijo `www.` cuando la configuración `Ignore www prefix` está
   activada.
8. La combinación de hostname y prefijo de ruta debe ser única. Dos
   dominios pueden compartir el mismo hostname solo si sus prefijos de
   ruta difieren.

**Scheme** (constraint `Choice`):

Debe ser uno de `http`, `https` o `variable`.

**Domain ID** (constraint `Range`):

Debe ser un entero no negativo (>= 0). El valor se asigna
automáticamente en `preSave()` y no debe establecerse manualmente.

**Extensibilidad**:

Los módulos pueden añadir reglas de validación de hostname
personalizadas implementando
`hook_domain_validate_alter(&$error_list, $hostname)`. Cualquier
cadena añadida a `$error_list` aparecerá como una violación de
constraint.

### Dominios y cache

Si algunos cambios de variables no se reflejan cuando la página se
renderiza, puede que necesite añadir sensibilidad al dominio en la
cache del sitio.

Para ello, clone `default.services.yml` a `services.yml` (si aún no
lo ha hecho) y cambie el valor de `required_cache_contexts` a:

``` yaml
required_cache_contexts: [ 'languages:language_interface', 'theme', 'user.permissions', 'domain' ]
```

La adición de `domain` debería proporcionar el contexto de dominio que
la capa de cache requiere.

Al utilizar el módulo Domain Access, tenga en cuenta que también puede
necesitar reconstruir los permisos
(`/admin/reports/status/rebuild`) después de los cambios de
configuración.

Para desarrolladores, consulte también la
[documentación de Domain Alias](domain_alias/index.md).

### Contribuir

Para Drupal 10+, puede usar el proyecto
[Domain DDEV](https://github.com/agentrickard/domain-ddev) para
comenzar rápidamente. Incluye todas las herramientas descritas a
continuación.

Si envía un merge request, ejecute las pruebas existentes para
verificar que no haya fallos. Escribir pruebas adicionales acelerará
enormemente la finalización, ya que el código no se fusiona sin
cobertura de pruebas.

Las nuevas pruebas deben escribirse en PHPUnit como pruebas Functional,
FunctionalJavascript, Kernel o Unit.

Para configurar un entorno local adecuado, necesita múltiples dominios
o dominios comodín configurados para apuntar a su instancia de Drupal.
Usamos variantes de `example.local` para las pruebas locales. Consulte
`DomainTestBase` para la documentación. Las pruebas de Domain deberían
funcionar con hosts raíz diferentes a `example.com`, aunque también
esperamos encontrar los subdominios `one.*, two.*, three.*, four.*,
five.*` en la mayoría de los casos de prueba. Consulte
`DomainTestBase::domainCreateTestDomains()` para la lógica.

Al ejecutar las pruebas, normalmente necesita estar en el dominio por
defecto.

### Linting de código

Usamos (y recomendamos) [PHPCBF](https://phpqa.io/projects/phpcbf.html),
[PHP Codesniffer](https://github.com/squizlabs/PHP_CodeSniffer) y
[phpstan](https://phpstan.org/) para la revisión de calidad del código.

Los siguientes comandos se ejecutan antes de hacer commit:

- `vendor/bin/phpcbf web/modules/contrib/domain --standard="Drupal,DrupalPractice" -n --extensions="php,module,inc,install,test,profile,theme"`
- `vendor/bin/phpcs web/modules/contrib/domain --standard="Drupal,DrupalPractice" -n --extensions="php,module,inc,install,test,profile,theme"`
- `vendor/bin/phpstan analyse web/modules/contrib/domain`

### Configuración de phpstan

Usamos el siguiente archivo `phpstan.neon`:

```
parameters:
  level: 2
  ignoreErrors:
    # new static() is a best practice in Drupal, so we cannot fix that.
    - "#^Unsafe usage of new static#"
  drupal:
    entityMapping:
      domain:
        class: Drupal\domain\Entity\Domain
        storage: Drupal\domain\DomainStorage
      domain_alias:
          class: Drupal\domain_alias\Entity\DomainAlias
          storage: Drupal\domain_alias\DomainAliasStorage

```

El drupal entityMapping también se proporciona en
`entity_mapping.neon` en la raíz del proyecto, para su uso con otras
pruebas.

## Mantenedores

- Ken Rickard - [agentrickard](https://www.drupal.org/u/agentrickard)
