# Domain Config

Le module Domain Config fournit des surcharges de configuration par domaine. Il
permet à chaque domaine d'avoir son propre nom de site, ses paramètres de thème,
sa négociation de langue ou toute autre configuration -- le tout depuis une seule
installation Drupal.

## Architecture : 2.x vs 3.x

Domain Config 3.x est une refonte complète de la manière dont les surcharges de
configuration par domaine sont stockées et résolues. La version 2.x utilisait
une solution interne personnalisée, tandis que la 3.x s'appuie sur l'API native
**Config Collections** de Drupal.

### L'approche 2.x (legacy)

Dans Domain 2.x, les surcharges par domaine étaient stockées en tant qu'**objets
de configuration séparés** dans la collection par défaut, selon une convention de
nommage :

```
domain.config.{domain_id}.{config_name}
domain.config.{domain_id}.{language_code}.{config_name}
```

Par exemple, pour surcharger le nom du site sur `one_example_com` :

```
domain.config.one_example_com.system.site
```

Et une surcharge spécifique au français :

```
domain.config.one_example_com.fr.system.site
```

Ces objets coexistaient avec toutes les autres configurations dans le stockage
par défaut. Au moment de l'exécution, un résolveur personnalisé interceptait les
chargements de configuration, détectait le domaine actif, construisait le nom de
la surcharge, tentait de le charger, puis fusionnait le résultat par-dessus la
configuration de base.

**Limitations de cette approche :**

- Les objets de configuration polluaient l'espace de noms de la collection par
  défaut.
- Le schéma de nommage était fragile -- l'expression régulière pour extraire
  l'identifiant du domaine, le code de langue et le nom de configuration à
  partir d'une chaîne plate était sujette aux erreurs.
- Aucune intégration avec `ConfigFactoryOverrideInterface` de Drupal -- le
  mécanisme de surcharge était entièrement personnalisé.
- L'export/import de configuration ne reconnaissait pas ces objets comme des
  surcharges -- ils apparaissaient comme des objets de configuration ordinaires.
- Les surcharges spécifiques à une langue devaient être gérées séparément du
  système `LanguageConfigOverride` propre à Drupal.
- Les événements de configuration (save, delete, rename) nécessitaient une
  propagation manuelle.

### L'approche 3.x (config collections)

Domain Config 3.x stocke les surcharges dans des **config collections Drupal**
-- une fonctionnalité Drupal de premier ordre conçue exactement pour cet usage.
Les collections sont des partitions virtuelles du stockage de configuration qui
partagent le même backend (système de fichiers, base de données, etc.) mais sont
logiquement séparées.

**Nommage des collections :**

| Type | Format | Exemple |
|------|--------|---------|
| Domaine seul | `domain.{domain_id}` | `domain.one_example_com` |
| Domaine + langue | `domain.{domain_id}.language.{lang_code}` | `domain.one_example_com.language.fr` |

Au sein d'une collection, l'objet de configuration conserve son nom d'origine.
Par exemple, la surcharge du nom du site pour `one_example_com` est stockée sous
le nom `system.site` dans la collection `domain.one_example_com` -- et non sous
un nom aplati et altéré.

**Avantages clés :**

- Séparation nette entre la configuration de base et les surcharges.
- API Drupal standard (`ConfigFactoryOverrideInterface`).
- Support correct de l'export/import de configuration -- les collections sont
  exportées sous forme de sous-répertoires.
- Cascade automatique : configuration de base &rarr; surcharge de domaine &rarr;
  surcharge domaine+langue.
- Les événements de configuration (save, delete, rename) sont gérés
  automatiquement.
- Intégration avec `LanguageConfigOverride` de Drupal pour les surcharges
  spécifiques à une langue.

## Fonctionnement à l'exécution

### Résolution des surcharges

Lorsque Drupal charge un objet de configuration (par ex., `system.site`), la
config factory demande à tous les services de surcharge enregistrés de fournir
leurs surcharges. Domain Config enregistre deux services de surcharge, appliqués
dans l'ordre :

1. **`domain.config_factory_override`** (priorité -253) -- charge la surcharge
   de domaine seul depuis la collection `domain.{domain_id}`.
2. **`domain.language.config_factory_override`** (priorité -252) -- charge la
   surcharge domaine+langue depuis la collection
   `domain.{domain_id}.language.{lang_code}`.

Le résultat final fusionné suit cette cascade :

```
Base config (default collection)
  ↓ merged with
Domain override (domain.{domain_id} collection)
  ↓ merged with
Domain+language override (domain.{domain_id}.language.{lang_code} collection)
  = Final runtime config
```

**Exemple** avec `system.site` sur le domaine `two_example_com`, langue `es` :

```yaml
# Base config (default collection):
system.site:
  name: "My Site"

# Domain override (domain.two_example_com collection):
system.site:
  name: "Two"        # overrides "My Site" → "Two"

# Domain+language override (domain.two_example_com.language.es collection):
system.site:
  name: "Dos"        # overrides "Two" → "Dos"

# Final result at runtime: name = "Dos"
```

### Contexte de domaine

Le domaine actif est déterminé par `DomainNegotiationContext`, qui est injecté
dans les deux services de surcharge. Le contexte est défini lors de l'événement
kernel request par `DomainSubscriber` et peut également être modifié
programmatiquement (par ex., par Domain Config lui-même lorsqu'il compare les
configurations entre domaines).

Lorsqu'aucun domaine n'est actif (par ex., lors de commandes Drush sans
contexte de domaine), aucune surcharge n'est appliquée et la configuration de
base est utilisée.

!!! tip "Négociation anticipée pour les middlewares"
    Si des middlewares tiers ont besoin des surcharges domain_config avant que
    l'événement kernel request ne se déclenche, installez le module **Domain
    Early Negotiation** (`domain_early_negotiation`) du projet
    [Domain Extras](https://www.drupal.org/project/domain_extras).
    Voir la [documentation Domain](../domain/index.md#negociation-de-domaine-anticipee)
    pour plus de détails.

### Mise en cache

Chaque service de surcharge fournit un **suffixe de cache** basé sur
l'identifiant du domaine courant (et le code de langue pour la surcharge
linguistique). Cela garantit que les objets de configuration mis en cache pour
un domaine ne sont pas servis à un autre.

Les métadonnées de cache incluent le cache context `domain`, de sorte que le
rendu qui dépend d'une configuration spécifique à un domaine est correctement
diversifié.

## Événements du cycle de vie de la configuration

Domain Config 3.x gère correctement les événements du cycle de vie de la
configuration afin de maintenir les surcharges de domaine synchronisées avec la
configuration de base :

| Événement | Comportement |
|-----------|-------------|
| **Config save** | Pour chaque domaine, si une surcharge de domaine existe pour la configuration sauvegardée, elle est filtrée pour supprimer les valeurs identiques à la nouvelle configuration de base (en ne conservant que les surcharges effectives). |
| **Config delete** | Si la configuration de base est supprimée, la surcharge de domaine correspondante est supprimée de toutes les collections de domaine. |
| **Config rename** | Si la configuration de base est renommée, la surcharge est renommée dans toutes les collections de domaine pour correspondre. |

## Export et import de configuration

Comme les surcharges résident dans de véritables collections Drupal, elles
s'intègrent au système d'export/import de configuration :

**Structure du répertoire d'export :**

Le `FileStorage` de Drupal convertit les points dans les noms de collection en
séparateurs de répertoire. La collection `domain.one_example_com` devient le
répertoire `domain/one_example_com/`, et `domain.one_example_com.language.fr`
devient `domain/one_example_com/language/fr/` :

```
config/sync/
  system.site.yml                              # Base config
  domain/
    one_example_com/
      system.site.yml                          # Domain override
      language/
        fr/
          system.site.yml                      # Domain+language override
    two_example_com/
      system.site.yml
```

Les modules peuvent également fournir des surcharges de domaine par défaut en
utilisant la même convention dans leur répertoire `config/install/` :

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

Lorsqu'une nouvelle entité de domaine est créée, `installDomainOverrides()`
appelle `ConfigInstallerInterface::installCollectionDefaultConfig()` de Drupal
pour installer les valeurs par défaut fournies par les modules pour la collection
de ce domaine.

## Domain Config UI

Le module optionnel **Domain Config UI** fournit une interface utilisateur pour
gérer les surcharges par domaine directement depuis les formulaires de
configuration existants.

Voir la [documentation Domain Config UI](../domain_config_ui/index.md) pour
plus de détails sur :

- L'activation/désactivation des surcharges par configuration et par domaine.
- Le bouton de bascule intégré dans les formulaires d'administration.
- Les configurations non autorisées.
- Le contrôle programmatique via des alter hooks.

## Migration de 2.x vers 3.x

Une migration automatique est fournie via `domain_config_update_10001()`. Le
service de migration (`DomainConfigMigration`) effectue les étapes suivantes :

1. **Analyse les objets de configuration legacy** -- recherche toutes les
   entrées `domain.config.{domain_id}.*` dans la collection par défaut.
2. **Parse le nom legacy** -- extrait l'identifiant du domaine, le code de
   langue optionnel et le nom de configuration à l'aide du pattern :
   ```
   /^domain\.config\.{domain_id}(?:\.([a-z]{2}))?\.([^.]+\.[^.]+)$/
   ```
3. **Écrit dans les collections** -- copie les données dans la collection
   `domain.{domain_id}` ou `domain.{domain_id}.language.{lang_code}`
   appropriée.
4. **Met à jour le registre** -- si Domain Config UI est installé, met à jour
   le paramètre `overridable_configurations` dans `domain_config_ui.settings`.
5. **Nettoie** -- supprime les objets legacy `domain.config.*` de la collection
   par défaut.

La migration s'exécute automatiquement lors de `drush updatedb`. Si la migration
d'un domaine échoue, le update hook lève une `UpdateException` avec les détails.

## Services

| Service | Classe | Rôle |
|---------|--------|------|
| `domain.config_factory_override` | `DomainConfigFactoryOverride` | Surcharges de configuration par domaine seul (priorité -253) |
| `domain.language.config_factory_override` | `DomainLanguageConfigFactoryOverride` | Surcharges de configuration domaine+langue (priorité -252) |
| `domain.language_manager` | `DomainConfigLanguageManager` | Décore `language_manager` pour intégrer les surcharges linguistiques de domaine |
| `domain_config.library.discovery.collector` | `DomainConfigLibraryDiscoveryCollector` | Décore la découverte de bibliothèques pour varier par domaine |
| `domain_config.config_migration` | `DomainConfigMigration` | Service de migration 2.x &rarr; 3.x |

## Issues associées

- [Domain Config collections](https://www.drupal.org/project/domain/issues/3221779)
