# Domain Source

Le module Domain Source permet d'assigner un domaine canonique au contenu
lors de la génération des URL. Domain Source garantit que le contenu
apparaissant sur plusieurs domaines renvoie toujours vers une seule URL.

## Fonctionnement

Lorsqu'une entité de contenu possède une valeur `field_domain_source`
différente du domaine actif, `DomainSourcePathProcessor` (priorité 310)
définit `$options['domain']` sur l'URL et délègue la réécriture effective de
l'URL à `DomainPathProcessor` (priorité 80) dans le module Domain principal.

Cela signifie que toutes les fonctionnalités d'URL inter-domaines fournies
par le module Domain (négociation de langue, gestion du paramètre de
destination) s'appliquent automatiquement aux réécritures de Domain Source.

## Token

Domain Source fournit un token `[node:canonical-source-domain-url]` qui
renvoie l'URL canonique absolue d'un nœud sur son domaine source.

Si aucun domaine source n'est assigné au nœud, le token se rabat sur l'URL
canonique par défaut.

### Utilisation avec le module Metatag

Le module Metatag remplace la balise `<link rel="canonical">` du cœur de
Drupal par la sienne, résolue à partir d'un token configurable (généralement
`[current-page:url]`). Comme Metatag résout son URL canonique via le système
de tokens plutôt que via `$entity->toUrl()`, **`DomainSourcePathProcessor`
ne l'intercepte pas** — la balise canonique peut pointer vers le domaine
courant au lieu du domaine source, même lorsque la route canonique n'est pas
exclue.

Pour corriger cela, définissez le champ **Canonical URL** dans la
configuration Metatag (`/admin/config/search/metatag`) sur
`[node:canonical-source-domain-url]` pour les types de contenu de type nœud.
Cela garantit que la balise `<link rel="canonical">` pointe toujours vers
l'URL du domaine source.

### Utilisation avec les routes canoniques exclues

Ce token est également utile lorsque la route `canonical` est ajoutée au
paramètre **Excluded entity route suffixes**
(`/admin/config/domain/domain_source`). Lorsque la route canonique est
exclue, `DomainSourcePathProcessor` ne réécrit plus les URL canoniques vers
le domaine source — ce qui réduit les changements de liens inter-domaines
dans le contenu rendu. Le token vous permet néanmoins de générer l'URL
correcte du domaine source là où c'est nécessaire, par exemple dans les
sitemaps XML ou les notifications par email.

## Fonctionnalités inter-domaines

Les fonctionnalités suivantes s'appliquent à toutes les réécritures d'URL
inter-domaines, y compris celles déclenchées par Domain Source. Elles sont
configurées dans les paramètres du module Domain
(`/admin/config/domain/settings`) sous **Experimental features** :

- **Language negotiation for cross-domain URLs** — garantit que les URL
  sortantes utilisent les paramètres de négociation de langue de leur domaine
  cible.
- **Domain-scoped destination redirects** — garantit que les utilisateurs
  sont redirigés vers le bon domaine après avoir effectué une action sur un
  domaine différent.

Consultez la [documentation du module Domain](../domain/index.md) pour tous
les détails sur ces fonctionnalités.

## Issues associées

- [#3570178: Language negotiation for cross-domain URLs](https://www.drupal.org/i/3570178)
- [#3570210: Domain-scoped destination redirects](https://www.drupal.org/i/3570210)
- [#3574800: Allow Url objects to specify the Domain as an option](https://www.drupal.org/i/3574800)
