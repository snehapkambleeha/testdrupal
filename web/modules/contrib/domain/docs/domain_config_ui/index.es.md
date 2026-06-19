# Domain Config UI

El módulo Domain Config UI proporciona una forma ligera de activar
sobrescrituras de configuración por dominio directamente desde los
formularios de configuración existentes. Al visitar un formulario de
configuración compatible dentro de un contexto de dominio, los usuarios
con los privilegios apropiados verán un enlace de acción en línea para
activar (o eliminar) una sobrescritura específica de dominio para ese
objeto de configuración.

## Cómo funciona

- Cuando un usuario con los permisos apropiados visita un formulario
  de configuración de administración en un host de dominio (por
  ejemplo, `https://one.example.com`), el módulo inspecciona el
  formulario para identificar los objetos de configuración subyacentes.
- Si se permite sobrescribir la configuración por dominio, aparece un
  enlace de acción en línea en la parte superior del formulario:
    - Enable domain configuration
    - Remove domain configuration (después de haberla activado)
- Una vez activada, los cambios enviados desde ese formulario se
  almacenan como sobrescrituras por dominio, sin alterar la
  configuración global.

## Configuraciones no permitidas

Puede impedir explícitamente que ciertos objetos de configuración sean
sobrescritos por dominio. Cuando un nombre de configuración está
marcado como no permitido, el enlace de acción en línea se oculta en
el formulario correspondiente.

### Dónde configurar

- UI: Administración > Configuración > Domain > Domain Config UI
  (`/admin/config/domain/config-ui`)
- Clave de configuración:
  `domain_config_ui.settings: disallowed_configurations`

### Ejemplo

Para prohibir las sobrescrituras a nivel de dominio del formulario Site
Information (`system.site`) y la configuración del tema
(`system.theme`), establezca lo siguiente en su configuración (YAML),
o a través del formulario de ajustes:

```yaml
domain_config_ui.settings:
  disallowed_configurations:
    - system.site
    - system.theme
```

Con la configuración anterior, el enlace "Enable domain configuration"
ya no aparecerá en:

- Site Information: `/admin/config/system/site-information`
  (`system.site`)
- Las páginas de apariencia y configuración de tema asociadas a
  `system.theme`

### Notas

- El módulo también impide sobrescribir su propia configuración por
  defecto.
- La verificación se aplica del lado del servidor a través de la
  config factory, no solo se oculta en la interfaz de usuario.

### Issue relacionado

- [#3562763](https://www.drupal.org/project/domain/issues/3562763)

## Control programático

Dos alter hooks permiten un control avanzado sobre dónde se muestra el
enlace de acción y qué objetos de configuración son elegibles para
sobrescrituras por dominio.

### Alter de configuraciones no permitidas

Impide las sobrescrituras de dominio para nombres de configuración
específicos de forma global:

```php
/**
 * Implements hook_domain_config_ui_disallowed_configurations_alter().
 */
function mymodule_domain_config_ui_disallowed_configurations_alter(array &$disallowed): void {
  // No permitir sobrescrituras de dominio para la configuración
  // del toolkit de imágenes en todo el sitio.
  $disallowed[] = 'system.image';
}
```

### Alter de rutas no permitidas

Oculta el enlace de acción completamente en rutas específicas (incluso
si la configuración subyacente normalmente estaría permitida):

```php
/**
 * Implements hook_domain_config_ui_disallowed_routes_alter().
 */
function mymodule_domain_config_ui_disallowed_routes_alter(array &$routes): void {
  // No mostrar el enlace de acción en la página de configuración
  // de cuentas.
  $routes[] = 'entity.user.admin_form';
}
```

## Resumen de permisos

Los permisos comunes utilizados por este módulo incluyen:

- `use domain config ui` — ver y utilizar el enlace de acción en línea
  en los formularios permitidos
- `administer domain config ui` — gestionar los ajustes
- `set default domain configuration` — gestionar los valores por
  defecto frente a los específicos de dominio
- `translate domain configuration` — gestionar las sobrescrituras
  específicas de idioma por dominio

Asegúrese de que los usuarios operen dentro de un contexto de dominio
(es decir, visitando el sitio en un host de dominio) para que el enlace
de acción esté disponible.

## Referencias de pruebas

El repositorio incluye pruebas funcionales y JavaScript que ilustran
el comportamiento esperado:

- `DomainConfigUISettingsTest` — activar/eliminar sobrescrituras desde
  formularios comunes
- `DomainConfigUIDisallowedConfigurationsTest` — verifica que añadir
  `system.site` a `disallowed_configurations` oculte el enlace de
  acción en Site Information
- `DomainConfigUIOptionsTest` y `DomainConfigUIPermissionsTest` —
  cobertura de permisos y opciones

Estas pruebas pueden servir como ejemplos al integrar la funcionalidad
en módulos personalizados.
