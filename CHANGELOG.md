# Changelog

All notable changes to Woo Total Menu will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.3] - 2026-06-25

### Fixed

- **Indicateur de drop temps réel** : l'indicateur de position (before/after/inside)
  ne se rafraîchissait pas quand le curseur se déplaçait verticalement à l'intérieur
  d'un même item. Le calcul utilisait `sortable.activatorEvent.clientY` qui est figé
  au moment du `pointerdown`. Désormais, un listener `pointermove` global met à jour
  un `useRef` (`cursorPosRef`) + un compteur `cursorTick` qui déclenche le recompute
  de l'effet. Le `handleDragEnd` du `TreePanel` utilise également un `liveCursorYRef`
  pour le calcul final de la position (spec §6.3.2).
- **Migration React 18** : remplacement de `ReactDOM.render()` (déprécié) par
  `createRoot()` depuis `@wordpress/element`. Corrige le warning React :
  « ReactDOM.render is no longer supported in React 18. Use createRoot instead. »
  et active les fonctionnalités concurrentes de React 18 (spec §9.2).
- **Double annonce ARIA** : `@dnd-kit` émet sa propre `LiveRegion` en anglais
  (« Draggable item X was dropped over droppable area Y ») en plus de notre annonce
  française. Désormais, un prop `accessibility={{ announcements: {...} }}` custom
  est passé au `DndContext` avec toutes les callbacks retournant une chaîne vide,
  ce qui neutralise l'annonce anglaise. Seule l'annonce française reste (spec §6.7).
  Note : en `@dnd-kit` v6, `announcements` doit être encapsulé dans `accessibility`,
  pas passé au premier niveau (sinon silencieusement ignoré).

### Changed

- `builder/index.js` : import `createRoot` au lieu de `render`, utilisation de
  `createRoot(container).render(<App />)`.
- `builder/components/SortableTreeItem.js` : ajout d'un `useEffect` qui enregistre
  un listener `pointermove` global (uniquement pendant un drag) et met à jour
  `cursorPosRef.current` + incrémente `cursorTick` pour déclencher le recompute.
- `builder/components/TreePanel.js` : ajout d'un `liveCursorYRef` mis à jour par
  `pointermove` global, utilisé dans `handleDragEnd` pour le calcul final.
- `builder/components/TreePanel.js` : ajout du prop `accessibility={accessibility}`
  au `DndContext`, encapsulant un sous-objet `announcements` avec callbacks
  retournant `''` (la syntaxe `announcements={...}` au premier niveau ne fonctionne
  pas en `@dnd-kit` v6 — elle est silencieusement ignorée).

## [1.1.2] - 2026-06-25

### Added

- **Drag & drop arborescent complet** via `@dnd-kit/core` + `@dnd-kit/sortable` :
  - Réordonnancement d'items au même niveau
  - Déplacement d'items entre niveaux (root → mega_container → column)
  - Nesting automatique d'un item dans un container (mega_container, column, accordion_parent)
  - Validation des règles de nesting en temps réel (spec §3.4.2)
- **Indicateurs visuels de drop** (spec §6.3.2) :
  - "before" : ligne bleue 2px au-dessus de l'item survolé
  - "after" : ligne bleue 2px en-dessous
  - "inside" : bordure pointillée bleue + fond légèrement teinté
  - Indicateur désactivé pour les drops invalides (pas de ligne fantôme)
- **Drag handle** (icône ⠿ six-dot) sur chaque item, séparé du bouton principal (spec §6.3.1)
- **Ghost overlay** qui suit le curseur pendant le drag, affichant l'icône et le label de l'item déplacé
- **Auto-expand** des containers repliés après 500ms de survol pendant le drag (spec §6.3.2)
- **Raccourcis clavier pour réorganiser** (spec §6.3.5) :
  - `Ctrl+↑` / `Ctrl+↓` : déplacer l'item parmi ses frères
  - `Ctrl+→` : indenter (l'item devient enfant du frère précédent)
  - `Ctrl+←` : outdenter (l'item rejoint la liste des frères de son parent, juste après celui-ci)
- **Undo/Redo** avec stacks `past`/`future` dans le store `wtm/menu` (spec §9.9) :
  - Boutons Annuler / Rétablir dans le header (icônes `dashicons-undo` et `dashicons-redo`)
  - Désactivés quand la stack correspondante est vide
  - Raccourcis : `Ctrl+Z` (undo), `Ctrl+Shift+Z` ou `Ctrl+Y` (redo)
  - Chaque action mutating (ADD_ITEM, UPDATE_ITEM, REMOVE_ITEM, MOVE_ITEM, UPDATE_MENU_TITLE, UPDATE_MENU_CONFIG) pousse un snapshot dans `past` et vide `future`
  - Maximum 50 snapshots conservés (FIFO)
  - `clearHistory()` appelé après chaque sauvegarde REST réussie
- **Annonces ARIA `aria-live="polite"`** après chaque déplacement pour les lecteurs d'écran (spec §6.7) :
  - « « Femmes » déplacé en position 2 dans Catégories. »
  - « Action annulée. » / « Action rétablie. »
  - « Déplacement invalide : la cible ne peut pas contenir cet élément. »
- **Validation des règles de nesting** (spec §3.4.2) :
  - `mega_container` accepte uniquement `column`
  - `column` accepte `link`, `title`, `widget`, `separator`, `accordion_parent`
  - `accordion_parent` (menu vertical) accepte `link` et `widget`
  - `link`, `widget`, `title`, `separator` sont terminaux (pas d'enfants)
  - Profondeur maximale = 3 (root → mega_container → column → widget/link)
- **Helper `dnd-helpers.js`** avec :
  - `computeDropPosition(cursorY, rect, canContainChildren)` — calcule before/after/inside selon la position du curseur
  - `isValidDrop({draggedItem, targetItem, targetDepth, menuType, position, isAncestor})` — valide un drop
  - `computeMoveTarget(...)` — calcule le parentId et l'index finaux à passer à `moveItem`
  - `isAncestorOf(items, ancestorId, descendantId)` — empêche de dropper un item dans son propre descendant
- **Helpers store étendus** : `findItemLocation`, `insertItemAtIndex`, `getItemDepth`, `isNestingAllowed`, `getParentDepth`

### Changed

- **`builder/stores/menu.js`** :
  - État étendu avec `past: []` et `future: []` (max 50 entrées)
  - Nouvelles actions : `undo()`, `redo()`, `clearHistory()`
  - Nouveaux sélecteurs : `canUndo(state)`, `canRedo(state)`, `getHistorySize(state)`
  - Nouveau helper interne `pushHistory(state)` appelé avant chaque mutation
  - `saveMenu()` appelle `clearHistory()` après succès
- **`builder/stores/ui.js`** :
  - État étendu avec `announcement: ''`
  - Nouvelles actions : `setAnnouncement(msg)`, `clearAnnouncement()`
  - Nouveau sélecteur : `getAnnouncement(state)`
- **`builder/components/Header.js`** :
  - Ajout des boutons Undo/Redo entre le titre et le device switcher
  - Hook `useEffect` global pour les raccourcis clavier Ctrl+Z / Ctrl+Shift+Z / Ctrl+Y
  - Boutons désactivés quand `!canUndo` / `!canRedo`
- **`builder/components/TreePanel.js`** :
  - Refonte complète pour utiliser `DndContext` + `PointerSensor` + `KeyboardSensor` de `@dnd-kit/core`
  - Gestion du `DragOverlay` (ghost qui suit le curseur)
  - Calcul de la position de drop et dispatch de `moveItem` dans `handleDragEnd`
  - Annonce ARIA après chaque drop
- **`builder/components/SortableTreeItem.js`** (nouveau fichier) :
  - Item d'arbre avec `useSortable` de `@dnd-kit/sortable`
  - Drag handle, bouton toggle expand/collapse, indicateurs de drop
  - Gestion des raccourcis Ctrl+Arrow pour réorganiser
  - Auto-expand au survol pendant 500ms
- **`builder/style.css`** : +173 lignes pour les nouveaux composants (drag handle, drop indicators, drag overlay, undo/redo buttons, sr-announcement)
- **`package.json`** :
  - Version bump `1.1.0` → `1.1.2`
  - Ajout des dépendances : `@dnd-kit/core@^6.3.1`, `@dnd-kit/sortable@^10.0.0`, `@dnd-kit/utilities@^3.2.2`, `@dnd-kit/modifiers@^9.0.0`
  - Ajout d'`overrides` pour forcer `ajv@^8.17.1` et `ajv-keywords@^5.1.0` (résolution conflit webpack)
- **Build bundle** : 28.6 Ko → 85.7 Ko (JS), 11.9 Ko → 14.3 Ko (CSS)

### Fixed

- **🚨 Bug critique `mapItems()`** dans `builder/stores/menu.js:63-65` : la variable `newItem` était déclarée `const` puis réassignée (`newItem = { ...newItem, children: ... }`), ce qui déclenchait une `TypeError: Assignment to constant variable.` dès qu'un item avec `children` était muté. Conséquence : toute opération `ADD_ITEM`, `UPDATE_ITEM`, `REMOVE_ITEM` ou `MOVE_ITEM` sur un arbre non-root crashait le builder. Correctif : `const newItem` → `let newItem`.
- **`moveItem` reducer** : réécrit pour utiliser le nouveau helper `insertItemAtIndex()` au lieu d'un `mapItems` imbriqué qui créait des enfants mal positionnés. L'index d'insertion est maintenant calculé correctement en tenant compte du retrait préalable de l'item déplacé.

## [1.1.1] - 2026-06-24

### Added
- **CRUD complet des items dans le builder React** :
  - Bouton "Ajouter un élément" avec dropdown des 6 types (link, mega_container, column, widget, title, separator)
  - Suppression d'item (icône corbeille au survol + confirmation JavaScript)
  - Renommage inline par double-clic sur le label (Enter pour valider, Escape pour annuler)
  - Ajout d'enfants dans `mega_container` et `column` (icône "+" au survol)
- **Composant `AddItemButton`** (`builder/components/AddItemButton.js`) :
  - Dropdown avec 6 types d'items, chacun avec icône, libellé et description
  - Valeurs par défaut intelligentes (link → label/url, widget html → content, etc.)
  - Variantes compact (icône seule) et full (icône + texte)
  - Fermeture automatique au clic en dehors
- **Panneau Propriétés entièrement éditable** selon le type d'item :
  - **link** : label, url, target (_self/_blank), icon, badge, visibility
  - **mega_container** : label, trigger (hover/click), width (200-2000 ou "full")
  - **column** : width (1-12)
  - **widget** : widget_type (read-only), label, et settings selon le type :
    - `html` : content (textarea)
    - `banner` : image_url, link_url, alt
    - `product_grid` : product_source (5 options), columns (1-6), limit (1-12)
    - `category_grid` : columns (1-6), show_images, show_counts
  - **title** : label, badge
  - **separator** : aucune propriété spécifique (juste visibility)
- **Éditeur de badge** : texte + couleur texte + couleur fond (color pickers) + bouton "Retirer le badge"
- **Édition du titre du menu** dans le panneau Propriétés quand aucun item n'est sélectionné
- **Actions Redux dans le store `wtm/menu`** :
  - `addItem(item, parentId)` — Ajoute un item (génère un ID unique si manquant)
  - `updateItem(id, patch)` — Met à jour un item par ID avec un patch
  - `removeItem(id)` — Supprime un item (et tous ses enfants récursivement)
  - `moveItem(id, parentId, index)` — Déplace un item vers un nouveau parent à un index donné (préparé pour v1.1.2 drag & drop)
- **Helpers immutables** exportés pour la manipulation de l'arbre :
  - `generateId(prefix)` — Génère un ID unique (prefix-timestamp-counter)
  - `findItem(items, id)` — Recherche récursive d'un item par ID
  - `mapItems(items, fn)` — Map récursif (retourne `false` pour supprimer)
  - `updateItemById(items, id, patch)` — Met à jour un item par ID
  - `removeItemById(items, id)` — Supprime un item par ID
  - `addChildToParent(items, parentId, newItem)` — Ajoute un enfant à un parent

### Changed
- `TreePanel.js` refondu :
  - Actions au survol de chaque ligne : ajouter enfant (+), supprimer (corbeille)
  - Mode édition inline (input remplaçant le label)
  - Bouton "Ajouter" en en-tête + en pied de panneau + dans l'état vide
  - Indicateur visuel pour les items avec badge
- `PropertiesPanel.js` refondu :
  - Composant `ItemProperties` avec formulaires d'édition par type
  - Composant `MenuProperties` avec édition du titre
  - Composant `BadgeEditor` (ajout/édition/suppression de badge)
  - Composant `EmptyState` quand aucun menu chargé
- Build bundle : 14 Ko → 28.6 Ko JS (nouvelles fonctionnalités)
- `WTM_VERSION` bumped to `1.1.1`

### Directory Structure (delta)
```
woo-total-menu/builder/
├── components/
│   ├── AddItemButton.js       ← NEW (dropdown 6 types)
│   ├── TreePanel.js           (MODIFIED — suppression + rename inline + add child)
│   └── PropertiesPanel.js     (MODIFIED — édition complète par type)
└── stores/
    └── menu.js                (MODIFIED — actions CRUD items + helpers)
```

## [1.1.0] - 2026-06-24

### Added
- **Builder visuel React — squelette de l'application** :
  - Page admin `wtm-builder` (full-screen, masque le menu WP et l'admin bar)
  - Layout 3 colonnes : arborescence (280px) / aperçu (flex 1) / propriétés (320px)
  - En-tête avec titre du menu, indicateur "dirty" (●), badge de type, sélecteur de device (desktop/tablet/mobile), bouton "Enregistrer"
- **Stores `@wordpress/data`** :
  - `wtm/menu` : état du menu (menu, items, isLoading, isSaving, error, isDirty), actions `loadMenu`, `saveMenu`, `updateMenuTitle`, `updateMenuConfig`, sélecteurs `getMenu`, `getItems`, `getSelectedItem`, `isLoading`, `isSaving`, `getError`, `isDirty`
  - `wtm/ui` : état UI (selectedItemId, device, restUrl, restNonce), actions `selectItem`, `setDevice`, `setRestConfig`
- **Communication avec l'API REST `/wtm/v1/menus`** via `@wordpress/api-fetch` avec nonce middleware
- **Pipeline de build `@wordpress/scripts`** :
  - Webpack 5 + Babel + DependencyExtractionWebpackPlugin (externals WordPress)
  - `webpack.config.js` custom pour pointer vers `builder/index.js` au lieu du `src/index.js` par défaut
  - Sortie : `build/index.js` (~14 Ko) + `build/style-index.css` (~8 Ko) + `build/index.asset.php` (dépendances)
- **Style CSS du builder** (~400 lignes) :
  - Layout flex 3 colonnes avec redimensionnement responsive
  - Composants stylés : header, tree panel, preview panel, properties panel
  - Variables de couleur cohérentes avec l'admin WP (primary #6C5CE7)
- **Bouton "Builder"** sur la liste des menus (ouvre le builder pour le menu sélectionné)
- **`package.json`** avec :
  - Dépendances : `@wordpress/api-fetch`, `@wordpress/data`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/url`
  - devDependency : `@wordpress/scripts`
  - Scripts : `npm run build`, `npm run start`, `npm run lint:js`

### Components React créés
- `builder/index.js` — Point d'entrée, rend `<App>` dans `#wtm-builder-root`
- `builder/components/App.js` — Composant racine, orchestre les 3 colonnes
- `builder/components/Header.js` — Toolbar avec titre, device switcher, save
- `builder/components/TreePanel.js` — Panneau gauche : arborescence des items
- `builder/components/PreviewPanel.js` — Panneau central : aperçu (placeholder en v1.1.0, iframe en v1.1.4)
- `builder/components/PropertiesPanel.js` — Panneau droit : propriétés de l'item sélectionné

### Changed
- `Admin_Menu::enqueue_admin_styles()` détecte la page Builder et appelle `enqueue_builder_assets()` qui :
  - Vérifie l'existence de `build/index.js`
  - Lit les dépendances depuis `build/index.asset.php`
  - Enregistre et enqueue le JS + CSS bundle
  - Localise `wtmBuilderData` (restUrl, restNonce)
  - Affiche un notice d'erreur si les assets ne sont pas compilés
- Nouveau sous-menu "Builder" ajouté dans `Admin_Menu::register_menu()`
- Nouveau fichier `src/Admin/Pages/Builder.php` qui rend le conteneur `#wtm-builder-root` avec data-attributes (menu-id, is-new)
- `WTM_VERSION` bumped to `1.1.0`

### Directory Structure (delta)
```
woo-total-menu/
├── builder/                    ← NEW directory (React source)
│   ├── index.js                ← Entry point
│   ├── style.css               ← Builder styles
│   ├── components/
│   │   ├── App.js              ← Root component
│   │   ├── Header.js           ← Top toolbar
│   │   ├── TreePanel.js        ← Left column
│   │   ├── PreviewPanel.js     ← Center column
│   │   └── PropertiesPanel.js  ← Right column
│   └── stores/
│       ├── menu.js             ← wtm/menu store
│       └── ui.js               ← wtm/ui store
├── build/                      ← NEW directory (compiled output)
│   ├── index.js                (14.3 Ko, minified)
│   ├── index.asset.php         (dependencies manifest)
│   ├── style-index.css         (8.0 Ko, minified)
│   └── style-index-rtl.css     (8.0 Ko, RTL)
├── package.json                ← NEW
├── package-lock.json           ← NEW (auto-generated)
├── webpack.config.js           ← NEW (override entry to builder/index.js)
└── src/Admin/Pages/
    └── Builder.php             ← NEW (PHP page that mounts React)
```

## [1.0.4] - 2026-06-24

### Added
- **Validation stricte par type d'item** dans `Schema_Validator` :
  - `link` : requires `label` + `url` ; optional `target` (_self/_blank), `icon`, `badge`, `children`
  - `mega_container` : requires `label` + `children` (au moins 1, tous de type `column`) ; optional `trigger` (hover/click), `width` (200-2000 ou "full")
  - `column` : optional `width` (1-12), `children` (parmi widget/link/title/separator)
  - `widget` : requires `widget_type` + `widget_settings` ; optional `label`, `children`
  - `title` : requires `label`
  - `separator` : no additional requirement
- **Validation par type de widget** (8 types) avec règles spécifiques :
  - `category_grid` : `columns` (1-6), `categories` (array of IDs), `show_images`, `show_counts`
  - `product_grid` : `columns` (1-6), `product_source` (featured/best_selling/recent/on_sale/custom), `limit` (1-12)
  - `mini_cart` : `show_subtotal`, `show_checkout_button`, `show_thumbnail` (booléens)
  - `search` : `placeholder` (string), `show_category_filter` (bool)
  - `banner` : `image_url` requis, `link_url`, `alt`, `target`
  - `html` : `content` requis
  - `custom_link` : `label` + `url` requis
  - `title` : `text` requis, `level` (1-6)
- **Validation des modules header/footer** (9 types) : `logo`, `menu`, `search`, `cart`, `button`, `html`, `social`, `newsletter`, `text`
- **Validation des badges** : `text` requis, `color` et `background` en hex (#RGB ou #RRGGBB)
- **Validation de l'arborescence des layouts** (rows → columns → modules) avec règles spécifiques à chaque niveau
- **30+ codes d'erreur explicites** : `wtm_link_missing_label`, `wtm_mega_invalid_trigger`, `wtm_widget_invalid_source`, `wtm_badge_invalid_color`, `wtm_row_missing_columns`, etc.
- **Méthode `Schema_Validator::get_full_schema()`** retournant le schéma JSON Schema draft-04 complet avec `definitions` (item, badge, layout, row, column, module)
- **Endpoint `GET /wtm/v1/menus/schema` enrichi** avec :
  - `definitions` (item, badge, layout, row, column, module)
  - Listes de valeurs autorisées : `item_types`, `widget_types`, `module_types`, `link_targets`, `mega_triggers`, `mobile_behaviors`, `visibility_values`
- **Documentation complète du schéma** dans `docs/schema.md` (8 sections, 4 exemples complets)
- **57 tests unitaires PHP** dans `/home/z/my-project/server/scripts/test-schema-validator.php` couvrant tous les cas valides et invalides (100% de réussite)

### Changed
- `Schema_Validator::validate_item()` dispatche maintenant vers des validators spécifiques par type (`validate_item_link`, `validate_item_mega_container`, `validate_item_column`, `validate_item_widget`, `validate_item_title`, `validate_item_separator`)
- `Menu_Controller::get_schema()` enrichi avec `definitions` et listes de valeurs autorisées
- `WTM_VERSION` bumped to `1.0.4`

### Backward Compatibility
- Les configs valides en v1.0.3 restent valides en v1.0.4 (vérifié par test unitaire de rétro-compatibilité)

### Directory Structure (delta)
```
woo-total-menu/
├── docs/                        ← NEW directory
│   └── schema.md                ← NEW (8 sections, 4 examples)
└── src/Core/
    └── Schema_Validator.php     (MODIFIED — extended with strict per-type validation)
```

## [1.0.3] - 2026-06-24

### Added
- **REST API CRUD complète** sous `/wp-json/wtm/v1/menus` (class `WooTotalMenu\Api\Menu_Controller`):
  - `GET /wtm/v1/menus` — Liste avec filtres (search, menu_type, location, status, orderby, order, page, per_page) et pagination (X-WP-Total, X-WP-TotalPages)
  - `GET /wtm/v1/menus/{id}` — Détail d'un menu
  - `POST /wtm/v1/menus` — Création avec validation JSON du config (201 + Location header)
  - `PUT/PATCH /wtm/v1/menus/{id}` — Mise à jour partielle (uniquement les champs fournis)
  - `DELETE /wtm/v1/menus/{id}` — Suppression avec retour de l'objet précédent
  - `POST /wtm/v1/menus/{id}/duplicate` — Duplication avec copie de toutes les méta (201 + Location header)
  - `GET /wtm/v1/menus/schema` — Schéma JSON Schema draft-04 complet
- **Schema_Validator** class (`src/Core/Schema_Validator.php`) :
  - `validate_config($value)` — Valide la structure de `_wtm_config` (version, items, settings)
  - `validate_item($item, $path)` — Valide un élément de menu (id, type parmi 6 valeurs, children)
  - `validate_layout($value)` — Valide `_wtm_header_config` / `_wtm_footer_config` (version, rows, settings)
  - `decode_and_validate_config($raw)` — Décode + valide une string JSON
  - `decode_and_validate_layout($raw)` — Idem pour les layouts
  - `normalize_config($value)` / `normalize_layout($value)` — Complète les champs manquants
- **Format de réponse propre** : id, title, slug, status, menu_type, location, config (décodé), header_config (décodé), footer_config (décodé), version, date_created, date_modified, author, edit_url
- Tous les endpoints vérifient la capacité `wtm_manage_menus` (read + write)
- Invalidation du cache du menu après chaque opération d'écriture
- Pagination headers `X-WP-Total` et `X-WP-TotalPages` sur la liste
- En-tête `Location` sur les réponses 201 (create + duplicate) pointant vers l'URL REST du menu créé

### Changed
- `Bootstrap::init_services()` : ajout de `rest_menus` (Menu_Controller) instancié sur tous les contextes (admin + frontend + AJAX)
- `WTM_VERSION` bumped to `1.0.3`

### Directory Structure (delta)
```
woo-total-menu/src/
├── Api/                        ← NEW directory
│   └── Menu_Controller.php     ← NEW
└── Core/
    └── Schema_Validator.php    ← NEW
```

## [1.0.2] - 2026-06-24

### Added
- **Page "Tableau de bord"** (`src/Admin/Pages/Dashboard.php`) with:
  - 6 stat cards (total menus, published, drafts, by location, by type, environment info)
  - Quick action buttons ("Créer un nouveau menu", "Voir tous les menus")
  - Recent menus table (last 5)
  - Admin notices based on query params
- **Page "Menus"** (`src/Admin/Pages/Menus_List.php`) with:
  - Filter bar (by type, by status, full-text search)
  - WP-style table with 7 columns (title, type, location, status, created, modified, actions)
  - 4 row actions: edit, toggle status, duplicate, delete (with JS confirm)
  - Empty state CTA
- **Page "Réglages"** (`src/Admin/Pages/Settings.php`) with 7 tabs:
  - Général (enable plugin, default location)
  - Styles (5 color pickers, border radius)
  - Typographie (10 Google Fonts, base size, heading size)
  - Responsive (mobile/tablet breakpoints, mobile behavior, hamburger position)
  - Performance (cache, lazy load, minify CSS)
  - Analytics (disabled — preview for v1.7.1)
  - Permissions (role × capability matrix, with admin locked)
- **Admin_Menu orchestrator** (`src/Admin/Admin_Menu.php`):
  - Top-level menu + 4 submenus (Dashboard, Menus, Settings, About)
  - Capability checks per submenu
  - Action router via `admin_init` (create_menu, delete_menu, duplicate_menu, toggle_status)
  - Nonce verification + capability check for each action
  - Cache invalidation on every action
  - ~120 lines of shared admin CSS (cards, badges, tables, tabs, buttons, forms, empty state)
- **Dossier `versions/`** with detailed per-version documentation:
  - `README.md` — table of contents with all versions and statuses
  - `v1.0.0.md`, `v1.0.1.md`, `v1.0.2.md` — full release notes (objective, features, changes, files, tests, update instructions, GitHub links)

### Changed
- `About.php` refactored:
  - Converted from instance class to static class (`About::render()`)
  - Removed redundant `admin_menu` hook (now handled by `Admin_Menu`)
  - Removed inline CSS (now shared via `Admin_Menu::get_admin_css()`)
  - Added "Liens utiles" card with GitHub links
  - Roadmap with status icons (done/current/todo)
- `Bootstrap::init_services()`:
  - Replaced `admin_pages` (was `Pages\About`) with `admin_menu` (`Admin_Menu`)
- `WTM_VERSION` bumped to `1.0.2`.

### Directory Structure (delta)
```
woo-total-menu/
├── versions/                    ← NEW (entire folder)
│   ├── README.md
│   ├── v1.0.0.md
│   ├── v1.0.1.md
│   └── v1.0.2.md
└── src/
    └── Admin/
        ├── Admin_Menu.php       ← NEW
        └── Pages/
            ├── About.php        (MODIFIED)
            ├── Dashboard.php    ← NEW
            ├── Menus_List.php   ← NEW
            └── Settings.php     ← NEW
```

## [1.0.1] - 2026-06-24

### Added
- **Custom Post Type `wtm_menu`** (class `WooTotalMenu\Core\CPT_Manager`)
  - Registered on `init` priority 5.
  - REST-enabled: accessible at `/wp-json/wp/v2/wtm_menus`.
  - Revisions enabled (for undo/redo).
  - Block editor disabled (we'll use our own builder).
  - Custom capabilities mapping (`wtm_manage_menus` required to edit/create/delete).
  - Hidden from admin menu (exposed via our own admin pages starting v1.0.2).
- **4 menu types** (filterable via `wtm_menu_types`):
  - `horizontal` — Méga menu horizontal
  - `vertical` — Menu vertical (sidebar)
  - `offcanvas` — Menu off-canvas
  - `footer` — Menu de pied de page
- **4 default theme locations** registered as `nav_menu_locations` (filterable via `wtm_locations`):
  - `wtm_primary` — Menu principal (header)
  - `wtm_footer` — Pied de page
  - `wtm_sidebar` — Barre latérale
  - `wtm_mobile` — Menu mobile dédié
- **6 meta-keys** registered via `register_post_meta()` with REST visibility and sanitization:
  - `_wtm_location` (string, default `primary`)
  - `_wtm_menu_type` (string, default `horizontal`)
  - `_wtm_config` (JSON string, default `{"version":1,"items":[]}`)
  - `_wtm_header_config` (JSON string, optional)
  - `_wtm_footer_config` (JSON string, optional)
  - `_wtm_version` (integer, default current DB version)
- **Meta-boxes** (class `WooTotalMenu\Admin\Meta_Boxes`):
  - Sidebar box: location selector, menu type selector, version (read-only).
  - Main box: 3 JSON textareas for menu config, header config, footer config.
  - Nonce-protected save handler with capability check.
  - Cache invalidation on save (via `Cache_Manager::invalidate_menu()`).
- **`default_menu_type_on_insert` filter** on `wp_insert_post_data`: ensures a non-empty title.
- **Helper methods** `CPT_Manager::get_menu_types()` / `CPT_Manager::get_locations()`.

### Updated
- `Bootstrap::init_services()` now instantiates `CPT_Manager` and `Meta_Boxes`.
- `WTM_VERSION` bumped to `1.0.1`.

### Directory Structure (delta)
```
woo-total-menu/src/
├── Admin/
│   ├── Pages/
│   │   └── About.php
│   └── Meta_Boxes.php          ← NEW
└── Core/
    ├── Cache_Manager.php
    ├── Permissions.php
    └── CPT_Manager.php          ← NEW
```

## [1.0.0] - 2026-06-24

### Added
- Plugin skeleton with PSR-4 autoloader (`WooTotalMenu\` namespace).
- `Bootstrap` class with dependency check (WooCommerce required).
- `Cache_Manager` (object cache + transients, with menu-specific invalidation).
- `Permissions` module with custom capabilities:
  - `wtm_manage_menus`
  - `wtm_manage_templates`
  - `wtm_view_analytics`
  - `wtm_manage_settings`
- Default global settings stored in `wtm_global_settings` option:
  - General (enabled, default location)
  - Styles (primary color #6C5CE7, background, text, success, error, border radius)
  - Typography (Inter, base 14px, heading 18px)
  - Responsive (mobile 768px, tablet 1024px, offcanvas mobile behavior)
  - Performance (cache enabled, lazy load widgets, minify CSS)
  - Analytics (disabled by default)
  - Permissions (default role mappings)
- Admin "About" page (`toplevel_page_wtm-about`) with:
  - Version info
  - Roadmap to v1.x
  - Environment info (PHP/WP/WooCommerce versions, theme, DB version)
- Frontend `Assets_Loader` stub (no-op in v1.0.0).
- `readme.txt` for WordPress.org plugin directory.
- `CHANGELOG.md` (this file).

### Constants
- `WTM_VERSION` = `1.0.0`
- `WTM_PLUGIN_FILE`, `WTM_PLUGIN_DIR`, `WTM_PLUGIN_URL`, `WTM_PLUGIN_BASENAME`
- `WTM_DB_VERSION` = `1`
- `WTM_REST_NAMESPACE` = `wtm/v1`
- `WTM_CPT_MENU` = `wtm_menu`
- `WTM_OPTION_SETTINGS` = `wtm_global_settings`
- `WTM_OPTION_TEMPLATES` = `wtm_user_templates`
- `WTM_OPTION_DB_VERSION` = `wtm_db_version`

### Directory Structure
```
woo-total-menu/
├── assets/                  # (empty, future icons/images)
├── build/                   # (empty, future compiled React assets)
├── languages/               # (empty, future .pot/.po/.mo)
├── src/
│   ├── Admin/
│   │   └── Pages/
│   │       └── About.php    # Admin "About" page
│   ├── Api/                 # (empty, future REST controllers)
│   ├── Core/
│   │   ├── Cache_Manager.php
│   │   └── Permissions.php
│   ├── Frontend/
│   │   └── Assets_Loader.php
│   ├── Entities/            # (empty, future data objects)
│   └── Bootstrap.php
├── templates/               # (empty, future JSON templates)
├── readme.txt
├── CHANGELOG.md
└── woo-total-menu.php       # Entry point
```
