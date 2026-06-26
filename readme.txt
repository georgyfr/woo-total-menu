=== Woo Total Menu ===
Contributors: woo-total-menu-team
Tags: menu, mega menu, header, footer, woocommerce, navigation, builder
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.6
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

= 1.7.1 =
* Fix: **Détection des menus WordPress natifs** — le module `menu` du Header/Footer Builder n'acceptait que les `wtm_menu` (post_id). Désormais, un dropdown unifié liste aussi les `nav_menu` natifs créés via `Apparence → Menus` (`/wp-admin/nav-menus.php`). Nouveau setting `menu_source` (`"wtm"` par défaut, `"wp"` pour les menus natifs). 100 % rétro-compatible.
* New: **Route REST `GET /wtm/v1/wp-menus`** (`src/Api/WP_Menus_Controller.php`) — liste tous les `nav_menu` natifs avec `term_id`, `name`, `slug`, `count`, `locations` et `edit_url`. Permission : `wtm_manage_menus`.
* New: **Hooks `wtm_wp_nav_menu_args` et `wtm_wp_nav_menu_html`** — filtres pour surcharger respectivement les arguments `wp_nav_menu()` et le HTML rendu final d'un menu WP natif dans un module `menu`.
* Changed: **`Header_Footer_Renderer::render_module_menu()`** — détecte `menu_source` et délègue vers `wp_nav_menu()` (avec wrapper `<nav class="wtm-nav wtm-wp-nav">`) pour la source `"wp"`, ou vers `Menu_Renderer::render_by_id()` (comportement inchangé) pour `"wtm"`.
* Changed: **`ModuleProperties.js`** — nouveau composant `MenuModuleEditor` avec dropdown `<select>` groupant les deux sources via `<optgroup>`. Cache module-level pour la session.
* Changed: **`Preview_Controller.php`** — label de prévisualisation différencié `[Menu WP #N]` vs `[Menu WTM #N]`.
* Changed: **Plugin version** — bump `1.7.0` → `1.7.1` (en-tête + constante `WTM_VERSION` + `package.json`).

= 1.7.0 =
* New: **Menus conditionnels** — `Condition_Evaluator` (`src/Core/Condition_Evaluator.php`, ~330 lignes). 10 types de règles de visibilité (`page_type`, `post_id`, `post_type`, `taxonomy`, `user_state`, `user_role`, `device`, `date_range`, `url_param`, `language`). Logique ET (toutes) ou OU (au moins une) avec court-circuit. Cache par requête. Validation statique via `Condition_Evaluator::validate()`.
* New: **Analytics simple** — `Analytics` (`src/Core/Analytics.php`, ~250 lignes). Stockage privacy-friendly (pas d'IP, pas d'user ID, pas de cookie). Compteurs agrégés par jour stockés dans l'option `wtm_analytics_{YYYY-MM-DD}`. 3 types d'événements (`view`, `click`, `hover`). Filtrage par utilisateur connecté. Méthode `cleanup($days)` pour purge auto après 90 jours.
* New: **API REST `/wtm/v1/menus/{id}/conditions`** (`src/Api/Conditions_Controller.php`, ~260 lignes) — 4 endpoints : `GET` (lecture), `PUT` (remplacement), `DELETE` (effacement), `POST /conditions/test` (évaluation contre la requête courante avec rapport par règle).
* New: **API REST `/wtm/v1/analytics`** (`src/Api/Analytics_Controller.php`, ~190 lignes) — 2 endpoints : `POST /track` (public, nonce-gated) pour soumettre un événement, `GET /stats` (admin-only) avec filtres `start`/`end`/`menu_id`/`event`/`group_by`.
* New: **Panneau Builder "Conditions"** (`builder/components/ConditionsPanel.js`, ~280 lignes) — nouvelle modale accessible depuis la barre d'outils supérieure du Builder (icône `dashicons-shield-alt`). Éditeur de règles avec menus déroulants pour les enums et inputs libres pour les autres types. Sélecteur ET/OU. Bouton "Tester sur la page courante". Bouton "Effacer toutes les conditions".
* New: **Page admin Analytics** (`src/Admin/Pages/Analytics_Page.php`, ~210 lignes) — dashboard `/wp-admin/admin.php?page=wtm-analytics`. 3 cartes KPI (vues, clics, CTR). Chart HTML/CSS pur (pas de JS) des 7/14/30/90 derniers jours. Tableau "par menu" avec CTR. Filtres par période et par menu.
* New: **Tracking JS frontend** (`assets/front/wtm-frontend.js`, +85 lignes) — module `initAnalytics()`. Événement `view` au chargement (une fois par menu portant `data-wtm-menu-id`). Événement `click` via `navigator.sendBeacon` (survit à la navigation). Fallback `fetch()` avec `keepalive: true`.
* New: **Méta `_wtm_conditions`** — enregistrée via `register_post_meta()` avec `revisions_enabled = true`. Sanitization custom via `Meta_Boxes::sanitize_conditions()`. Copiée dans les révisions WordPress et restaurée lors d'un restore. Copiée lors d'un duplicate de menu.
* New: **Attributs data sur le frontend** — `data-wtm-menu-id="{ID}"` ajouté au `<nav>` du menu. `data-wtm-item-id="{ID}"` ajouté aux `<a>` des items `link` (méthode `Menu_Renderer::item_id_attr()` qui produit un hash numérique stable de `label|url` si l'item n'a pas d'ID explicite).
* New: **3 hooks/filters développeur** — `wtm_condition_result` (filter, surcharge du résultat final), `wtm_condition_rule_{type}` (filter, évaluation de types custom), `wtm_analytics_recorded` (action, post-enregistrement d'événement).
* Changed: **`Settings.php` — onglet Analytics** : les checkboxes `enabled` et `track_logged` ne sont plus `disabled`. Ajout d'un bouton "Voir le tableau de bord analytics".
* Changed: **`Menu_Controller`** — `format_item()` expose `conditions`. `create_menu()` et `update_menu()` acceptent un paramètre `conditions` optionnel (validé via `Condition_Evaluator::validate()`).
* Changed: **`Location_Interceptor`** consulte le `Condition_Evaluator` avant de remplacer un `wp_nav_menu()`. Si les conditions ne matchent pas, le thème retombe sur son walker natif.
* Changed: **`Header_Footer_Injector`** consulte le `Condition_Evaluator` avant d'injecter le header et le footer.
* Changed: **`Assets_Loader`** passe la config analytics au JS frontend via `wp_localize_script`.
* Changed: **`Admin_Menu`** enregistre la nouvelle page `wtm-analytics` (capacité `wtm_view_analytics`).
* Changed: **`About.php`** — roadmap v1.7.x marquée comme `done`.
* Changed: **Plugin version** — bump `1.6.0` → `1.7.0` (en-tête + constante `WTM_VERSION` + `package.json`).
* Privacy: Aucune donnée personnelle n'est collectée par le module Analytics (ni IP, ni user-agent, ni identifiant). Conforme RGPD et CCPA sans nécessiter de bannière de consentement.

= 1.6.0 =
* New: **Rôles personnalisés** — `Roles_Manager` (`src/Core/Roles_Manager.php`, ~370 lignes). Création, mise à jour et suppression de rôles WordPress dédiés au plugin (préfixe `wtm_` automatique). Protection des 5 rôles WordPress standards contre la suppression. Réassignation automatique des utilisateurs vers `subscriber` lors de la suppression d'un rôle custom. Stockage de la liste des rôles custom dans l'option `wtm_custom_roles`.
* New: **API REST `/wtm/v1/roles`** (`src/Api/Roles_Controller.php`, ~280 lignes) — 5 endpoints : `GET /roles` (liste), `GET /roles/{slug}` (détail), `POST /roles` (création), `PUT /roles/{slug}` (maj caps), `DELETE /roles/{slug}/delete` (suppression). Toutes requièrent `wtm_manage_settings`.
* New: **3 blocs Gutenberg** (`src/Integration/Gutenberg_Blocks.php`, ~290 lignes) — `wtm/menu`, `wtm/header`, `wtm/footer` server-rendered via `register_block_type`. Sidebar `SelectControl` pour choisir un menu WTM publié. Placeholder HTML si non configuré. Editor JS `blocks/index.js` (~210 lignes).
* New: **Intégration Elementor** (`src/Integration/Elementor_Integration.php` + `Elementor_Widget.php`, ~265 lignes) — widget "Woo Total Menu" (icône `eicon-nav-menu`, catégorie `general`). Contrôle SELECT pour le menu. Lazy-loaded si Elementor actif. Widget dans fichier séparé pour éviter le chargement de `\Elementor\Widget_Base` quand Elementor est absent.
* New: **Intégration Bricks** (`src/Integration/Bricks_Integration.php`, ~140 lignes) — élément custom `wtm-menu` enregistré via `bricks/builder/elements`. Hook de rendu `bricks/element/render/wtm-menu`. Lazy-loaded si Bricks actif.
* New: **Intégration Oxygen** (`src/Integration/Oxygen_Integration.php`, ~165 lignes) — 3 shortcodes additionnels `[wtm_header id="42"]`, `[wtm_footer id="42"]`, `[wtm_oxygen_menu id="42"]` + helper PHP `wtm_oxygen_render_menu($menu_id)`.
* New: **Multisite** — `Multisite_Manager` (`src/Core/Multisite_Manager.php`, ~210 lignes). Activation réseau initialise tous les blogs existants. Hook `wpmu_new_blog` initialise les nouveaux blogs créés. `for_each_blog($callback)` helper. `get_network_stats()` statistiques réseau. Désactivation/désinstallation réseau-aware.
* New: **8 hooks/filters développeur** — `wtm_role_created`, `wtm_role_updated`, `wtm_role_deleted`, `wtm_activated`, `wtm_deactivated`, `wtm_network_activated`, `wtm_multisite_blog_setup`, `wtm_multisite_blog_cleanup`.
* Changed: **Bootstrap** — `on_activate($network_wide)` / `on_deactivate($network_wide)` multisite-aware. Instanciation des nouvelles classes (Roles_Controller, Gutenberg_Blocks, 3 intégrations page-builders). Hook `wpmu_new_blog` enregistré.
* Changed: **Webpack config** — ajout entry point `blocks: './blocks/index.js'`. Bundle `build/blocks.js` (3.99 KiB) + `build/style-blocks.css` (334 o).
* Changed: **Plugin version** — bump `1.5.0` → `1.6.0` (en-tête + constante `WTM_VERSION` + `package.json`).
* Tests: 30/30 backend tests passent via `/home/z/my-project/scripts/test-v1.6.0-endpoint.php`.

= 1.5.0 =
* New: **Système de templates intégrés** (spec §1.4.2) — 12 templates prêts à l'emploi, répartis en 4 menus + 4 headers + 4 footers. Catégorisation métier : ecommerce (5), minimal (4), blog (1), corporate (1), electronics (1).
* New: **4 templates de menus** : Menu horizontal simple (4 liens plats — blog/vitrine), Méga menu boutique de mode (2 méga containers Femmes/Hommes + product_grid + banner), Méga menu électronique (3 méga containers Smartphones/Ordinateurs/TV + bannière promo), Menu vertical sidebar (3 catégories + widget filtres WooCommerce).
* New: **4 templates de headers** : Header e-commerce classique (Logo | Menu | Recherche + Panier + Compte), Header minimaliste (Logo | Menu, 2 colonnes), Header promotionnel (top bar promo + ligne main), Header sticky centré (logo centré + menu centré, style éditorial/luxe).
* New: **4 templates de footers** : Footer e-commerce 4 colonnes (about + 2 menus + newsletter), Footer minimaliste (copyright + social), Footer corporate 4 colonnes (logo + 3 menus), Footer sombre accessible WCAG AA (4 cols + bottom bar RGPD).
* New: **Backend `Template_Registry`** (`src/Core/Template_Registry.php`, ~840 lignes) — catalogue statique lazy-built, validation via `Schema_Validator::validate_config()` ou `validate_layout()`, sélection automatique de la meta cible (`_wtm_config` / `_wtm_header_config` / `_wtm_footer_config`), filtre `wtm_templates_catalog` pour extension tierce, action `wtm_template_applied` après application.
* New: **API REST `/wtm/v1/templates`** (`src/Api/Templates_Controller.php`, ~330 lignes) — 3 endpoints : `GET /templates` (liste filtrable par type/category/search), `GET /templates/{id}` (détail avec config), `POST /templates/{id}/apply` (applique à un menu). Lecture publique, écriture réservée à `edit_posts`.
* New: **Builder React — Galerie de templates** — nouveau bouton "Galerie de templates" dans le Header (icône dashicons-layout). Modal avec toolbar (tabs Tous/Menus/Headers/Footers), recherche plein-texte, filtre par catégorie. `TemplateCard` affiche un mini-aperçu CSS synthétique (10 variants), nom, description, tags et bouton "Appliquer". Pré-filtre automatique selon le mode actif. Confirmation avant application. Fermeture Escape sauf si application en cours.
* New: **Store Redux `wtm/templates`** (`builder/stores/templates.js`, ~260 lignes) — cache du catalogue, états de filtre (`filterType`/`filterCategory`/`filterSearch`), états d'application (`isApplying`/`applyError`/`lastApplied`), sélecteur `getFilteredTemplates`, action async `applyTemplate(id)` qui POST → reload menu → reload layout → ferme la galerie.
* New: **Store `wtm/ui` étendu** — nouvel état `isTemplatesOpen` + actions `openTemplates()` / `closeTemplates()`.
* New: **2 hooks/filters développeurs** — `wtm_templates_catalog` (filter, modifie le catalogue) + `wtm_template_applied` (action, déclenchée après application).
* Update: +480 lignes CSS Builder pour la galerie (modal, cards, mini-previews CSS pour 10 types de thumbnails, responsive mobile 768px).
* Update: Version bumpée à 1.5.0 dans `woo-total-menu.php`, `package.json`, `readme.txt`.
* Update: `About.php` — roadmap v1.5.x marquée `done`. `versions/README.md` — entrée v1.5.0 dans le sommaire.

= 1.4.0 =
* New: **Header & Footer Builder visuel** (spec §3.6, §3.7, §4.6.5) — nouveau mode "Header" et "Footer" dans le Builder React accessible via 3 tabs Menu / Header / Footer dans l'en-tête. Layout 3-colonnes mode-aware : ModulePalette (gauche) + LayoutCanvas (centre) + ModuleProperties (droite) en mode header/footer, layout classique TreePanel + PreviewPanel + PropertiesPanel en mode menu. Grille visuelle rows → columns → modules avec resize des colonnes (largeur 1-12), drag-and-drop HTML5 des modules, déplacement des modules entre colonnes.
* New: **9 types de modules** disponibles dans le Builder Header/Footer — `logo` (image + lien accueil), `menu` (rend un menu wtm_menu existant), `search` (barre de recherche avec live suggestions), `cart` (mini-panier WooCommerce avec drawer AJAX), `button` (CTA), `html` (HTML libre), `social` (icônes réseaux sociaux), `newsletter` (formulaire email AJAX), `text` (texte / copyright avec shortcode `[year]`).
* New: **2 nouvelles classes PHP frontend** — `Header_Footer_Renderer` (~838 lignes, walker PHP pur rows→columns→modules qui produit du HTML sémantique `<header>`/`<footer>`, réutilise les renderers widgets v1.3 pour search/cart/social/newsletter) et `Header_Footer_Injector` (~288 lignes, hooks `wp_body_open` priority 10 + `wp_footer` priority 20 pour injecter automatiquement le header et le footer globaux sur toutes les pages).
* New: **Settings `header_footer`** (spec §9.5.2) — section "Header & Footer" dans Réglages → Woo Total Menu avec 5 champs : `enabled` (master toggle, défaut false opt-in), `header_menu_id`, `footer_menu_id`, `hide_theme_header`, `hide_theme_footer`. Masquage CSS optionnel du header/footer natif du theme.
* New: **REST API étendue** — `GET /wtm/v1/menus/{id}` retourne désormais `header_config` et `footer_config` décodés. `POST /wtm/v1/menus/{id}` accepte `header_config` et `footer_config` validés via `Schema_Validator::validate_layout()`. 2 meta post `_wtm_header_config` et `_wtm_footer_config` enregistrés via `register_meta()` et versionnés dans les révisions WordPress.
* New: **`Schema_Validator::validate_layout()`** — nouvelle méthode statique qui valide la structure complète d'un layout header/footer (version, rows, columns, modules) avec messages d'erreur précis et chemin (ex: `rows[0].columns[1].modules[2] doit avoir un champ "type" dans: logo, menu, search, …`). `MODULE_TYPES` = 9 types valides.
* New: **Layout store Redux dédié** (`builder/stores/layout.js`, ~640 lignes) — store `wtm/layout` avec états séparés `state.header` et `state.footer`. 14 actions (loadFromMenu, addRow, updateRow, removeRow, moveRow, addColumn, updateColumn, removeColumn, addModule, updateModule, removeModule, moveModule, selectElement, clearSelection) + 5 sélecteurs (getLayout, getSelectedElementId, getSelectedElementType, isDirty, getHeaderConfig, getFooterConfig).
* New: **5 hooks/filters pour développeurs** — `wtm_header_inject_hook` (filter, override du hook d'injection header ex: `storefront_header`), `wtm_footer_inject_hook` (filter), `wtm_before_header` (action), `wtm_before_footer` (action), `wtm_layout_module_html` (filter).
* New: **`Assets_Loader` étendu** — nouvelle méthode `is_header_footer_active()` qui force l'enqueue des assets frontend même si aucun menu n'a encore été rendu (car `wp_body_open` / `wp_footer` se déclenchent après `wp_enqueue_scripts`).
* New: **Preview iframe étendu** — `Preview_Controller` gère désormais les modes header et footer avec `renderModule()`, `renderColumn()`, `renderHeader()` et `renderFooter()` qui produisent un aperçu compact de chaque module type.
* New: ~361 lignes CSS frontend pour les styles `.wtm-header` / `.wtm-footer` (layout grid 12 cols via flex, sticky header, responsive 768px). ~546 lignes CSS Builder pour le layout 3-col mode-aware, ModulePalette, LayoutCanvas et ModuleProperties.
* Fix: `Header_Footer_Renderer::render_module_search()` n'ajoutait pas les attributs `data-wtm-live-search` et `data-min-chars` sur l'input quand `live_suggestions: true` — la recherche live ne fonctionnait pas dans les headers/footers. Fix : ajout conditionnel des attributs + classe `wtm-search--live` + attribut `data-wtm-suggestions` sur la div suggestions.
* Fix: `Header_Footer_Renderer::render_module_newsletter()` ne générait pas les attributs attendus par `initNewsletter()` du JS frontend (`data-provider`, `data-list-id`, `data-wtm-newsletter-message`, `<script data-wtm-newsletter-config>`). Fix : alignement complet du rendu sur le widget v1.3.
* Update: `Bootstrap::init_services()` instancie désormais `hf_renderer` et `hf_injector` sur chaque requête (pas seulement `is_admin`).
* Update: `Assets_Loader::__construct()` écoute les actions `wtm_before_header` et `wtm_before_footer` pour marquer les assets comme nécessaires.
* Update: Builder `App.js` est mode-aware et propage le dirty state du store `wtm/layout` vers le store `wtm` (menu) pour activer le bouton Save. Builder `Header.js` affiche 3 tabs Menu / Header / Footer.
* Update: Bump version 1.3.0 → 1.4.0 (woo-total-menu.php, package.json, readme.txt)

= 1.3.0 =
* New: 4 nouveaux widgets WooCommerce avancés — `recent_posts` (articles WordPress en grille), `social_icons` (icônes réseaux sociaux avec glyphes SVG inline Facebook/Twitter/Instagram/LinkedIn/YouTube/Pinterest/GitHub), `newsletter` (formulaire d'abonnement email avec AJAX + nonce + providers internal/mailchimp/none), `filters` (filtres WooCommerce layered nav : catégories/prix/attributs).
* New: Widget `mini_cart` upgraded — nouveau mode `display_mode: 'drawer'` qui ouvre un panneau latéral AJAX avec le contenu du panier (items, quantités, total, boutons Voir le panier/Commander). Récupère les données via la route REST `/wtm/v1/mini-cart-contents`. Refresh automatique quand les WC cart fragments sont mis à jour.
* New: Widget `search` upgraded — nouvelle option `live_suggestions` qui active un dropdown de suggestions produits en AJAX (debounce 250 ms, minimum 2-5 caractères configurable). Navigation clavier (ArrowUp/ArrowDown/Escape) + aria-role listbox.
* New: 2 routes REST publiques — `GET /wtm/v1/search-suggest?s=…&limit=5` (recherche produits par relevance, retourne id/title/permalink/price_html/thumbnail/on_sale) et `GET /wtm/v1/mini-cart-contents` (contenu complet du panier pour le drawer).
* New: 1 admin-ajax handler — `wtm_newsletter_subscribe` (vérifie le nonce `wtm_newsletter`, valide l'email, stocke dans l'option `wtm_newsletter_subscribers` avec IP anonymisée GDPR-friendly pour le provider internal, déclenche les hooks `wtm_newsletter_subscription_handled` et `wtm_newsletter_subscribed` pour intégrations tierces Mailchimp).
* New: `WIDGET_SUBTYPES` picker dans AddItemButton.js — quand l'utilisateur choisit "Widget", un panneau secondaire s'ouvre avec les 12 sous-types de widgets (html, banner, custom_link, product_grid, category_grid, mini_cart, search, recent_posts, social_icons, newsletter, filters, title), chacun avec icône + description + defaults spécifiques.
* New: Inspecteurs PropertiesPanel.js pour les 7 nouveaux types de widgets — chaque widget a son propre formulaire d'édition (mode d'affichage + position pour mini_cart, suggestions live + min_chars pour search, tri + colonnes + image/date/extrait pour recent_posts, liste dynamique réseaux sociaux pour social_icons, provider + layout + message de succès pour newsletter, filtres catégories/prix/attributs pour filters, etc.).
* New: ~570 lignes CSS frontend pour les nouveaux composants — drawer latéral (header/body/footer/overlay), dropdown de suggestions (image+titre+prix+badge promo), cards articles récents (media+title+date+extrait), icônes sociales (CSS mask SVG inline + couleurs hover par réseau), formulaire newsletter (inline/stacked + états success/error), filtres WooCommerce (select/price range/checkboxes/actions) + responsive 768px.
* New: ~430 lignes JS frontend pour les 3 nouveaux modules — `initCartDrawer()` (création dynamique du drawer + overlay si non présent, fetch REST, render items/total/actions, focus management, Escape close, refresh auto sur wc_fragments_refreshed), `initLiveSearch()` (debounce 250 ms, fetch REST, render dropdown, navigation clavier, blur delay pour clic), `initNewsletter()` (FormData POST vers admin-ajax, validation email, gestion états success/error, reset form).
* New: 4 nouveaux hooks filters pour développeurs — `wtm_search_suggest_query` (filtre la WP_Query du search-suggest), `wtm_newsletter_subscription_handled` (court-circuite le stockage internal pour déléguer à Mailchimp), `wtm_newsletter_subscribed` (action post-subscription pour sync CRM).
* New: `wp_localize_script` étendu — passe désormais `restUrl`, `restNonce`, `newsletterNonce` au JS frontend (en plus de `ajaxUrl`, `breakpoint`, `i18n`, `wooCartFragments`). 6 nouvelles clés i18n (openCart, closeCart, cartEmpty, viewCart, checkout, noResults, searching, subscribing, invalidEmail).
* Update: `Schema_Validator::WIDGET_TYPES` étendu de 8 à 12 types. Validation stricte par widget_type pour les 4 nouveaux (recent_posts: limit/columns/orderby/show_image/show_date/show_excerpt, social_icons: items[]/size, newsletter: provider/layout, filters: show_categories/show_price/show_attributes/attributes/columns) + extension des validators existants pour mini_cart (display_mode/drawer_position) et search (live_suggestions/min_chars).
* Update: `Menu_Renderer.php` étendu (~430 lignes) — 4 nouvelles méthodes `render_widget_recent_posts`, `render_widget_social_icons`, `render_widget_newsletter`, `render_widget_filters` + helpers `render_post_card`. Upgrade `render_widget_mini_cart` (mode drawer génère un `<button data-wtm-cart-drawer>` au lieu d'un `<a>`) et `render_widget_search` (option `live_suggestions` ajoute `data-wtm-live-search` + conteneur suggestions).
* Update: `Bootstrap.php` instancie le nouveau `Frontend_Controller` (REST + admin-ajax) sur chaque requête.
* Update: `Assets_Loader.php` enrichit le `wp_localize_script` avec `restUrl`, `restNonce`, `newsletterNonce` + 6 nouvelles clés i18n.
* Update: `AddItemButton.js` refactorisé — quand l'utilisateur clique sur "Widget", un sous-panneau s'ouvre avec les 12 sous-types (au lieu d'ajouter directement un widget html par défaut).
* Update: Bump version 1.2.0 → 1.3.0 (woo-total-menu.php, package.json, readme.txt)
* Security: Nonce `wtm_newsletter` vérifié sur le handler newsletter (mitigue spam). Emails stockés avec IP anonymisée (dernier octet IPv4 /64 IPv6 — GDPR). Échappement systématique sur tout le rendu (esc_url/esc_html/esc_attr/wp_kses_post).

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
