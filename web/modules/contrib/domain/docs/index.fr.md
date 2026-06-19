# Présentation

La suite de modules Domain permet de partager les utilisateurs, le contenu et
la configuration entre un groupe de domaines depuis une seule installation et
une seule base de données.

Pour une description complète du module, consultez la
[page du projet](https://www.drupal.org/project/domain).

Soumettez des rapports de bogues et des suggestions de fonctionnalités, ou
suivez les changements dans la
[file d'attente des problèmes](https://www.drupal.org/project/issues/domain).

## Prérequis

Ce module ne nécessite aucun module en dehors du cœur de Drupal.

La version 3.x nécessite Drupal 10.2 ou supérieur et est compatible avec
Drupal 11.

## Installation

Installez comme vous installeriez normalement un module Drupal contribué. Pour
plus d'informations, consultez
[Installer des modules Drupal](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Modules inclus

- **Domain**
  Le module principal. Domain permet d'enregistrer plusieurs domaines au sein
  d'une seule installation Drupal. Il permet d'assigner des utilisateurs comme
  administrateurs de domaine et fournit un contexte d'affichage pour les blocs
  et les vues. Les domaines peuvent également partager un nom d'hôte avec
  différents [préfixes de chemin](domain/path_prefix.md) pour une séparation
  par chemin.

- **Domain Access**
  Fournit des contrôles d'accès aux nœuds basés sur les domaines. Il permet
  d'assigner des utilisateurs comme éditeurs de contenu par domaine, définit
  des règles de visibilité du contenu et fournit une intégration Views pour
  le contenu. Consultez la
  [documentation de Domain Access](domain_access/index.md) pour plus
  d'informations.

- **Domain Alias**
  Permet de faire pointer plusieurs noms d'hôte vers un seul domaine
  enregistré. Ces alias peuvent inclure des wildcards (comme
  *.example.com) et être configurés pour rediriger vers leur domaine
  canonique. Domain Alias permet également d'enregistrer des alias par
  `environment`, afin que différents hôtes soient utilisés de manière
  cohérente entre les environnements de développement. Consultez la
  [documentation de Domain Alias](domain_alias/index.md) pour plus
  d'informations.

- **Domain Config**
  Permet de modifier les paramètres de configuration par domaine. Consultez
  la [documentation de Domain Config](domain_config/index.md) pour plus
  d'informations.

- **Domain Content**
  Fournit des pages de vue d'ensemble du contenu par domaine, afin que les
  éditeurs puissent consulter le contenu assigné à des domaines spécifiques.
  Consultez la [documentation de Domain Content](domain_content/index.md)
  pour plus d'informations.

- **Domain Source**
  Permet d'assigner un domaine canonique au contenu pour la génération
  d'URL. Domain Source garantit que le contenu apparaissant sur plusieurs
  domaines renvoie toujours vers une seule URL. Consultez la
  [documentation de Domain Source](domain_source/index.md) pour plus
  d'informations.

## Notes d'implémentation

### Connexion inter-domaines

Pour utiliser la connexion inter-domaines, vous devez définir la valeur
**cookie_domain** dans **sites/default/services.yml**.

Pour ce faire, clonez `default.services.yml` en `services.yml` et modifiez
la valeur `cookie_domain` pour qu'elle corresponde au nom d'hôte racine de
vos sites. Notez que la connexion inter-domaines nécessite le partage d'un
domaine de premier niveau, donc un paramètre comme `.example.com`
fonctionnera pour tous les sous-domaines de `example.com`.

Exemple :

```
cookie_domain: '.example.com'
```

Voir [drupal.org/node/2391871](https://www.drupal.org/node/2391871).

### Requêtes HTTP inter-sites (CORS)

Drupal permet à un site d'activer CORS pour les réponses servies par Drupal.

Dans le cas de Domain, autoriser CORS peut supprimer les erreurs AJAX
causées lors de l'utilisation de certains formulaires, notamment les
références d'entité, lorsque la requête AJAX est dirigée vers un autre
domaine.

Cette fonctionnalité n'est pas activée par défaut car elle a des
conséquences en termes de sécurité. Voir
[drupal.org/node/2715637](https://www.drupal.org/node/2715637) pour plus
d'informations et d'instructions.

Pour activer CORS pour tous les domaines, copiez `default.services.yml` en
`services.yml` et activez les lignes suivantes :

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

### Paramètres de trusted host

Si vous utilisez le paramètre de sécurité trusted host, assurez-vous
d'ajouter chaque domaine et alias à la liste de patterns. Par exemple :

``` php
$settings['trusted_host_patterns'] = [
  '^.+\.example\.org$',
  '^myexample\.com$',
  '^myexample\.dev$',
  '^localhost$',
];
```

Nous **recommandons fortement** l'utilisation des paramètres trusted host.
Lorsque Domain émet une redirection, il vérifie le nom d'hôte du domaine
par rapport à ces paramètres. Toute redirection ne correspondant pas aux
paramètres trusted host sera refusée et lèvera une exception.

Voir [drupal.org/node/1992030](https://www.drupal.org/node/1992030) pour
plus d'informations.

### Configuration des enregistrements de domaine

Pour créer un enregistrement de domaine, vous devez fournir les
informations suivantes :

- Un **hostname** unique, qui peut inclure un port. (Ainsi, example.com et
  example.com:8080 sont considérés comme différents.) Le nom d'hôte ne peut
  contenir que des caractères alphanumériques, des tirets, des points et un
  deux-points. Si vous souhaitez utiliser des noms de domaine
  internationaux, activez le paramètre `Allow non-ASCII characters in
  domains and aliases.`.
- Un **machine_name** qui doit être unique. Cette valeur est générée
  automatiquement et ne peut pas être modifiée après la création.
- Un **name** à utiliser dans les listes de domaines.
- Un schéma d'URL, utilisé pour la génération des liens vers le domaine. Le
  schéma peut être `http`, `https` ou `variable`. Si `variable` est
  utilisé, le schéma sera hérité du serveur ou des paramètres de la
  requête. Cette option est utile si vos environnements de test n'ont pas
  de certificats sécurisés mais que votre environnement de production en a.
- Un **status** indiquant `active` ou `inactive`. Les domaines inactifs ne
  peuvent être consultés que par les utilisateurs ayant la permission
  `view inactive domains` ; tous les autres utilisateurs seront redirigés
  vers le domaine par défaut (voir ci-dessous).
- Le **weight** utilisé pour le tri des domaines. Ces valeurs s'incrémentent
  automatiquement à la création de nouveaux domaines.
- Si le domaine est le domaine **default** ou non. Un seul domaine peut être
  défini comme `default`. Le domaine par défaut est utilisé pour les
  redirections lorsque d'autres domaines sont restreints (inactifs) ou ne se
  chargent pas. Cette valeur peut être réassignée après la création des
  domaines.
- Un **path prefix** optionnel qui permet à plusieurs domaines de partager
  le même nom d'hôte tout en étant distingués par le premier segment du
  chemin de l'URL. Consultez la
  [documentation du préfixe de chemin](domain/path_prefix.md).

Les enregistrements de domaine sont des **entités de configuration**, ce qui
signifie qu'ils ne sont pas stockés dans la base de données ni accessibles à
Views par défaut. Ils sont cependant exportables dans le cadre de votre
configuration.

### Règles de validation

Les enregistrements de domaine sont validés via des plugins de contrainte
Symfony déclarés dans le schéma de configuration (`domain.schema.yml`). Ces
contraintes s'exécutent automatiquement lors de l'enregistrement via le
formulaire d'administration ou les commandes Drush.

**Hostname** (contraintes `DomainHostname` + `DomainUniqueHostname`) :

1. Au moins un point requis (sauf `localhost`).
2. Un seul deux-points (`:`) pour la spécification du port.
3. Après un deux-points, seul un entier est autorisé.
4. Pas de points en début ou fin de chaîne.
5. Caractères ASCII uniquement (sauf si `domain.settings:allow_non_ascii`
   est activé).
6. Minuscules uniquement.
7. Pas de préfixe `www.` lorsque le paramètre `Ignore www prefix` est
   activé.
8. La combinaison du nom d'hôte et du préfixe de chemin doit être unique.
   Deux domaines peuvent partager le même nom d'hôte uniquement si leurs
   préfixes de chemin diffèrent.

**Scheme** (contrainte `Choice`) :

Doit être `http`, `https` ou `variable`.

**Domain ID** (contrainte `Range`) :

Doit être un entier non négatif (>= 0). La valeur est assignée
automatiquement dans `preSave()` et ne doit pas être définie manuellement.

**Extensibilité** :

Les modules peuvent ajouter des règles de validation de nom d'hôte
personnalisées en implémentant
`hook_domain_validate_alter(&$error_list, $hostname)`. Toute chaîne ajoutée
à `$error_list` apparaîtra comme une violation de contrainte.

### Domaines et cache

Si certains changements de variables ne sont pas pris en compte lors du
rendu de la page, vous devrez peut-être ajouter la sensibilité au domaine
dans le cache du site.

Pour ce faire, clonez `default.services.yml` en `services.yml` (si ce n'est
pas déjà fait) et modifiez la valeur `required_cache_contexts` :

``` yaml
required_cache_contexts: [ 'languages:language_interface', 'theme', 'user.permissions', 'domain' ]
```

L'ajout de `domain` devrait fournir le contexte de domaine dont la couche
de cache a besoin.

Lorsque vous utilisez le module Domain Access, gardez à l'esprit que vous
devrez peut-être également reconstruire les permissions
(`/admin/reports/status/rebuild`) après des changements de configuration.

Pour les développeurs, consultez également la
[documentation de Domain Alias](domain_alias/index.md).

### Contribuer

Pour Drupal 10+, vous pouvez utiliser le projet
[Domain DDEV](https://github.com/agentrickard/domain-ddev) pour démarrer
rapidement. Il inclut tous les outils décrits ci-dessous.

Si vous soumettez une merge request, exécutez les tests existants pour
vérifier l'absence de régressions. La rédaction de tests supplémentaires
accélérera grandement le processus, car le code n'est pas fusionné sans
couverture de tests.

Les nouveaux tests doivent être écrits en PHPUnit sous forme de tests
Functional, FunctionalJavascript, Kernel ou Unit.

Pour configurer un environnement local, vous avez besoin de domaines
multiples ou wildcard pointant vers votre instance Drupal. Nous utilisons
des variantes de `example.local` pour les tests locaux. Consultez
`DomainTestBase` pour la documentation. Les tests Domain devraient
fonctionner avec des hôtes racines autres que `example.com`, bien que nous
nous attendions également à trouver les sous-domaines `one.*, two.*,
three.*, four.*, five.*` dans la plupart des cas de test. Consultez
`DomainTestBase::domainCreateTestDomains()` pour la logique.

Lors de l'exécution des tests, vous devez normalement être sur le domaine
par défaut.

### Linting du code

Nous utilisons (et recommandons)
[PHPCBF](https://phpqa.io/projects/phpcbf.html),
[PHP Codesniffer](https://github.com/squizlabs/PHP_CodeSniffer) et
[phpstan](https://phpstan.org/) pour la revue de qualité du code.

Les commandes suivantes sont exécutées avant chaque commit :

- `vendor/bin/phpcbf web/modules/contrib/domain --standard="Drupal,DrupalPractice" -n --extensions="php,module,inc,install,test,profile,theme"`
- `vendor/bin/phpcs web/modules/contrib/domain --standard="Drupal,DrupalPractice" -n --extensions="php,module,inc,install,test,profile,theme"`
- `vendor/bin/phpstan analyse web/modules/contrib/domain`

### Configuration phpstan

Nous utilisons le fichier `phpstan.neon` suivant :

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

Le drupal entityMapping est également fourni par `entity_mapping.neon` à la
racine du projet, pour une utilisation avec d'autres tests.

## Mainteneurs

- Ken Rickard - [agentrickard](https://www.drupal.org/u/agentrickard)
