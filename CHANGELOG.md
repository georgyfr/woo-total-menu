# Changelog

All notable changes to Woo Total Menu will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
