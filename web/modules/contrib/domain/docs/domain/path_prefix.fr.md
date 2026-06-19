# Préfixe de chemin

Le module Domain prend en charge un **préfixe de chemin** optionnel sur les
enregistrements de domaine, permettant à plusieurs domaines de partager un
même nom d'hôte tout en étant distingués par le premier segment du chemin
de l'URL.

Cela est utile pour les sites qui ne peuvent pas ajouter de nouveaux noms
d'hôte (par exemple, pare-feu d'entreprise, hébergement mutualisé, CDN
à origine unique) mais qui ont besoin de contextes de domaine distincts
pour différents publics, régions ou marques.

## Fonctionnement

Chaque enregistrement de domaine possède une propriété optionnelle
`path_prefix` (chaîne vide par défaut). Lorsque plusieurs domaines
partagent le même nom d'hôte avec des préfixes différents, le négociateur
les désambiguïse en comparant le chemin de la requête avec le préfixe de
chaque domaine.

### Exemple

Soit trois enregistrements de domaine :

| Nom du domaine | Nom d'hôte | Préfixe de chemin | Objectif |
|----------------|------------|-------------------|----------|
| Default | `example.com` | *(vide)* | Site principal |
| Belgium NL | `example.com` | `benl` | Néerlandais belge |
| France | `example.com` | `fr` | Français |

Une requête vers `example.com/benl/fr/about-us` est traitée comme suit :

1. **Négociation de domaine** -- le négociateur charge tous les domaines
   correspondant à `example.com`, trouve trois candidats et compare le
   préfixe `benl` avec le chemin de la requête `/benl/fr/about-us`.
2. **Traitement inbound du chemin** -- le `DomainPrefixPathProcessor`
   (priorité 350) supprime le préfixe de domaine, produisant `/fr/about-us`.
3. **Négociation de langue** -- la négociation de langue du cœur supprime
   le préfixe de langue `fr`, produisant `/about-us`.
4. **Résolution des alias de chemin** -- Drupal résout `/about-us` vers le
   chemin interne (par exemple, `/node/1`).

La génération outbound des URL inverse le processus :

1. **Alias de chemin** -- `/node/1` devient `/about-us`.
2. **Processeur de langue** -- ajoute le préfixe `fr/`.
3. **Processeur de préfixe de domaine** (priorité 50) -- ajoute `benl/` en
   tête du préfixe d'URL, produisant `benl/fr/about-us`.

### Règles de correspondance des préfixes

- **Préfixe le plus long en premier** -- lorsque des préfixes se chevauchent
  (par exemple, `fr` et `fr-be`), le préfixe correspondant le plus long
  l'emporte. Une requête vers `/fr-be/page` correspond à `fr-be`, et non
  à `fr`.
- **Préfixe vide comme solution de repli** -- le domaine sans préfixe sert
  de solution de repli lorsqu'aucun préfixe ne correspond au chemin de la
  requête.
- **Correspondance exacte par segment** -- le préfixe doit correspondre à
  un segment complet du chemin. `/france/page` ne correspond **pas** au
  préfixe `fr`.

## Configuration

### Activer la prise en charge du préfixe de chemin

La prise en charge du préfixe de chemin est désactivée par défaut. Pour
l'activer, rendez-vous sur la page de configuration de Domain
(`/admin/config/domain/settings`), développez **Experimental
features** et cochez **Enable path prefix support**.

Lorsque la fonctionnalité est désactivée, tous les composants liés au
préfixe de chemin sont retirés du conteneur (aucun surcoût à l'exécution),
le champ de préfixe de chemin est masqué dans le formulaire de domaine
et la colonne de préfixe est masquée dans la liste des domaines.

### Ajouter un préfixe de chemin à un domaine

Une fois la prise en charge du préfixe de chemin activée, le formulaire
d'ajout ou de modification de domaine
(`/admin/config/domain/add` ou `/admin/config/domain/edit/{domain}`)
affiche un champ **Path prefix**. La valeur doit être une chaîne simple,
sans barre oblique en début ou en fin (par exemple, `fr`, `benl`,
`asia-pacific`).

### Contrainte d'unicité

La combinaison du nom d'hôte et du préfixe de chemin doit être unique. Deux
domaines peuvent partager le même nom d'hôte uniquement si leurs préfixes
de chemin diffèrent. Tenter d'enregistrer deux domaines avec le même nom
d'hôte et le même préfixe (y compris les deux vides) déclenchera une erreur
de validation.

### Rétrocompatibilité

Les enregistrements de domaine existants ont par défaut un préfixe de chemin
vide. La fonctionnalité est désactivée par défaut et doit être activée dans
`/admin/config/domain/settings`.

## Interaction avec les autres modules

### Négociation de langue (préfixes d'URL)

Le préfixe de domaine est le segment de chemin **le plus externe**, placé
avant tout préfixe de langue. L'ordre de traitement est le suivant :

| Direction | Priorité | Processeur | Action |
|-----------|----------|------------|--------|
| Inbound | 350 | `DomainPrefixPathProcessor` | Supprime le préfixe de domaine |
| Inbound | 300 | `LanguageNegotiationUrl` | Supprime le préfixe de langue |
| Outbound | 100 | `LanguageNegotiationUrl` | Ajoute le préfixe de langue |
| Outbound | 50 | `DomainPrefixPathProcessor` | Ajoute le préfixe de domaine |

Une URL telle que `/benl/fr/about-us` se décompose ainsi :

```
/benl/fr/about-us
 ^^^^           → domain prefix (stripped first inbound, added last outbound)
      ^^        → language prefix
         ^^^^^^^^ → path alias
```

### Domain Access

Domain Access attribue la visibilité du contenu par domaine. Les domaines
avec préfixe sont des entités de domaine à part entière, donc les valeurs
des champs Domain Access et les droits d'accès aux nœuds (node grants)
fonctionnent de manière identique -- chaque domaine préfixé peut avoir ses
propres attributions de contenu.

### Domain Config / Domain Config UI

Domain Config fournit des surcharges de configuration par domaine. Chaque
domaine préfixé est une entité de configuration distincte et reçoit donc
ses propres surcharges de configuration comme attendu.

### Domain Alias

Domain Alias fournit des noms d'hôte alternatifs pour un domaine. Les alias
correspondent par nom d'hôte, et non par préfixe de chemin.

**Important :** lorsque plusieurs domaines partagent le même nom d'hôte avec
des préfixes de chemin différents, vous devez créer les alias uniquement sur
le **domaine sans préfixe (par défaut)** pour ce nom d'hôte. L'alias résout
le nom d'hôte ; la négociation de préfixe sélectionne ensuite le bon domaine
en fonction du chemin de l'URL. Créer le même motif d'alias sur un domaine
préfixé échouera car les motifs d'alias sont globalement uniques.

Par exemple, si `example.com` (sans préfixe) et `example.com`
(préfixe `fr`) partagent un nom d'hôte, ajoutez `*.example.com` comme
alias uniquement sur le domaine sans préfixe. Les requêtes vers
`something.example.com/fr/page` résoudront l'alias vers `example.com`,
puis la négociation de préfixe sélectionnera le domaine `fr`.

### Domain Source

Domain Source attribue un domaine canonique au contenu pour la génération
d'URL. Lorsque le domaine source d'une entité de contenu possède un préfixe
de chemin, l'URL générée inclut automatiquement le préfixe.

### Domain Path

Domain Path opère sur les chemins internes (après la suppression inbound du
préfixe), il fonctionne donc sans modification.

### Domain Content

Domain Content fournit des pages de vue d'ensemble du contenu par domaine.
Chaque domaine préfixé apparaît comme une entrée distincte dans le filtre
de domaine.

## Détails techniques

### Utilisation programmatique

```php
// Get the path prefix of a domain entity.
$prefix = $domain->getPathPrefix();

// Set the path prefix.
$domain->setPathPrefix('benl');
$domain->save();

// Load all domains sharing a hostname.
$storage = \Drupal::entityTypeManager()->getStorage('domain');
$domains = $storage->loadMultipleByHostname('example.com');

// getBasePath() returns scheme + hostname + base_path (no prefix).
// Use this when building base URLs for outbound path processors.
$base = $domain->getBasePath();
// e.g. "http://example.com/"

// getPath() returns the full path including the prefix.
// Use this for display and direct linking.
$path = $domain->getPath();
// e.g. "http://example.com/fr/"
```

### Génération outbound des URL

Le `DomainPrefixPathProcessor` ajoute le préfixe à l'option `prefix`
utilisée par le générateur d'URL de Drupal. Pour les URL générées avec
l'option `domain` (voir
[Réécriture d'URL inter-domaines](index.md#reecriture-durl-inter-domaines)), le
préfixe du domaine cible est utilisé. Pour toutes les autres URL, le
préfixe du domaine actif est utilisé.

```php
use Drupal\Core\Url;

// URL targeting a prefixed domain includes the prefix automatically.
$domain = $storage->load('example_com_fr');
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
$url->setOption('domain', $domain);
// Generates: http://example.com/fr/node/1
```

### Installations en sous-répertoire

La prise en charge du préfixe de chemin fonctionne correctement lorsque
Drupal est installé dans un sous-répertoire (par exemple,
`example.com/drupal/`). Les méthodes `setUrl()` et `setPath()` utilisent
`Request::getPathInfo()` et `Request::getBasePath()` de Symfony pour
séparer le chemin de base du sous-répertoire du chemin de route avant la
manipulation du préfixe. L'URL résultante conserve l'ordre correct :
`scheme + hostname + base_path + prefix + route_path`.

Par exemple, avec un chemin de base `/drupal/` et un préfixe `fr`, une
requête vers `/drupal/fr/admin/config` produit l'URL
`http://example.com/drupal/fr/admin/config`.

### Performance

La fonctionnalité n'a aucun impact mesurable sur les performances :

- **Lorsqu'elle est désactivée** -- le path processor du préfixe de chemin,
  la surcharge de négociation de langue et la logique de négociation de
  préfixe sont entièrement retirés du conteneur. Aucun surcoût à
  l'exécution.
- **Lorsqu'elle est activée mais qu'aucun domaine n'utilise de préfixe** --
  tous les chemins de code retournent immédiatement grâce à des
  vérifications de chaîne vide.
- **Lorsque des préfixes sont actifs** -- le négociateur trie un petit
  tableau en mémoire (généralement 2 à 5 entrées) et effectue une
  comparaison de chaîne par candidat. Aucune requête de stockage
  supplémentaire n'est émise.
- **Pas de nouveau cache context** -- le processeur outbound ajoute le
  cache context `domain`, qui est déjà présent sur chaque page d'un site
  utilisant les domaines. Aucune fragmentation de cache supplémentaire
  n'est introduite.

### Préfixes non-ASCII

Le paramètre **Allow non-ASCII characters** sur la page de configuration
de Domain (`/admin/config/domain/settings`) s'applique également aux
préfixes de chemin. Lorsqu'il est activé, les lettres minuscules Unicode
et les chiffres sont acceptés dans les préfixes (par exemple, `belgië`,
`日本`). Lorsqu'il est désactivé (valeur par défaut), seuls les caractères
ASCII minuscules `a-z`, les chiffres `0-9`, les tirets et les tirets bas
sont autorisés.

### Schéma de configuration

Le champ `path_prefix` est déclaré dans `domain.schema.yml` en tant que
propriété `string` sur `domain.record.*` avec une contrainte `Regex`
utilisant des classes de caractères Unicode (`\p{L}`, `\p{N}`) comme
base permissive :

```yaml
domain.record.*:
  type: config_entity
  mapping:
    # ... existing fields ...
    path_prefix:
      type: string
      label: 'Path prefix'
      constraints:
        Regex:
          pattern: '/^([\p{L}\p{N}][\p{L}\p{N}_\-]*)?$/u'
          message: 'The path prefix may only contain ...'
```

La vérification plus stricte limitée à l'ASCII est appliquée lors de la
validation du formulaire et dans `preSave()` de l'entité lorsque le
paramètre **Allow non-ASCII characters** est désactivé. L'expression
régulière du schéma sert de base qui intercepte les valeurs totalement
invalides lors des importations de configuration.

Cela rend le schéma `domain.record.*` entièrement validable via
`TypedConfigManager::createFromNameAndData()->validate()`, interceptant
les valeurs invalides lors des importations de configuration et de la
validation des formulaires sans nécessiter d'enregistrement.

## Tickets associés

- [#3575947: Support path-prefix-based domain separation on a single hostname](https://www.drupal.org/i/3575947)
