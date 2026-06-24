# Changelog

All notable changes to Woo Total Menu will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
