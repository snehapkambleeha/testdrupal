# Domain Access

Le module Domain Access fournit un contrôle d'accès aux nœuds basé sur
l'affectation aux domaines. Il permet de publier du contenu sur un ou
plusieurs domaines, de restreindre l'édition par domaine et d'assigner des
utilisateurs comme éditeurs par domaine.

## Champs

Domain Access crée automatiquement deux champs sur chaque type de contenu et
sur l'entité utilisateur :

| Champ | Type | Description |
|-------|------|-------------|
| `field_domain_access` | Référence d'entité (domain) | Affecte l'entité à un ou plusieurs domaines. Obligatoire sur les nœuds, optionnel sur les utilisateurs. |
| `field_domain_all_affiliates` | Booléen | Lorsque coché, l'entité est visible sur (ou l'utilisateur peut modifier sur) **tous** les domaines. |

Un paramètre tiers sur `field_domain_access` contrôle si les nouvelles
entités reçoivent automatiquement le domaine courant comme valeur par défaut.

## Système de contrôle d'accès aux nœuds

Domain Access implémente le système de node access grants de Drupal pour
contrôler la visibilité et l'édition.

### Grant realms

| Realm | Portée |
|-------|--------|
| `domain_id` | Contenu publié sur un domaine spécifique |
| `domain_unpublished` | Contenu non publié sur un domaine spécifique |
| `domain_site` | Contenu publié affecté à tous les affiliés (consultation uniquement) |

Lorsque le paramètre expérimental **per-bundle grants** est activé, des
realms supplémentaires comme `domain_id:{bundle}` et
`domain_unpublished:{bundle}` sont utilisés pour un contrôle plus fin.

### Comment les grants sont attribués

**Accès en consultation :**

- Tous les visiteurs reçoivent un grant pour le domaine actif (`domain_id`)
  et le realm global (`domain_site`).
- Les utilisateurs disposant de la permission *view unpublished domain
  content* et d'un accès au domaine reçoivent également le grant
  `domain_unpublished`.

**Accès en modification et suppression :**

- Nécessite la permission *edit domain content* ou *delete domain content*.
- L'utilisateur doit être affecté au domaine (ou avoir *all affiliates*
  coché).
- Avec les per-bundle grants, des permissions par bundle comme *update
  {bundle} content on assigned domains* sont vérifiées à la place.

!!! note
    Après avoir modifié les paramètres d'accès, reconstruisez les permissions
    à `/admin/reports/status/rebuild`.

## Permissions

### Publication de contenu

| Permission | Description |
|------------|-------------|
| `publish to any domain` | Publier du contenu sur tous les domaines et modifier le champ *all affiliates*. |
| `publish to any assigned domain` | Publier du contenu sur les domaines assignés à l'utilisateur. |
| `create domain content` | Créer du contenu sur les domaines assignés. |
| `create {bundle} content on assigned domains` | Contrôle de création par bundle. |
| `edit domain content` | Modifier du contenu sur les domaines assignés. |
| `update {bundle} content on assigned domains` | Contrôle de modification par bundle. |
| `delete domain content` | Supprimer du contenu sur les domaines assignés. |
| `delete {bundle} content on assigned domains` | Contrôle de suppression par bundle. |
| `view unpublished domain content` | Voir le contenu non publié sur les domaines assignés. |

### Assignation des éditeurs

| Permission | Description |
|------------|-------------|
| `assign domain editors` | Assigner des éditeurs aux domaines assignés à l'utilisateur. |
| `assign editors to any domain` | Assigner des éditeurs à n'importe quel domaine. |

## Paramètres

Le formulaire de paramètres se trouve à `/admin/config/domain/domain_access`.

| Paramètre | Description |
|-----------|-------------|
| **Move domain fields to advanced tab** | Place les champs de domaine dans la barre latérale avancée du formulaire de nœud. |
| **Keep advanced tab open** | Ouvre l'onglet avancé par défaut. |
| **Allow field removal** (expérimental) | Permet de supprimer les champs Domain Access de types d'entités spécifiques. Nécessite une reconstruction des permissions. |
| **Per-bundle grants** (expérimental) | Active les node access grants par bundle pour un contrôle plus fin. |

## Intégration Views

Domain Access enregistre plusieurs plugins Views :

| Type de plugin | ID | Description |
|----------------|----|-------------|
| Field | `domain_access_field` | Affiche les domaines assignés sous forme de liens vers l'entité sur chaque domaine. |
| Filter | `domain_access_filter` | Filtre par affectation de domaine (ManyToOne, supporte OR/NOT). |
| Filter | `domain_access_current_all_filter` | Filtre le contenu disponible sur le domaine courant ou marqué comme *all affiliates*. |
| Argument | `domain_access_argument` | Accepte un identifiant de domaine comme filtre contextuel. |
| Access | `domain_access_editor` | Restreint un affichage Views aux utilisateurs pouvant modifier le contenu sur leurs domaines. |
| Access | `domain_access_admin` | Restreint un affichage Views aux utilisateurs pouvant gérer les éditeurs sur leurs domaines. |

## Bulk actions

Lorsqu'un nouveau domaine est créé, Domain Access enregistre automatiquement
quatre configurations de bulk actions :

- **Add content to {domain}** / **Remove content from {domain}**
- **Add editors to {domain}** / **Remove editors from {domain}**

Quatre actions globales supplémentaires sont toujours disponibles :

- **Assign to all affiliates** / **Remove from all affiliates** (pour le
  contenu et les éditeurs).

Ces actions sont supprimées lorsque le domaine correspondant est supprimé.

## Plugin de condition

Le plugin de condition `domain_access` évalue si un nœud est affecté à un ou
plusieurs domaines sélectionnés. Il peut être utilisé dans Rules, Block
Visibility et des systèmes similaires. La condition supporte la négation.

## Hooks

### Alter hooks

`hook_domain_references_alter(&$query, $account, $context)` — filtre la
liste des domaines disponibles dans les widgets de référence d'entité en
fonction des permissions et des affectations de domaine de l'utilisateur
courant.

### Cycle de vie des entités

Domain Access vide son cache statique interne lors du presave et du predelete
des entités, et crée automatiquement les champs lorsqu'un nouveau type de
contenu est ajouté.

### Accès aux champs

Le module restreint qui peut modifier les champs de domaine :

- `field_domain_access` sur les **nœuds** nécessite *publish to any domain*
  ou *publish to any assigned domain*.
- `field_domain_access` sur les **utilisateurs** nécessite *assign domain
  editors* ou *assign editors to any domain*.
- `field_domain_all_affiliates` nécessite la permission globale
  correspondante.

## Services

| Service | Classe | Rôle |
|---------|--------|------|
| `domain_access.manager` | `DomainAccessManager` | Vérification centrale des accès : grants de domaine, vérification des permissions, URLs du contenu. |
| `domain_access.helper` | `DomainAccessHelper` | Aide à la création des champs et à l'installation. |
