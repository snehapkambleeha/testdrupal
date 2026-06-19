# Domain Alias

Le module Domain Alias permet d'associer plusieurs noms d'hôte à un seul
enregistrement de domaine. Un alias peut correspondre à un nom d'hôte exact ou
utiliser des motifs wildcard, et peut optionnellement rediriger vers le domaine
parent.

## Principe clé : les enregistrements de domaine contiennent les noms d'hôte canoniques

Les enregistrements de domaine doivent utiliser vos **noms d'hôte de production
canoniques** -- ceux que vous souhaitez voir apparaître dans les URL générées,
les sitemaps et les métadonnées SEO. Les alias remplissent alors deux
fonctions :

1. **Correspondance d'environnement** -- les noms d'hôte de développement
   local, de staging et de CI qui résolvent vers les enregistrements de
   domaine de production.
2. **Redirections de production** -- les noms d'hôte de production alternatifs
   (par ex. `www.example.com`) qui redirigent vers le nom d'hôte canonique
   via 301/302.

### Exemple

Si votre site de production fonctionne sur `example.com` et
`shop.example.com`, créez deux enregistrements de domaine avec ces noms
d'hôte. Ajoutez ensuite des alias :

| Motif d'alias | Domaine parent | Env | Redirection |
|---------------|----------------|-----|-------------|
| `www.example.com` | `example.com` | default | 301 |
| `www.shop.example.com` | `shop.example.com` | default | 301 |
| `example.local` | `example.com` | local | -- |
| `shop.example.local` | `shop.example.com` | local | -- |
| `example.staging.acme.com` | `example.com` | staging | -- |
| `shop.staging.acme.com` | `shop.example.com` | staging | -- |

Cette approche garantit que :

- **Les URL de production sont toujours canoniques** -- les liens générés,
  les sitemaps et les métadonnées SEO utilisent les vrais noms d'hôte de
  production.
- **Les noms d'hôte alternatifs redirigent correctement** -- les visiteurs
  accédant à `www.example.com` sont redirigés vers `example.com` avec le
  code de statut HTTP approprié.
- **La réécriture d'environnement fonctionne correctement** -- lors de la
  visite d'un alias dans un environnement non par défaut, tous les noms
  d'hôte de domaine sont réécrits vers leurs alias d'environnement
  correspondants (voir [Environnements](#environnements)).
- **Les surcharges de configuration sont prévisibles** -- les surcharges
  Domain Config sont indexées par l'identifiant de l'enregistrement de
  domaine, qui est dérivé du nom d'hôte canonique.

**Ne créez pas** d'enregistrements de domaine avec des noms d'hôte de
développement ou de staging pour ensuite aliaser le nom d'hôte de production
vers ceux-ci. Cela inverse la relation prévue et casse la réécriture
d'environnement, la génération d'URL et les surcharges de configuration.

## Propriétés des alias

Chaque alias est une entité de configuration avec les champs suivants :

| Champ | Description |
|-------|-------------|
| **Pattern** | Le motif de nom d'hôte à faire correspondre (80 caractères max). |
| **Redirect** | `0` = pas de redirection, `301` = permanente, `302` = temporaire. |
| **Environment** | L'environnement serveur auquel cet alias appartient. |
| **Weight** | Ordre de tri pour la correspondance (plus bas = priorité plus haute). |

Les alias sont gérés par domaine à l'adresse
`/admin/config/domain/alias/{domain}`.

## Correspondance de motifs

Lorsqu'une requête arrive et ne correspond exactement à aucun enregistrement
de domaine enregistré, Domain Alias recherche un motif d'alias correspondant.

### Ordre de correspondance

1. **Enregistrement de domaine exact** -- géré par le module Domain de base.
2. **Alias exact** -- un alias sans wildcard qui correspond au nom d'hôte.
3. **Alias wildcard** -- triés par spécificité (moins de wildcards d'abord,
   motifs plus longs d'abord).

### Syntaxe wildcard

Le caractère `*` correspond à un ou plusieurs caractères dans un segment de
nom d'hôte. Un maximum d'un wildcard par alias est autorisé.

```
*.example.com        matches one.example.com, two.example.com
example.*.com        matches example.dev.com
example.*            matches example.com, example.local
*.com                matches anything.com
```

### Correspondance de port

Les ports peuvent être inclus dans les motifs d'alias. Les règles sont :

- **Ports par défaut (80, 443)** : une requête sur ces ports correspond aux
  alias avec ou sans indicateur de port. Par exemple, `example.com:80`
  correspond à la fois à `example.com` et `example.com:80`.
- **Ports non par défaut** : une requête sur le port 8080 ne correspond
  qu'aux alias qui incluent explicitement un port. `example.com:8080`
  correspond à `example.com:8080` et `example.com:*`, mais **pas** à
  `example.com`.

```
example.com:8080     matches only example.com:8080
example.com:*        matches example.com on any port
*.com:*              matches anything.com on any port
```

## Redirection

Lorsqu'un alias a une valeur de redirection de `301` ou `302`, l'utilisateur
est redirigé vers le domaine parent avec le code de statut HTTP correspondant.
Ceci est utile pour consolider le trafic des noms d'hôte alternatifs vers un
domaine canonique.

Pour les alias d'environnement non par défaut avec une redirection, la cible
de redirection est résolue vers le premier alias sans redirection dans le même
environnement plutôt que vers le nom d'hôte canonique. Cela évite de rediriger
le trafic de développement vers les URL de production.

## Interaction avec le préfixe de chemin

Lorsque le [support du préfixe de chemin](../domain/path_prefix.md) est
activé, plusieurs domaines peuvent partager le même nom d'hôte (par ex.
`example.com` avec les préfixes `fr`, `benl`, etc.). Les alias résolvent
**uniquement les noms d'hôte** — la négociation du préfixe de chemin
s'effectue automatiquement ensuite.

### Fonctionnement

1. L'alias résout un nom d'hôte vers son enregistrement de domaine parent.
2. Si le support du préfixe de chemin est activé, tous les domaines
   partageant le nom d'hôte résolu sont chargés.
3. `negotiateByPathPrefix()` sélectionne le bon domaine en fonction du
   chemin de la requête courante.

### Où ajouter les alias

Si plusieurs domaines partagent un nom d'hôte avec des préfixes de chemin
différents, vous n'avez besoin d'ajouter des alias qu'à **un seul**
d'entre eux — typiquement le domaine sans préfixe. Le formulaire d'alias
affiche un avertissement lorsque le domaine parent utilise un préfixe de
chemin, suggérant d'ajouter les alias au domaine sans préfixe à la place.

### Réécriture d'environnement

Lors de la réécriture d'environnement, les domaines qui partagent le même
nom d'hôte canonique que le domaine actif (c'est-à-dire qu'ils ne diffèrent
que par le préfixe de chemin) sont réécrits directement en utilisant le nom
d'hôte de la requête courante — aucune recherche d'alias supplémentaire
n'est nécessaire.

## Environnements

Les alias peuvent être étiquetés avec un **environnement** pour prendre en
charge les workflows de développement multi-environnements. Lorsque la requête
active correspond à un alias dans un environnement non par défaut, tous les
noms d'hôte de domaine sont réécrits vers leurs alias spécifiques à
l'environnement correspondant. Cela garantit que les liens générés restent
dans l'environnement courant.

### Environnements par défaut

- `default` -- URL canoniques, aucune réécriture n'est effectuée.
- `local` -- développement local.
- `development` -- serveur d'intégration.
- `staging` -- serveur de pré-déploiement.
- `testing` -- environnements CI.

La liste peut être surchargée dans `settings.php` (voir
[Configuration](#configuration)). Le projet
[Domain Extras](https://www.drupal.org/project/domain_extras) inclut un
sous-module **Domain Alias Extras** qui fournit une interface pour
personnaliser cette liste.

!!! warning "Les environnements de preview et de CI doivent utiliser un environnement non par défaut"

    Si vous utilisez des alias wildcard pour les environnements de preview ou
    de CI (par ex. `*.tugboatqa.com`, `*.ci-host.com`), assignez-les à un
    environnement non par défaut tel que `local` ou `testing`. Avec
    l'environnement `default`, la réécriture des noms d'hôte est ignorée,
    donc les liens générés (y compris dans la liste d'administration des
    domaines) pointeront vers le nom d'hôte de production canonique au lieu
    de l'URL de preview réelle.

### Exemple de configuration

Considérons un site avec trois domaines de production :

| Domaine | Nom d'hôte |
|---------|------------|
| Principal | `example.com` |
| Foo | `foo.example.com` |
| Bar | `bar.example.com` |

Pour le développement local, créez des alias étiquetés comme `local` :

| Alias | Domaine parent | Environnement |
|-------|----------------|---------------|
| `example.local` | `example.com` | local |
| `foo.example.local` | `foo.example.com` | local |
| `bar.example.local` | `bar.example.com` | local |

Lorsqu'un développeur visite `foo.example.local` :

1. Aucun enregistrement de domaine exact ne correspond.
2. L'alias `foo.example.local` correspond, pointant vers `foo.example.com`.
3. Comme l'alias est dans l'environnement `local`, tous les domaines voient
   leurs noms d'hôte réécrits : `example.com` devient `example.local`,
   `bar.example.com` devient `bar.example.local`.
4. Tous les liens générés sur la page utilisent les domaines `.local`.

### Environnements wildcard

Les alias wildcard fonctionnent également avec les environnements. En plaçant
le wildcard en position de TLD, un seul jeu d'alias couvre plusieurs
environnements (`.local`, `.dev`, `.test`, etc.) sans duplication :

| Alias | Domaine parent | Environnement |
|-------|----------------|---------------|
| `example.*` | `example.com` | local |
| `foo.example.*` | `foo.example.com` | local |
| `bar.example.*` | `bar.example.com` | local |

Lorsqu'un développeur visite `foo.example.local` :

1. L'alias `foo.example.*` correspond, capturant `local`.
2. Pour les autres domaines, leurs alias `local` sont chargés et les
   wildcards sont remplacés par la valeur capturée : `example.*` devient
   `example.local`, `bar.example.*` devient `bar.example.local`.

Les mêmes alias fonctionnent aussi pour `foo.example.dev`,
`foo.example.test`, etc. -- tous résolus via l'environnement `local`.

## Règles de validation

Les enregistrements d'alias sont validés via des plugins de contrainte Symfony
déclarés dans le schéma de configuration (`domain_alias.schema.yml`). Ces
contraintes s'exécutent automatiquement lors de l'enregistrement via le
formulaire d'administration ou les commandes Drush.

**Pattern** (contraintes `DomainAliasPattern` + `DomainAliasUniquePattern`) :

1. Au moins un point requis (sauf `localhost`).
2. Un seul wildcard (`*` ou `?`) par motif.
3. Un seul deux-points (`:`) pour la spécification du port.
4. Après un deux-points, seul un entier ou `*` est autorisé.
5. Pas de points en début ou en fin.
6. Caractères ASCII uniquement (sauf si `domain.settings:allow_non_ascii`
   est activé).
7. Ne peut pas correspondre à un nom d'hôte de domaine existant.
8. Doit être unique parmi tous les alias.

**Redirect** (contrainte `Choice`) :

Doit être l'une des valeurs `0` (pas de redirection), `301` ou `302`.

**Environment** (contrainte `DomainAliasEnvironment`) :

Doit être l'une des valeurs définies dans
`domain_alias.settings:environments`.

## Suppression en cascade

Lorsqu'un enregistrement de domaine est supprimé, tous ses alias sont
automatiquement supprimés.

## Permissions

| Permission | Description |
|------------|-------------|
| `administer domain aliases` | Contrôle total sur tous les alias. |
| `create domain aliases` | Créer des alias (limité aux domaines assignés). |
| `edit domain aliases` | Modifier des alias (limité aux domaines assignés). |
| `delete domain aliases` | Supprimer des alias (limité aux domaines assignés). |
| `view domain aliases` | Voir les alias (limité aux domaines assignés). |

## Commandes Drush

### domain-alias:list

Liste les alias avec des filtres optionnels.

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

Aliases : `domain-aliases`, `domain-alias-list`

### domain-alias:add

Crée un nouvel alias pour un domaine.

```bash
drush domain-alias:add example.com test.example.com
drush domain-alias:add example.com test.example.com --environment=local
drush domain-alias:add example.com test.example.com --redirect=301
drush domain-alias:add example.com '*.example.local' --environment=local
```

```
Created the alias test.example.com with machine id test_example_com.
```

Aliases : `domain-alias-add`

Options :

| Option | Description |
|--------|-------------|
| `--machine_name` | Surcharger le nom machine généré automatiquement. |
| `--redirect` | `0` (pas de redirection), `301` ou `302`. Par défaut `0`. |
| `--environment` | Étiquette d'environnement. Par défaut `default`. |

### domain-alias:update

Met à jour un alias existant.

```bash
drush domain-alias:update test.example.com --environment=local
drush domain-alias:update test.example.com --pattern=test2.example.com
drush domain-alias:update test.example.com --redirect=301
```

```
Domain Alias updated successfully.
```

Aliases : `domain-alias-update`

Options :

| Option | Description |
|--------|-------------|
| `--pattern` | Modifier le motif d'alias. |
| `--redirect` | `0`, `301` ou `302`. |
| `--environment` | Modifier l'étiquette d'environnement. |

### domain-alias:delete

Supprime un alias unique par motif.

```bash
drush domain-alias:delete test.example.com
```

```
Domain Alias test.example.com with id test_example_com deleted.
```

Aliases : `domain-alias-delete`

### domain-alias:delete-bulk

Supprime plusieurs alias pour un domaine, avec des filtres optionnels.

```bash
drush domain-alias:delete-bulk example.com
drush domain-alias:delete-bulk example.com --environment=local
drush domain-alias:delete-bulk example.com --redirect=301
```

```
Aliases Deleted Successfully: (example_local) example.local, (star_example_local) *.example.local
```

Aliases : `domain-alias-delete-bulk`

## Performance

Domain Alias s'exécute à chaque requête dans le cadre de la négociation de
domaine. Voici un détail précis de ce qui se passe et quand.

### Quand le nom d'hôte correspond à un enregistrement de domaine (cas le plus courant)

Sur un site de production, les requêtes entrantes correspondent typiquement
directement à un enregistrement de domaine. Dans ce cas, Domain Alias ne fait
quasiment rien :

1. Le module Domain de base trouve une correspondance exacte de nom d'hôte et
   définit le type de correspondance à `DOMAIN_MATCHED_EXACT`.
2. `hook_domain_request_alter()` se déclenche. Domain Alias vérifie le type
   de correspondance, voit `DOMAIN_MATCHED_EXACT`, et **retourne
   immédiatement** -- aucune recherche d'alias n'est effectuée.

**Coût :** une comparaison avec la constante de type de correspondance.
Négligeable.

### Quand le nom d'hôte ne correspond à aucun enregistrement de domaine

Lorsque le nom d'hôte de la requête ne correspond à aucun enregistrement de
domaine (par ex. un nom d'hôte de développement ou de staging), Domain Alias
effectue les opérations suivantes :

1. **Génération de motifs** -- le nom d'hôte est découpé en segments et
   toutes les combinaisons wildcard possibles sont générées (par ex.
   `dev.example.com` produit `*.example.com`, `dev.*.com`,
   `dev.example.*`, etc.). Les variantes de port sont ajoutées si
   applicable. Il s'agit de pure manipulation de chaînes sur un petit
   tableau (typiquement 3-4 segments).

2. **Recherche de motifs** -- chaque motif généré est vérifié dans le
   stockage d'entités de configuration des alias via `loadByProperties()`.
   Les entités de configuration sont chargées depuis le cache de
   configuration de Drupal (en mémoire après la première lecture dans une
   requête), **pas** depuis la base de données. La recherche s'arrête à la
   première correspondance, donc dans le meilleur cas, seulement une ou deux
   requêtes contre le cache en mémoire sont nécessaires.

3. **Chargement du domaine** -- le domaine parent de l'alias correspondant
   est chargé par identifiant. Le stockage d'entités de configuration
   utilise un cache statique, donc si le domaine a déjà été chargé plus tôt
   dans la requête, c'est une opération nulle.

4. **Désambiguïsation par préfixe de chemin** -- si le nom d'hôte résolu est
   partagé par plusieurs domaines avec des préfixes de chemin différents, le
   négociateur trie les candidats (typiquement 2-5 entrées) par longueur de
   préfixe et effectue une vérification `str_starts_with()` par candidat.
   Pas de requêtes de stockage supplémentaires.

5. **Réécriture d'environnement** (environnements non par défaut uniquement)
   -- lorsque l'alias correspondant appartient à un environnement non par
   défaut (par ex. `local`), toutes les entités de domaine voient leurs noms
   d'hôte réécrits au chargement via `hook_domain_load()`. Les domaines qui
   partagent le même nom d'hôte canonique que le domaine actif (c'est-à-dire
   ne différant que par le préfixe de chemin) sont réécrits directement sans
   aucune recherche d'alias. Les autres domaines nécessitent le chargement
   des alias par domaine et par environnement ainsi que la résolution des
   motifs wildcard. Les résultats sont mis en cache en mémoire pour la durée
   de la requête, donc les chargements répétés du même domaine ne
   déclenchent pas de recherches supplémentaires.


### Caractéristiques de performance

- **Pas de requêtes base de données** -- toutes les recherches d'alias et
  de domaine passent par le stockage d'entités de configuration, qui lit
  depuis le cache de configuration de Drupal (rempli une fois par requête
  depuis la base de données ou APCu/Redis si un backend de cache est
  configuré).
- **Pas d'appels HTTP externes** -- la résolution d'alias est entièrement
  locale.
- **Pas de cache context supplémentaire** -- Domain Alias n'ajoute pas de
  cache contexts au-delà de ce que le module Domain de base fournit déjà
  (`domain`).

### Considérations de montée en charge

Le nombre d'entités d'alias affecte la taille du cache de configuration mais
pas le coût par requête, car `loadByProperties()` filtre en mémoire. Les
sites avec des centaines d'alias ne devraient voir aucune différence mesurable
par rapport aux sites avec quelques alias.

Le facteur principal est le nombre de **domaines** (pas d'alias). La
réécriture d'environnement dans `hook_domain_load()` s'exécute une fois par
entité de domaine chargée par requête. Pour la plupart des sites (moins de 20
domaines), cela est négligeable. Les sites avec un très grand nombre de
domaines devraient surveiller l'impact de la réécriture d'environnement et
déterminer si tous les domaines nécessitent des alias d'environnement.

## Configuration

- Ajoutez des alias à l'adresse `/admin/config/domain/alias/{domain}`.
- Tous les noms d'hôte d'alias doivent être listés dans
  `trusted_host_patterns` dans `settings.php`.
- Surchargez la liste des environnements dans `settings.php` si nécessaire :

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
