# Domain Config UI

Le module Domain Config UI fournit un moyen simple d'activer des surcharges
de configuration par domaine directement depuis les formulaires de
configuration existants. Lorsqu'un formulaire de configuration pris en charge
est consulté dans un contexte de domaine, les utilisateurs disposant des
droits adéquats voient un bouton en ligne permettant d'activer (ou de
supprimer) une surcharge de configuration spécifique au domaine pour cet objet
de configuration.

## Fonctionnement

- Lorsqu'un utilisateur disposant des permissions appropriées visite un
  formulaire de configuration d'administration sur un nom d'hôte de domaine
  (par exemple, `https://one.example.com`), le module inspecte le formulaire
  pour identifier le ou les objets de configuration sous-jacents.
- Si la configuration peut être surchargée par domaine, un lien d'action
  en ligne apparaît en haut du formulaire :
    - Activer la configuration de domaine
    - Supprimer la configuration de domaine (une fois celle-ci activée)
- Une fois activée, les modifications soumises via ce formulaire sont
  enregistrées en tant que surcharges par domaine, sans modifier la
  configuration globale.

## Configurations non autorisées

Vous pouvez explicitement empêcher certains objets de configuration d'être
surchargés par domaine. Lorsqu'un nom de configuration est interdit, le lien
de basculement en ligne est masqué sur le formulaire correspondant.

### Où configurer

- Interface : Administration > Configuration > Domain > Domain Config UI
  (`/admin/config/domain/config-ui`)
- Clé de configuration : `domain_config_ui.settings: disallowed_configurations`

### Exemple

Pour interdire les surcharges au niveau du domaine pour le formulaire
Informations du site (`system.site`) et les paramètres de thème
(`system.theme`), définissez la configuration suivante (YAML), ou via le
formulaire de paramètres :

```yaml
domain_config_ui.settings:
  disallowed_configurations:
    - system.site
    - system.theme
```

Avec la configuration ci-dessus, le lien "Activer la configuration de
domaine" n'apparaîtra plus sur :

- Informations du site : `/admin/config/system/site-information` (`system.site`)
- Pages d'apparence et de paramètres de thème correspondant à `system.theme`

### Notes

- Le module empêche également la surcharge de ses propres paramètres par
  défaut.
- La vérification est appliquée côté serveur via la fabrique de
  configuration, et pas seulement masquée dans l'interface.

### Issue associée

- [#3562763](https://www.drupal.org/project/domain/issues/3562763)

## Contrôle programmatique

Deux hooks alter permettent un contrôle avancé sur l'affichage du bouton de
basculement et sur les objets de configuration éligibles aux surcharges par
domaine.

### Alter des configurations non autorisées

Empêcher les surcharges de domaine pour des noms de configuration spécifiques
de manière globale :

```php
/**
 * Implements hook_domain_config_ui_disallowed_configurations_alter().
 */
function mymodule_domain_config_ui_disallowed_configurations_alter(array &$disallowed): void {
  // Disallow domain overrides for image toolkit settings site-wide.
  $disallowed[] = 'system.image';
}
```

### Alter des routes non autorisées

Masquer entièrement le bouton de basculement sur des routes spécifiques (même
si la configuration sous-jacente serait normalement autorisée) :

```php
/**
 * Implements hook_domain_config_ui_disallowed_routes_alter().
 */
function mymodule_domain_config_ui_disallowed_routes_alter(array &$routes): void {
  // Do not show the toggle on the account settings page.
  $routes[] = 'entity.user.admin_form';
}
```

## Aperçu des permissions

Les permissions courantes utilisées par ce module incluent :

- `use domain config ui` — voir et utiliser le bouton de basculement en
  ligne sur les formulaires autorisés
- `administer domain config ui` — gérer les paramètres
- `set default domain configuration` — gérer les valeurs par défaut et
  spécifiques au domaine
- `translate domain configuration` — gérer les surcharges linguistiques par
  domaine

Assurez-vous que les utilisateurs opèrent dans un contexte de domaine
(c'est-à-dire en visitant le site sur le nom d'hôte d'un domaine) pour que
le bouton de basculement soit disponible.

## Références de tests

Le dépôt inclut des tests fonctionnels et JavaScript qui illustrent le
comportement attendu :

- `DomainConfigUISettingsTest` — activation/suppression des surcharges
  depuis les formulaires courants
- `DomainConfigUIDisallowedConfigurationsTest` — vérifie que l'ajout de
  `system.site` à `disallowed_configurations` masque le bouton de
  basculement sur Informations du site
- `DomainConfigUIOptionsTest` et `DomainConfigUIPermissionsTest` —
  couverture des permissions et des options

Ces tests peuvent servir d'exemples lors de l'intégration de cette
fonctionnalité dans des modules personnalisés.
