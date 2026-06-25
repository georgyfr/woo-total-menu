=== Woo Total Menu ===
Contributors: woo-total-menu-team
Tags: menu, mega menu, header, footer, woocommerce, navigation, builder
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Créez des méga menus, headers et footers WooCommerce avancés via un builder visuel glisser-déposer.

== Description ==

Woo Total Menu est un plugin WordPress/WooCommerce conçu pour créer en quelques clics :

* Des méga menus horizontaux mettant en valeur les catégories de produits, articles et pages.
* Des menus verticaux (sidebar navigation, filtres, menu catalogue).
* Des headers complets (logo, barre de recherche, icônes panier/compte).
* Des footers complets (multi-colonnes, widgets, liens légaux, réseaux sociaux).

Le tout avec une approche glisser-déposer visuelle côté admin, et un rendu 100% responsive, moderne et personnalisable côté frontend, sans toucher une ligne de code.

= Version 1.0.0 =

Cette version initiale pose les fondations techniques du plugin :

* Squelette PHP avec autoloader PSR-4
* Système de permissions et capacités personnalisées (`wtm_manage_menus`, etc.)
* Gestionnaire de cache (objet + transients)
* Réglages globaux par défaut (couleurs, typo, responsive, performance)
* Page d'accueil de l'administration avec roadmap

== Installation ==

1. Téléversez le dossier `woo-total-menu` dans `/wp-content/plugins/`
2. Activez le plugin via le menu "Extensions" de WordPress
3. WooCommerce doit être activé pour que Woo Total Menu fonctionne
4. Accédez au menu "Woo Total Menu" dans la barre latérale d'administration

== Frequently Asked Questions ==

= WooCommerce est-il obligatoire ? =

Oui. Woo Total Menu est pensé en priorité pour les boutiques WooCommerce. Une notification s'affiche si WooCommerce n'est pas actif.

= Cette version crée-t-elle déjà des menus ? =

Non. La v1.0.0 pose les fondations techniques. Les premières fonctionnalités visibles arrivent en v1.0.1 (Custom Post Type) et v1.1.0 (Builder visuel).

== Changelog ==

= 1.2.0 =
* New: Rendu frontend complet — les menus wtm_menu s'affichent désormais côté visiteur (spec §2.4, §5). Quatre types de menus supportés : horizontal (méga menu), vertical (sidebar), off-canvas (mobile), footer (multi-colonnes).
* New: Classe `Menu_Renderer` (src/Frontend/Menu_Renderer.php, ~800 lignes) — walker PHP pur qui parcourt l'arbre JSON `_wtm_config` et produit du HTML sémantique `<nav><ul>` pour les 6 types d'items (link, mega_container, column, widget, title, separator) et les 7 types de widgets (html, banner, product_grid, category_grid, mini_cart, search, custom_link).
* New: Classe `Location_Interceptor` — hook `wp_nav_menu_args` (priority 20) qui remplace les locations de thème enregistrées (`wtm_primary`, `wtm_footer`, etc.) par le rendu WTM quand un menu est publié pour cette location (spec §7.5).
* New: Classe `Dynamic_CSS` — compile un CSS unique à partir des réglages globaux (couleurs, typo, breakpoint) et des paramètres par menu, sauvegardé dans `uploads/wtm-cache/dynamic-{hash}.css` avec cache-busting (spec §2.4.3). Purge automatique sur `save_post_wtm_menu`, `wtm_settings_saved`, `wp_restore_post_revision`.
* New: Classe `Shortcode` — `[wtm_menu id="123"]` ou `[wtm_menu location="primary"]` pour insérer un menu n'importe où (pages, articles, Elementor, etc.) (spec §2.8.2).
* New: Assets frontend conditionnels — `Assets_Loader` remplace le stub v1.0.0 et n'enqueue CSS/JS que si un menu WTM est rendu sur la page (spec §2.6.1). Écoute l'action `wtm_rendered_location` déclenchée par le shortcode et l'interceptor.
* New: Fichier `assets/front/wtm-frontend.css` (~15 Ko, base styles pour 4 types de menus + méga panel + widgets + responsive + reduced-motion).
* New: Fichier `assets/front/wtm-frontend.js` (~5 Ko, vanilla JS sans jQuery) — gère off-canvas (open/close, overlay click, ESC, focus trap), click-trigger mega containers, accordion mobile, footer accordion, sync mini-cart WooCommerce.
* New: 4 widgets WooCommerce rendus côté frontend : `product_grid` (grille de produits avec image/nom/prix/bouton "Ajouter" + transient cache 12h filtrable), `category_grid` (grille de catégories avec thumbnail), `mini_cart` (compteur + total synchronisé avec WC cart fragments), `search` (formulaire de recherche produits WC).
* New: 3 widgets non-WC : `html` (wp_kses_post), `banner` (bloc CTA coloré), `custom_link` (bouton stylisé).
* New: Hooks filters pour développeurs (spec §2.8.4) : `wtm_menu_config`, `wtm_render_item`, `wtm_menu_classes`, `wtm_dynamic_css`, `wtm_map_theme_location`, `wtm_product_grid_query`, `wtm_widget_cache_duration`, `wtm_force_enqueue_assets`.
* New: Action `wtm_rendered_location` déclenchée à chaque rendu de menu (permet à des extensions tierces de tracker quels menus sont actifs).
* New: Localisation FR du JS frontend via `wp_localize_script('wtmFrontend', ...)` avec breakpoint, mobileBehavior, i18n strings, ajaxUrl.
* Update: Bootstrap.php instancie les 4 nouveaux services frontend (Dynamic_CSS, Menu_Renderer, Location_Interceptor, Shortcode) + Assets_Loader avec dépendance Dynamic_CSS. Ajoute hook `purge_dynamic_css` sur save_post_wtm_menu / wtm_settings_saved / wp_restore_post_revision.
* Update: Bump version 1.1.5 → 1.2.0 (woo-total-menu.php, package.json, readme.txt)
* Security: Tout le HTML rendu échappe les URLs (esc_url), labels (esc_html), couleurs (regex whitelist), HTML personnalisé (wp_kses_post). Le répertoire de cache contient un .htaccess + index.php qui bloquent l'exécution PHP directe.

= 1.1.5 =
* New: Historique des révisions WordPress — chaque sauvegarde crée désormais une révision du menu CPT (spec §6.6, §7.6). Le bouton "Historique" (icône dashicons-backup) dans le header ouvre un modal listant les révisions passées avec auteur, date relative, et nombre d'items.
* New: Endpoint REST GET /wtm/v1/menus/{id}/revisions — liste les révisions paginées d'un menu avec métadonnées WTM décodées (_wtm_config, _wtm_menu_type, _wtm_location, _wtm_header_config, _wtm_footer_config, _wtm_version)
* New: Endpoint REST GET /wtm/v1/menus/{id}/revisions/{revision_id} — récupère une révision spécifique avec son snapshot de configuration complet
* New: Endpoint REST POST /wtm/v1/menus/{id}/revisions/{revision_id}/restore — restaure une révision : appelle wp_restore_post_revision() puis copie manuellement les métadonnées WTM depuis la révision vers le post parent (WP core ne restaure pas les meta automatiquement)
* New: Filter `wtm_max_revisions` (valeur par défaut 10) — appliqué via wp_revisions_to_keep sur le CPT wtm_menu (spec §7.6)
* New: Toutes les 6 meta WTM sont désormais enregistrées avec `revisions_enabled => true` (WP 6.4+) et hookées via le filter `_wp_post_revision_meta_keys` pour compatibilité avec WP 6.3
* New: Action `wp_restore_post_revision` hookée pour restaurer les meta WTM en plus des post fields
* New: Composant HistoryPanel.js — modal React avec overlay, backdrop click close, Escape close, liste scrollable, avatar auteur, date relative, compteur d'items, badge "Aperçu" sur la révision actuellement prévisualisée
* New: Prévisualisation de révision — cliquer une ligne du modal envoie la config de la révision dans l'iframe de preview via postMessage, sans toucher à l'état live du menu. Un pill "Révision #N" s'affiche dans le header du panneau de preview.
* New: Flow de restauration avec confirmation inline (bouton "Restaurer" → confirmation → appel API → remplacement de l'état menu → clear de l'undo/redo local → fermeture du modal)
* New: Store wtm/menu étendu avec `revisions`, `isLoadingRevisions`, `isRestoring` + actions `loadRevisions(menuId)`, `restoreRevision(menuId, revisionId)`, `setRevisions()`, `setIsLoadingRevisions()`, `setIsRestoring()`
* New: Store wtm/ui étendu avec `isHistoryOpen`, `previewRevisionId` + actions `openHistory()`, `closeHistory()`, `setPreviewRevision(revisionId)`
* Update: Header.js ajoute un 3e bouton "Historique" dans le group undo/redo (dashicons-backup), disabled pour les nouveaux menus non sauvegardés
* Update: PreviewPanel.js étendu pour utiliser `effectiveConfig` (config live OU config d'une révision prévisualisée) dans le postMessage envoyé à l'iframe
* Update: Menu_Controller::update_menu() écrit désormais une signature dérivée du config dans `post_content` pour garantir qu'une révision est créée à chaque save (sinon WP ne crée pas de révision sur changements meta-only)
* Update: Meta_Boxes.php — `register_meta()` ajoute `revisions_enabled => true` sur les 6 meta WTM ; nouveaux hooks `_wp_post_revision_meta_keys` et `wp_restore_post_revision`
* Update: CPT_Manager.php — hook `wp_revisions_to_keep` applique le filter `wtm_max_revisions` (défaut 10) sur le CPT wtm_menu
* Update: Bootstrap.php instancie Revisions_Controller sur chaque requête
* Update: Bump Requires at least 6.3 → 6.4 (nécessaire pour `revisions_enabled` sur register_post_meta, aligné avec spec §7.6 qui mentionne WP 6.4)
* Update: Bump version 1.1.4 → 1.1.5 (woo-total-menu.php, package.json, readme.txt)

= 1.1.4 =
* New: Live preview via iframe + postMessage — le panneau central du Builder affiche maintenant un aperçu en direct du menu (remplace le placeholder statique de v1.1.0)
* New: Endpoint REST GET /wtm/v1/preview-frame servant un document HTML auto-contenu avec un listener postMessage (renderer JS pour les 6 types d'items + 7 types de widgets)
* New: Débounce 250 ms sur les postMessage (spec §6.6 — évite de flooder l'iframe pendant un drag)
* New: Modes responsive (desktop/tablet/mobile) avec bordures "device" pour visualiser le menu en 375px, 768px ou pleine largeur
* Update: PreviewPanel.js entièrement réécrit (iframe + postMessage + ready signal)
* Update: UI store étendu avec previewFrameUrl
* Update: Admin_Menu passe previewFrameUrl au Builder via wp_localize_script
* Update: Bootstrap instancie Preview_Controller sur chaque requête (l'iframe charge via REST, pas admin)
* Update: Bump version 1.1.3 → 1.1.4 (woo-total-menu.php, package.json, readme.txt)

= 1.1.3 =
* Fix: Indicateur de drop qui ne se rafraîchissait pas en temps réel quand le curseur se déplaçait verticalement à l'intérieur d'un même item (le calcul utilisait cursorY figé au pointerdown au lieu du cursorY courant)
* Fix: Migration de ReactDOM.render → createRoot (React 18 API moderne) — corrige le warning "ReactDOM.render is no longer supported in React 18" et active les fonctionnalités concurrentes
* Fix: Double annonce ARIA pour les lecteurs d'écran (l'anglais par défaut de @dnd-kit + le français du plugin) — désormais seule l'annonce française est émise
* Update: SortableTreeItem.js ajoute un listener pointermove global avec useREF cursorPosRef + state cursorTick pour déclencher le recompute de l'indicateur
* Update: TreePanel.js ajoute liveCursorYRef mis à jour par pointermove, utilisé par handleDragEnd pour le calcul final de la position
* Update: index.js migré vers createRoot (import depuis @wordpress/element)
* Update: TreePanel.js passe un prop accessibility={{announcements:{}}} au DndContext pour neutraliser la LiveRegion par défaut (en @dnd-kit v6, announcements doit être encapsulé dans accessibility, pas passé au premier niveau)
* Update: Bump version 1.1.2 → 1.1.3 (woo-total-menu.php, package.json, readme.txt)

= 1.1.2 =
* New: Drag & drop arborescent complet via @dnd-kit (réordonnancement + nesting + cross-level moves)
* New: Indicateurs visuels de drop : ligne bleue avant/après, bordure pointillée pour "inside" (spec §6.3.2)
* New: Drag handle (icône ⠿) sur chaque item + ghost overlay qui suit le curseur (spec §6.3.1)
* New: Auto-expand des containers repliés après 500ms de survol (spec §6.3.2)
* New: Raccourcis clavier pour réorganiser : Ctrl+↑/↓ (réordonner), Ctrl+→ (indenter), Ctrl+← (outdenter) (spec §6.3.5)
* New: Boutons Undo/Redo dans le header + raccourcis Ctrl+Z / Ctrl+Shift+Z / Ctrl+Y (spec §9.9)
* New: Annonces ARIA aria-live="polite" après chaque déplacement pour les lecteurs d'écran (spec §6.7)
* New: Validation des règles de nesting en temps réel (mega_container→column uniquement, column→widget/link, etc.) (spec §3.4.2)
* New: Store wtm/menu étendu avec past/future stacks (max 50 snapshots) + actions undo(), redo(), clearHistory()
* New: Store wtm/ui étendu avec announcement + actions setAnnouncement(msg), clearAnnouncement()
* New: Helper dnd-helpers.js (computeDropPosition, isValidDrop, computeMoveTarget, isAncestorOf)
* New: Helpers store addItem/moveItem/insertItemAtIndex avec nesting validation
* Fix: Bug critique mapItems() qui réassignait une const → crash sur toute opération d'arbre non-root (menu.js:63-65)
* Fix: Action moveItem() réécrite pour utiliser insertItemAtIndex (au lieu de mapItems imbriqué)
* Update: Build bundle 28.6 Ko → 85.7 Ko (inclut @dnd-kit/core + @dnd-kit/sortable + @dnd-kit/utilities)
* Update: package.json : ajout de @dnd-kit/core@^6.3.1, @dnd-kit/sortable@^10.0.0, @dnd-kit/utilities@^3.2.2, @dnd-kit/modifiers@^9.0.0 ; bump version 1.1.0 → 1.1.2
* Update: style.css : +173 lignes pour DnD (drag handle, drop indicators, drag overlay, undo/redo buttons, sr-announcement)

= 1.1.1 =
* New: CRUD complet des items dans le builder React
* New: Composant AddItemButton (dropdown avec 6 types : link, mega_container, column, widget, title, separator)
* New: Bouton "Ajouter un élément" en racine et dans chaque mega_container/column
* New: Suppression d'item (icône corbeille au survol + confirmation)
* New: Renommage inline par double-clic sur le label (Enter pour valider, Escape pour annuler)
* New: Panneau Propriétés entièrement éditable selon le type d'item :
  * link : label, url, target, icon, badge, visibility
  * mega_container : label, trigger, width
  * column : width (1-12)
  * widget : widget_type, label, et settings selon le type (html content, banner image, product_grid source/columns/limit, category_grid columns/images/counts)
  * title : label, badge
  * separator : (aucune propriété spécifique)
* New: Éditeur de badge (texte + couleur texte + couleur fond + bouton retirer)
* New: Édition du titre du menu (panneau propriétés quand aucun item sélectionné)
* New: Actions Redux addItem, updateItem, removeItem, moveItem dans le store wtm/menu
* New: Helpers generateId, findItem, mapItems, updateItemById, removeItemById, addChildToParent (immutables)
* Update: TreePanel refondu avec actions au survol (ajouter enfant, supprimer) et mode édition
* Update: PropertiesPanel refondu avec formulaires d'édition complets et panneau MenuProperties
* Update: Build bundle 14 Ko → 28.6 Ko (nouvelles fonctionnalités)

= 1.1.0 =
* New: Builder visuel React — squelette de l'application
* New: Page admin "Builder" full-screen (menu masqué, admin bar masquée)
* New: Layout 3 colonnes (arborescence / aperçu / propriétés) avec en-tête (titre + sélecteur device + bouton save)
* New: Stores @wordpress/data : wtm/menu (CRUD via REST) et wtm/ui (sélection, device, REST config)
* New: Communication avec l'API REST /wtm/v1/menus via @wordpress/api-fetch
* New: Pipeline build @wordpress/scripts (Webpack 5 + Babel + DependencyExtractionWebpackPlugin)
* New: webpack.config.js custom pour entry builder/index.js au lieu de src/index.js
* New: Style CSS du builder (~400 lignes) avec responsive
* New: Bouton "Builder" sur la liste des menus (ouvre le builder pour le menu sélectionné)
* New: package.json avec dépendances @wordpress/* (api-fetch, data, element, i18n, url) et devDependency @wordpress/scripts
* Update: Admin_Menu::enqueue_admin_styles() détecte la page Builder et charge le bundle
* Update: Nouvelle page Builder.php (sous-menu wtm-builder) qui rend le conteneur React

= 1.0.4 =
* New: Validation stricte par type d'item dans Schema_Validator (6 types : link, mega_container, column, widget, title, separator)
* New: Validation par type de widget (8 types : category_grid, product_grid, mini_cart, search, banner, html, custom_link, title) avec règles spécifiques par type
* New: Validation des modules header/footer (9 types : logo, menu, search, cart, button, html, social, newsletter, text)
* New: Validation des badges (text requis, color et background en hex)
* New: Validation de l'arborescence des layouts (rows → columns → modules)
* New: 30+ codes d'erreur explicites (wtm_link_missing_label, wtm_mega_invalid_trigger, wtm_widget_invalid_source, etc.)
* New: Méthode Schema_Validator::get_full_schema() retournant le schéma JSON Schema draft-04 complet avec definitions
* New: Endpoint GET /wtm/v1/menus/schema enrichi avec definitions (item, badge, layout, row, column, module) et listes de valeurs autorisées
* New: Documentation complète du schéma dans docs/schema.md (8 sections, 4 exemples complets)
* New: 57 tests unitaires PHP couvrant tous les cas valides et invalides (100% de réussite)
* Update: Rétro-compatibilité conservée — les configs v1.0.3 restent valides en v1.0.4

= 1.0.3 =
* New: API REST CRUD complète sous `/wp-json/wtm/v1/menus`
* New: Endpoint GET /wtm/v1/menus (liste avec filtres : search, menu_type, location, status, orderby, order, page, per_page)
* New: Endpoint GET /wtm/v1/menus/{id} (détail d'un menu)
* New: Endpoint POST /wtm/v1/menus (création avec validation JSON)
* New: Endpoint PUT/PATCH /wtm/v1/menus/{id} (mise à jour partielle)
* New: Endpoint DELETE /wtm/v1/menus/{id} (suppression avec retour de l'objet précédent)
* New: Endpoint POST /wtm/v1/menus/{id}/duplicate (duplication avec copie des méta)
* New: Endpoint GET /wtm/v1/menus/schema (schéma JSON complet)
* New: Classe Schema_Validator (validation _wtm_config, _wtm_header_config, _wtm_footer_config)
* New: Format de réponse propre (id, title, slug, status, menu_type, location, config, header_config, footer_config, version, dates, author, edit_url)
* New: En-têtes de pagination X-WP-Total et X-WP-TotalPages sur la liste
* New: En-tête Location sur les réponses 201 (create, duplicate)
* Update: Bootstrap instancie désormais Menu_Controller
* Security: Toutes les routes vérifient la capacité wtm_manage_menus

= 1.0.2 =
* New: Page "Tableau de bord" avec statistiques (menus totaux, actifs, brouillons, par type, par emplacement) et menus récents
* New: Page "Menus" avec table filtrable (type, statut, recherche) et 4 actions par ligne (modifier, dupliquer, activer/désactiver, supprimer)
* New: Page "Réglages" avec 7 onglets (général, styles, typographie, responsive, performance, analytics, permissions)
* New: Menu admin structuré avec 4 sous-menus (Dashboard, Menus, Réglages, À propos)
* New: Actions routées via admin_init (create_menu, delete_menu, duplicate_menu, toggle_status) avec nonces et vérifications de capacité
* New: Matrice de permissions rôles × capacités dans l'onglet Permissions
* New: ~120 lignes de CSS admin mutualisées (cards, badges, tables, tabs, boutons, formulaires)
* New: Dossier `versions/` avec documentation détaillée de chaque version (v1.0.0, v1.0.1, v1.0.2)
* Update: Page "À propos" refondue (classe statique, roadmap enrichie avec icônes, carte "Liens utiles" avec liens GitHub)
* Update: Bootstrap instancie désormais Admin_Menu (au lieu de Pages\About)

= 1.0.1 =
* New: Custom Post Type `wtm_menu` (REST-enabled, revision-enabled)
* New: 4 menu types (horizontal, vertical, offcanvas, footer)
* New: 4 default locations (primary, footer, sidebar, mobile) registered as theme nav_menu_locations
* New: 6 meta-keys with REST visibility & sanitization:
  - `_wtm_location` (string)
  - `_wtm_menu_type` (string)
  - `_wtm_config` (JSON)
  - `_wtm_header_config` (JSON, optional)
  - `_wtm_footer_config` (JSON, optional)
  - `_wtm_version` (integer)
* New: Meta-boxes on the wtm_menu edit screen (sidebar settings + JSON editor)
* New: `CPT_Manager` class with helpers `get_menu_types()` / `get_locations()`
* New: Filters `wtm_menu_types` / `wtm_locations` for extensibility
* Update: Bootstrap now instantiates CPT_Manager + Meta_Boxes
* Update: Cache invalidation triggered on menu save

= 1.0.0 =
* Initial release
* Plugin skeleton with PSR-4 autoloader
* Bootstrap class with dependency check (WooCommerce)
* Cache_Manager (object cache + transients)
* Permissions module with custom capabilities
* Default global settings (styles, typography, responsive, performance, analytics, permissions)
* Admin "About" page with roadmap and environment info
* Frontend Assets_Loader stub

== Upgrade Notice ==

= 1.0.0 =
Première version. Pose les fondations techniques du plugin Woo Total Menu.
