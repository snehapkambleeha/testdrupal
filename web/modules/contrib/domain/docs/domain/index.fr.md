# Domain

Le module Domain est le noyau de la suite de modules Domain. Il fournit la
gestion des entités de domaine, la négociation de domaine et la réécriture
d'URL inter-domaines.

## Réécriture d'URL inter-domaines

Tout code peut cibler un domaine spécifique lors de la construction d'une URL
en définissant l'option `domain` sur un objet `Url`. Le `DomainPathProcessor`
(outbound path processor, priorité 80) réécrit alors l'URL pour pointer vers
ce domaine.

### Utilisation

L'option `domain` requiert un objet entité `DomainInterface`, de la même
manière que l'option `language` du core requiert une `LanguageInterface` :

```php
use Drupal\Core\Url;

$domain = \Drupal::entityTypeManager()->getStorage('domain')->load('one_example_com');
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
$url->setOption('domain', $domain);
```

Cela produit une URL absolue pointant vers le domaine cible, par exemple
`http://one.example.com/node/1`.

### Fonctionnement

Lorsque `DomainPathProcessor::processOutbound()` rencontre une option
`domain`, il :

1. **Valide le domaine** -- vérifie que l'option est une entité
   `DomainInterface`.
2. **Applique les fonctionnalités inter-domaines** (négociation de langue,
   paramètre de destination -- voir ci-dessous).
3. **Réécrit l'URL** en définissant `base_url` sur le chemin du domaine cible
   et en forçant `absolute = TRUE`.
4. **Ajoute des métadonnées de cache** -- l'entité de domaine comme dépendance
   cacheable (pour l'invalidation lorsque le domaine change), et le cache
   context `domain` lorsque la fonctionnalité de paramètre de destination est
   active.

### Intégration avec Domain Source

Le module Domain Source utilise ce mécanisme en interne. Lorsqu'une entité de
contenu possède un domaine source différent du domaine actif,
`DomainSourcePathProcessor` (priorité 310) définit
`$options['domain'] = $source` et laisse `DomainPathProcessor` gérer la
réécriture effective de l'URL.

Cela signifie que toutes les fonctionnalités inter-domaines décrites
ci-dessous s'appliquent automatiquement aux réécritures de Domain Source
ainsi qu'à tout code personnalisé qui définit l'option `domain`.

## Fonctionnalités expérimentales

Les fonctionnalités suivantes sont disponibles sous **Experimental features**
sur la page de configuration de Domain (`/admin/config/domain/settings`).
Toutes sont désactivées par défaut.

### Support du préfixe de chemin

Le module Domain prend en charge un **préfixe de chemin** optionnel sur les
enregistrements de domaine, permettant à plusieurs domaines de partager un même
nom d'hôte tout en étant distingués par le premier segment du chemin de l'URL
(par exemple, `example.com/fr/...` vs `example.com/benl/...`).

Cochez **"Enable path prefix support"** dans les paramètres de Domain pour
activer cette fonctionnalité. Lorsqu'elle est désactivée, tous les composants
liés au préfixe de chemin sont retirés du conteneur pour un surcoût nul à
l'exécution.

Consultez la [documentation du préfixe de chemin](path_prefix.md) pour tous
les détails.

- Config key : `domain.settings:path_prefix`
- Issue associée : [#3575947](https://www.drupal.org/i/3575947)

### Négociation de langue pour les URL inter-domaines

Lorsqu'un site utilise plusieurs domaines avec des paramètres de négociation
de langue différents (par exemple, un domaine utilise des préfixes de chemin
comme `/fr/...` tandis qu'un autre utilise une négociation basée sur le
domaine), les URL outbound doivent être traitées en utilisant les paramètres
de négociation de langue du domaine *cible*, et non du domaine courant.

Cochez **"Enable language negotiation for cross-domain URLs"** dans les
paramètres de Domain pour activer cette fonctionnalité.

Lorsqu'elle est activée, `DomainPathProcessor` :

1. Compare la configuration URL de `language.negotiation` entre le domaine
   actif et le domaine cible (en utilisant les surcharges Domain Config).
2. Si les configurations diffèrent, relance le processeur outbound
   `LanguageNegotiationUrl` dans le contexte du domaine cible.
3. Cela garantit que les préfixes de chemin et les autres méthodes de
   négociation basées sur l'URL sont correctement appliqués pour le domaine
   cible.

!!! note
    Cela déclenche une passe de négociation de langue supplémentaire
    uniquement pour les URL dont le domaine cible possède une configuration
    de négociation de langue différente. Les URL restant sur le même domaine
    ou ciblant un domaine avec une configuration identique ne sont pas
    affectées.

- Config key : `domain.settings:language_negotiation`
- Issue associée : [#3570178](https://www.drupal.org/i/3570178)

### Redirections de destination à portée de domaine

Lorsqu'un utilisateur suit un lien inter-domaines qui inclut un paramètre de
requête `destination` (par exemple, pour se connecter ou modifier du contenu
sur un autre domaine), le chemin relatif standard de `destination` le
redirigerait vers le domaine *cible* plutôt que vers le domaine *d'origine*.

Cochez **"Allow domain-scoped destination redirects"** dans les paramètres
de Domain pour activer cette fonctionnalité.

Lorsqu'elle est activée, `DomainPathProcessor` :

1. Détecte les liens inter-domaines qui incluent un paramètre de requête
   `destination` correspondant au chemin de la requête courante.
2. Ajoute un paramètre de requête `destination_domain` contenant l'URL de
   base du domaine courant (scheme + host).
3. Sur le domaine cible, l'event subscriber `DomainSubscriber` reconstruit
   une URL `destination` absolue à partir des deux paramètres, garantissant
   que l'utilisateur est redirigé vers la bonne page sur le domaine
   d'origine.

**Exemple de flux :**

1. L'utilisateur est sur `http://example.com/admin/content`.
2. Il clique sur un lien d'édition réécrit vers
   `http://one.example.com/node/1/edit`.
3. Avec cette fonctionnalité activée, le lien devient :
   `http://one.example.com/node/1/edit?destination=/admin/content&destination_domain=http://example.com`
4. Après l'enregistrement, l'utilisateur est redirigé vers
   `http://example.com/admin/content`.

- Config key : `domain.settings:allow_destination_domain`
- Issue associée : [#3570210](https://www.drupal.org/i/3570210)

### Négociation de domaine anticipée

Si des middlewares tiers ont besoin des surcharges domain_config avant
l'événement de requête du kernel, installez le module **Domain Early
Negotiation** (`domain_early_negotiation`) depuis le projet
[Domain Extras](https://www.drupal.org/project/domain_extras). Il fournit un
`DomainNegotiationMiddleware` qui négocie le domaine actif tôt dans la pile de
middlewares. L'activation du module active la fonctionnalité ; la priorité du
middleware est configurable à `/admin/config/domain/early-negotiation`.

## Commandes Drush

Le module Domain fournit des commandes Drush pour gérer les enregistrements
de domaine depuis la ligne de commande.

### domain:list

Liste tous les enregistrements de domaine avec leur statut et leur réponse
HTTP.

```bash
drush domain:list
drush domain:list --inactive
drush domain:list --active
```

Aliases : `domains`, `domain-list`

```
 Machine name          Name      Hostname     Path prefix  Scheme  Status  Default  Response
 example_com           Default   example.com               https   Active  Default  200 - OK
 example_com_fr        French    example.com  fr           https   Active           200 - OK
 shop_example_com      Shop      shop.com                  https   Active           200 - OK
```

### domain:info

Affiche des informations générales sur les domaines du site.

```bash
drush domain:info
```

Aliases : `domain-info`, `dinf`

```
 All Domains              3
 Active Domains           3
 Default Domain ID        example_com
 Default Domain hostname  example.com
 Fields in Domain entity  id, domain_id, hostname, path_prefix, name, ...
 Domain admin entities    node, user
```

### domain:add

Crée un nouvel enregistrement de domaine.

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

Aliases : `domain-add`

Options :

| Option | Description |
|--------|-------------|
| `--scheme` | `http`, `https` ou `variable`. Par défaut : `http`. |
| `--weight` | Ordre de tri du domaine. |
| `--inactive` | Crée le domaine comme inactif. |
| `--is_default` | Définit comme domaine par défaut. |
| `--validate` | Vérifie la réponse URL avant l'enregistrement. |
| `--path-prefix` | Préfixe de chemin pour le partage de nom d'hôte (voir [Path prefix](path_prefix.md)). |

### domain:delete

Supprime un enregistrement de domaine et réassigne optionnellement son contenu
et ses utilisateurs.

```bash
drush domain:delete example.com
drush domain:delete example.com --content-assign=ignore
drush domain:delete example.com --users-assign=example_net
drush domain:delete all
drush domain:delete example.com --dryrun
```

Aliases : `domain-delete`

Le domaine par défaut ne peut pas être supprimé. Utilisez `domain:default`
pour définir un nouveau domaine par défaut au préalable. Lors de la
suppression, vous êtes invité à réassigner les utilisateurs à un autre domaine
sauf si `--users-assign` est spécifié.

### domain:default

Définit un domaine comme domaine par défaut.

```bash
drush domain:default example.com
drush domain:default example_org --validate
```

```
example_com set to primary domain.
```

Aliases : `domain-default`

### domain:enable / domain:disable

Active ou désactive un domaine.

```bash
drush domain:enable example.com
drush domain:disable example.com
```

```
example.com has been disabled.
```

Aliases : `domain-enable`, `domain-disable`

### domain:name

Change le libellé d'un domaine.

```bash
drush domain:name example.com 'New Name'
```

```
Renamed example.com to New Name.
```

Aliases : `domain-name`

### domain:scheme

Change le schéma d'URL d'un domaine.

```bash
drush domain:scheme example.com https
```

```
Scheme is now to "https." for example_com
```

Aliases : `domain-scheme`

Sans argument de schéma, propose une sélection.

### domain:test

Teste les domaines pour vérifier la bonne réponse HTTP.

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

Aliases : `domain-test`

### domain:replace

Remplace une chaîne dans tous les noms d'hôte de domaine. Effectue un dry run
par défaut ; utilisez `--force` pour appliquer les modifications.

```bash
drush domain:replace "old.com" "new.com"
drush domain:replace "old.com" "new.com" --force
```

```
 Name     Current            New
 Default  example.old.com    example.new.com
 Shop     shop.old.com       shop.new.com
```

Aliases : `domain-replace`

### domain:generate

Génère des domaines de test pour le développement. Crée des sous-domaines du
nom d'hôte principal donné.

```bash
drush domain:generate example.com
drush domain:generate example.com --count=25
drush domain:generate example.com --count=25 --empty
drush domain:generate example.com --scheme=https
```

Aliases : `gend`, `domgen`, `domain-generate`

Options :

| Option | Description |
|--------|-------------|
| `--count` | Nombre de domaines à générer. Par défaut : 15. |
| `--empty` | Supprime tous les domaines avant la génération. |
| `--scheme` | `http`, `https` ou `variable`. |

### Résolution de l'identifiant de domaine

Toutes les commandes qui acceptent un argument `domain_id` le résolvent dans
l'ordre suivant :

1. Machine name (par exemple, `example_com`)
2. Nom d'hôte (par exemple, `example.com`)

## Issues associées

- [#3574800: Allow Url objects to specify the Domain as an option](https://www.drupal.org/i/3574800)
