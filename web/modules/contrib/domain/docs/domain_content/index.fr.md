# Domain Content

Le module Domain Content fournit des pages de vue d'ensemble du contenu et
des éditeurs par domaine, afin que les administrateurs puissent consulter et
gérer les entités assignées à des domaines spécifiques.

## Pages de vue d'ensemble

Le module ajoute deux pages sous `/admin/content/` :

| Page | Chemin | Permission |
|------|--------|------------|
| **Contenu affilié** | `/admin/content/domain-content` | `access domain content` |
| **Éditeurs affiliés** | `/admin/content/domain-editors` | `access domain content editors` |

Chaque page affiche un tableau listant chaque domaine avec un compteur de
contenu ou d'éditeurs. Cliquer sur un nom de domaine ouvre une liste
détaillée alimentée par Views.

Les utilisateurs disposant de la permission *publish to any domain* (ou
*assign editors to any domain*) voient également une ligne **All affiliates**
qui renvoie aux entités marquées comme visibles sur tous les domaines.

## Permissions

| Permission | Description |
|------------|-------------|
| `access domain content` | Voir la vue d'ensemble du contenu affilié. |
| `access domain content editors` | Voir la vue d'ensemble des éditeurs affiliés. |

## Views

Domain Content fournit deux Views optionnelles :

### affiliated_content

Affiche les nœuds assignés à un domaine donné.

- **Page 1** — `/admin/content/domain-content/{domain_id}` : nœuds sur un
  domaine spécifique avec des filtres exposés pour le statut, le type de
  contenu, le titre et la langue.
- **Page 2** — `/admin/content/domain-content/all_affiliates` : nœuds
  marqués comme *all affiliates*, avec un filtre exposé supplémentaire par
  domaine.

Les deux pages affichent les opérations en masse, le titre, le type de
contenu, l'auteur, les domaines assignés, l'indicateur all-affiliates, le
statut, la date de mise à jour et les opérations.

### affiliated_editors

Affiche les utilisateurs assignés à un domaine donné.

- **Page 1** — `/admin/content/domain-editors/{domain_id}` : éditeurs sur
  un domaine spécifique avec des filtres exposés pour le statut, le domaine,
  le nom d'utilisateur, l'email et la langue.
- **Page 2** — `/admin/content/domain-editors/all_affiliates` : éditeurs
  marqués comme *all affiliates*.

Les deux pages affichent les opérations en masse, le nom d'utilisateur,
l'email, les domaines assignés, l'indicateur all-affiliates, le statut, la
date de création et les opérations.

Les quatre affichages utilisent un paginateur de 50 éléments.

## Plugins d'accès Views

Le module fournit deux plugins d'accès Views qui étendent Domain Access :

| Plugin | ID | Condition requise |
|--------|----|-------------------|
| `DomainContentAccess` | `domain_content_editor` | `access domain content` + assignation au domaine |
| `DomainEditorAccess` | `domain_content_admin` | `access domain content editors` + assignation au domaine |

## Opérations de domaine

Domain Content ajoute des liens d'opération **Content** et **Editors** à la
liste des domaines sur `/admin/config/domain`. Ces liens sont affichés de
manière conditionnelle en fonction des permissions et des assignations de
domaine de l'utilisateur courant.

## Vérification des prérequis

À l'exécution, le module vérifie que `field_domain_access` et
`field_domain_all_affiliates` existent sur tous les types de nœuds et sur
l'entité utilisateur. Une erreur de statut est signalée sur
`/admin/reports/status` si un champ est manquant.
