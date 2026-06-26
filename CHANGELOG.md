# Changelog

All notable changes to Woo Total Menu will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.4] - 2026-06-26

### Added

- **Dossier `build/` avec les assets compilés** : le Builder React (Builder visuel glisser-déposer)
  est maintenant compilé et inclus dans le dépôt. Sans ces fichiers, l'interface d'administration
  du plugin était vide/inaccessible après installation.

### Changed

- **Nettoyage de `package.json`** : suppression de `@dnd-kit/modifiers` qui n'était importé
  nulle part dans le code source.

## [1.7.3] - 2026-06-26

### Fixed

- **Suppression de tous les `apiFetch.createNonceMiddleware()` dans le Builder JS** : l'appel
  répété à `apiFetch.use()` empilait les middlewares de nonce à chaque requête REST, causant
  des comportements imprévisibles et des erreurs d'authentification. Le nonce est maintenant
  passé directement via le header `X-WP-Nonce` dans chaque appel `apiFetch()` individuel.
  Fichiers corrigés :
  - `builder/stores/templates.js` (2 occurrences)
  - `builder/stores/menu.js` (6 occurrences)
  - `builder/components/ModuleProperties.js` (1 occurrence)
  - `builder/components/ConditionsPanel.js` (4 occurrences)

## [1.7.2] - 2026-06-26

### Fixed

- **Erreur de syntaxe PHP critique dans `Menus_List.php`** : l'opérateur de concaténation
  `.` manquait après `esc_html__()` dans le message de succès de duplication de menu
  (`);'` au lieu de `) . '`). Cette erreur provoquait un `ParseError` qui
  empêchait le chargement complet de la page de liste des menus.

## [1.7.1] - 2026-06-26

### Added

- **Détection des menus WordPress natifs dans le module `menu`** du
  Header/Footer Builder. Auparavant, le module `menu` n'acceptait que les
  `wtm_menu` (CPT du plugin) via leur `post_id` saisi manuellement. Les
  menus WordPress natifs créés via `Apparence → Menus`
  (`/wp-admin/nav-menus.php`) n'étaient pas détectés.

  Désormais, le module `menu` expose un **dropdown unifié** présentant :

  - les `wtm_menu` posts (post_id) ;
  - les `wp_nav_menu` natifs (term_id de la taxonomy `nav_menu`).

  La sélection est stockée via deux settings complémentaires :

  - `menu_source` : `"wtm"` (défaut, rétro-compatible) ou `"wp"` ;
  - `menu_id` : `post_id` (wtm) ou `term_id` (wp).

  Côté frontend, le renderer `Header_Footer_Renderer::render_module_menu()`
  détecte le `menu_source` et délègue vers `wp_nav_menu()` (avec les classes
  `wtm-nav wtm-wp-nav`) lorsque la source est `"wp"`, ou vers
  `Menu_Renderer::render_by_id()` (comportement inchangé) pour `"wtm"`.

- **Nouvelle route REST `GET /wtm/v1/wp-menus`**
  (`src/Api/WP_Menus_Controller.php`) :
  - Liste tous les `nav_menu` natifs avec `id` (term_id), `name`, `slug`,
    `count`, `locations` et `edit_url`.
  - Retourne également les emplacements enregistrés via
    `get_registered_nav_menus()`.
  - Permission : `wtm_manage_menus`.

- **Hook `wtm_wp_nav_menu_args`** (filter) — permet de surcharger les
  arguments passés à `wp_nav_menu()` lors du rendu d'un menu natif
  dans un module `menu` du Header/Footer Builder.

- **Hook `wtm_wp_nav_menu_html`** (filter) — permet de filtrer le HTML
  final rendu par `wp_nav_menu()` pour un module `menu` de source `"wp"`.

### Changed

- `Header_Footer_Renderer::render_module_menu()` accepte désormais un
  setting `menu_source` (`"wtm"` par défaut pour préserver la
  rétro-compatibilité). Si `menu_source` est absent et que `menu_id`
  correspond à un `wtm_menu` post publié, le rendu reste identique aux
  versions précédentes.

- `ModuleProperties.js` — la section `case 'menu'` est désormais gérée
  par un composant dédié `MenuModuleEditor` qui :
  - fetch en parallèle `/wtm/v1/menus?per_page=100&status=any` et
    `/wtm/v1/wp-menus` (cache module-level pour la session) ;
  - affiche un `<select>` avec deux `<optgroup>` (WTM + WP natifs) ;
  - masque le champ "Emplacement" pour les menus WP (qui n'en ont pas
    besoin — le `wp_nav_menu()` est appelé directement avec le term).

- `Preview_Controller.php` — la prévisualisation du module `menu`
  dans l'iframe affiche désormais `[Menu WP #N]` ou `[Menu WTM #N]`
  selon la source, pour distinguer visuellement les deux types.

### Files Modified

- `woo-total-menu.php` — bump `WTM_VERSION` à `1.7.1`.
- `package.json` — bump version à `1.7.1`.
- `src/Bootstrap.php` — enregistrement du service `rest_wp_menus`.
- `src/Frontend/Header_Footer_Renderer.php` — `render_module_menu()`
  + nouvelle méthode privée `render_wp_nav_menu()`.
- `src/Frontend/Preview_Controller.php` — label de prévisualisation
  différencié.
- `builder/components/ModuleProperties.js` — nouveau composant
  `MenuModuleEditor` + hook `useAvailableMenus` + cache
  `_availableMenusCache`.
- `builder/style.css` — styles pour `.wtm-field-hint` et les
  `<optgroup>` du dropdown.
- `build/index.js` + `build/style-index.css` — rebuild webpack.

### Files Added

- `src/Api/WP_Menus_Controller.php` — nouveau contrôleur REST.
- `versions/v1.7.1.md` — présente release note.

### Backward Compatibility

- 100 % rétro-compatible : un `wtm_menu` existant avec un header/footer
  config qui utilise un module `menu` sans `menu_source` continue de
  fonctionner comme avant (défaut = `"wtm"`).
- Aucune migration DB nécessaire.

## [1.7.0] - 2026-06-26

### Added

- **Menus conditionnels** — `Condition_Evaluator`
  (`src/Core/Condition_Evaluator.php`, ~330 lignes) :
  - Évaluateur runtime de règles de visibilité attachées à un `wtm_menu`.
  - 10 types de règles : `page_type`, `post_id`, `post_type`, `taxonomy`,
    `user_state`, `user_role`, `device`, `date_range`, `url_param`, `language`.
  - Logique de combinaison `all` (ET) ou `any` (OU) avec court-circuit.
  - Cache par requête (résultats mémorisés par menu_id).
  - Validation statique via `Condition_Evaluator::validate()` (utilisable
    côté REST avant sauvegarde).
  - Hooks : `wtm_condition_result` (filter), `wtm_condition_rule_{type}`
    (filter pour types custom).

- **Analytics simple** — `Analytics` (`src/Core/Analytics.php`,
  ~250 lignes) :
  - Stockage privacy-friendly (pas d'IP, pas d'user ID, pas de cookie).
  - Compteurs agrégés par jour stockés dans l'option `wtm_analytics_{YYYY-MM-DD}`.
  - 3 types d'événements : `view`, `click`, `hover`.
  - Filtrage par utilisateur connecté (`track_logged` setting).
  - Méthode `cleanup($days)` pour purger les données anciennes (90 jours par défaut).
  - Hook : `wtm_analytics_recorded` (action post-enregistrement).

- **API REST — `Conditions_Controller`**
  (`src/Api/Conditions_Controller.php`, ~260 lignes) :
  - 4 routes REST sous `/wtm/v1/menus/{id}/conditions` :
    - `GET /conditions` — lecture des conditions courantes.
    - `PUT /conditions` — remplacement (validation + sanitization).
    - `DELETE /conditions` — effacement.
    - `POST /conditions/test` — évaluation contre la requête courante
      (retourne un rapport par règle + résultat global).

- **API REST — `Analytics_Controller`**
  (`src/Api/Analytics_Controller.php`, ~190 lignes) :
  - 2 routes REST sous `/wtm/v1/analytics` :
    - `POST /track` — endpoint public (nonce-gated) pour soumettre un événement.
    - `GET /stats` — endpoint admin-only avec filtres `start`, `end`,
      `menu_id`, `event`, `group_by` (`day` | `menu` | `event`).

- **Panneau Builder "Conditions"** (`builder/components/ConditionsPanel.js`,
  ~280 lignes) :
  - Nouvelle modale accessible depuis la barre d'outils supérieure du Builder
    (icône `dashicons-shield-alt`).
  - Éditeur de règles avec menus déroulants pour les enums (`page_type`,
    `user_state`, `device`) et inputs libres pour les autres types.
  - Sélecteur de logique ET/OU (radio buttons).
  - Bouton **Tester sur la page courante** qui appelle `/conditions/test`
    et affiche le rapport détaillé par règle.
  - Bouton **Effacer toutes les conditions** pour réinitialiser.
  - État `isConditionsOpen` dans le store `wtm/ui` (Redux) avec actions
    `openConditions()` / `closeConditions()`.

- **Page admin Analytics** (`src/Admin/Pages/Analytics_Page.php`, ~210 lignes) :
  - Nouvelle page sous `/wp-admin/admin.php?page=wtm-analytics`.
  - 3 cartes KPI : vues totales, clics totaux, CTR.
  - Chart HTML/CSS pur (pas de JS, pas de dépendance) des 7/14/30/90
    derniers jours avec barres view + click côte à côte.
  - Tableau "par menu" avec vues, clics et CTR.
  - Filtre par période + par menu.
  - Bloc "Confidentialité" expliquant l'approche RGPD/CCPA.

- **Tracking JS frontend** (`assets/front/wtm-frontend.js`, +85 lignes) :
  - Nouveau module `initAnalytics()` exécuté au `DOMContentLoaded`.
  - Événement `view` : déclenché une fois par menu portant
    `data-wtm-menu-id` au chargement de la page.
  - Événement `click` : déclenché lors du clic sur un élément portant
    `data-wtm-item-id`, via `navigator.sendBeacon` (survit à la navigation).
  - Fallback `fetch()` avec `keepalive: true` pour les navigateurs sans
    `sendBeacon`.
  - Configuration passée via `wtmFrontend.analytics` (URL + nonce).

- **Méta `_wtm_conditions`** :
  - Enregistrée via `register_post_meta()` avec `revisions_enabled = true`.
  - Sanitization custom via `Meta_Boxes::sanitize_conditions()` qui
    délègue à `Condition_Evaluator::validate()`.
  - Copiée automatiquement dans les révisions WordPress.
  - Restaurée lors d'un restore de révision.
  - Copiée lors d'un duplicate de menu (action `duplicate_menu`).

- **Attributs data sur le frontend** :
  - `data-wtm-menu-id="{ID}"` ajouté au `<nav>` du menu (via `Menu_Renderer`).
  - `data-wtm-item-id="{ID}"` ajouté aux `<a>` des items `link`
    (via la nouvelle méthode `Menu_Renderer::item_id_attr()`).
  - L'ID d'item est calculé comme un hash numérique stable de `label|url`
    si l'item n'a pas d'ID explicite.

- **Hooks développeur** :
  - `wtm_condition_result` (filter) — surcharge du résultat d'évaluation.
  - `wtm_condition_rule_{type}` (filter) — évaluation de types custom.
  - `wtm_analytics_recorded` (action) — post-enregistrement d'événement.

### Changed

- **`Settings.php` — onglet Analytics** : les checkboxes `enabled` et
  `track_logged` ne sont plus `disabled`. Le message "Module disponible à
  partir de la v1.7.1" est remplacé par une description fonctionnelle.
  Un bouton "Voir le tableau de bord analytics" a été ajouté.

- **`Menu_Controller::format_item()`** : expose maintenant `conditions`
  dans la réponse JSON de chaque menu (décodé depuis la méta `_wtm_conditions`).

- **`Menu_Controller::create_menu()` et `update_menu()`** acceptent
  maintenant un paramètre `conditions` optionnel (validé via
  `Condition_Evaluator::validate()`).

- **`Location_Interceptor::intercept()`** consulte le `Condition_Evaluator`
  avant de remplacer un `wp_nav_menu()`. Si les conditions ne matchent pas,
  le thème retombe sur son walker natif.

- **`Header_Footer_Injector::inject_header()` et `inject_footer()`**
  consultent le `Condition_Evaluator` avant d'injecter le HTML.

- **`Assets_Loader::maybe_enqueue()`** passe la config analytics au JS
  frontend via `wp_localize_script('wtmFrontend', 'analytics', ...)`.

- **`Admin_Menu::register_menu()`** enregistre la nouvelle page
  `wtm-analytics` (capacité `wtm_view_analytics`).

- **`About.php`** : roadmap v1.7.x marquée comme `done`.

- **`Bootstrap.php`** : enregistre `rest_conditions` et `rest_analytics`
  dans le container de services.

### Removed

- Aucun retrait. La v1.7.0 est une release d'ajout pur.

### Compatibility

- Aucune migration DB nécessaire (la nouvelle méta `_wtm_conditions` est
  créée à la volée).
- Les menus existants sans conditions continuent à s'afficher comme avant.
- Le module Analytics est opt-in (désactivé par défaut).
- PHP 7.4+ requis (inchangé).
- WordPress 6.4+ requis (inchangé).

## [1.6.0] - 2026-06-26

### Added

- **Rôles personnalisés** — `Roles_Manager` (`src/Core/Roles_Manager.php`,
  ~370 lignes) :
  - Création, mise à jour et suppression de rôles WordPress personnalisés
    dédiés au plugin (préfixe `wtm_` automatique).
  - API statique : `get_all_roles()`, `get_role($slug)`, `create_role($slug,
    $name, $caps)`, `update_role_caps($slug, $caps)`, `delete_role($slug)`,
    `is_custom_role($slug)`, `get_custom_role_slugs()`.
  - Protection : les 5 rôles WordPress standards ne peuvent jamais être
    supprimés. Administrator a toujours toutes les caps WTM.
  - Réassignation automatique des utilisateurs vers le rôle `subscriber`
    lorsqu'un rôle custom est supprimé.
  - Stockage de la liste des rôles custom dans l'option `wtm_custom_roles`.

- **API REST — `Roles_Controller`** (`src/Api/Roles_Controller.php`,
  ~280 lignes) :
  - 5 routes REST sous `/wtm/v1/roles` :
    - `GET /roles` — liste tous les rôles + leurs caps WTM.
    - `GET /roles/{slug}` — détail d'un rôle.
    - `POST /roles` — crée un rôle custom (body : `{ slug, name, caps }`).
    - `PUT /roles/{slug}` — met à jour un sous-ensemble de caps.
    - `DELETE /roles/{slug}/delete` — supprime un rôle custom.
  - Toutes les routes requièrent la capacité `wtm_manage_settings`.

- **Blocs Gutenberg** — `Gutenberg_Blocks` (`src/Integration/Gutenberg_Blocks.php`,
  ~290 lignes) :
  - 3 blocs dynamiques server-rendered via `register_block_type` :
    - `wtm/menu` — affiche un menu WTM par ID ou par emplacement.
    - `wtm/header` — affiche un header WTM (utilise la config header du menu).
    - `wtm/footer` — affiche un footer WTM (utilise la config footer du menu).
  - `render_callback` PHP qui délègue à `Menu_Renderer::render_by_id()` ou
    `Header_Footer_Renderer::render_header_by_id()` / `render_footer_by_id()`.
  - Placeholder HTML si le bloc n'est pas configuré ou que le menu cible
    n'existe pas.
  - Editor JS minimaliste (`blocks/index.js`, ~210 lignes) avec sidebar
    `SelectControl` pour choisir un menu WTM publié (liste injectée via
    `wp_localize_script` → `wtmBlocksData.menus`).

- **Intégration Elementor** — `Elementor_Integration` +
  `WTM_Elementor_Widget` (`src/Integration/Elementor_Integration.php` +
  `src/Integration/Elementor_Widget.php`, ~265 lignes) :
  - Widget custom "Woo Total Menu" (icône `eicon-nav-menu`, catégorie
    `general`).
  - Contrôle SELECT pour choisir un menu WTM publié.
  - Lazy-loaded : ne s'instancie que si `class_exists('\Elementor\Widget_Base')`.
  - Widget dans un fichier séparé pour éviter le chargement de
    `\Elementor\Widget_Base` quand Elementor n'est pas actif.

- **Intégration Bricks** — `Bricks_Integration`
  (`src/Integration/Bricks_Integration.php`, ~140 lignes) :
  - Élément custom `wtm-menu` enregistré via `bricks/builder/elements`.
  - Hook de rendu `bricks/element/render/wtm-menu`.
  - Lazy-loaded : ne s'instancie que si `defined('BRICKS_VERSION')` ou
    `class_exists('\Bricks\Element')`.

- **Intégration Oxygen** — `Oxygen_Integration`
  (`src/Integration/Oxygen_Integration.php`, ~165 lignes) :
  - 3 shortcodes additionnels utilisables partout (y compris dans Oxygen
    via le bloc "Shortcode") :
    - `[wtm_header id="42"]` — affiche un header WTM.
    - `[wtm_footer id="42"]` — affiche un footer WTM.
    - `[wtm_oxygen_menu id="42"]` — alias explicite de `[wtm_menu]`.
  - Helper PHP global `wtm_oxygen_render_menu($menu_id)` pour les
    templates PHP Oxygen.

- **Multisite** — `Multisite_Manager` (`src/Core/Multisite_Manager.php`,
  ~210 lignes) :
  - Activation réseau : initialise tous les blogs existants (options, caps,
    flush rewrite rules).
  - Hook `wpmu_new_blog` : initialise automatiquement les nouveaux blogs
    créés après activation réseau.
  - `for_each_blog($callback)` : helper qui parcourt tous les blogs actifs
    du réseau (avec `switch_to_blog` / `restore_current_blog`). En
    single-site, exécute une fois sur le blog courant.
  - `get_network_stats()` : statistiques réseau (`total_blogs`,
    `total_menus`, `active_blogs`, `per_blog`).
  - Désactivation réseau : flush rewrite rules sur tous les blogs.
  - Désinstallation réseau : suppression de toutes les options + caps sur
    tous les blogs.

- **8 hooks/filters développeur** :
  - `wtm_role_created` ($slug, $name, $granted) — après création d'un rôle.
  - `wtm_role_updated` ($slug, $caps) — après mise à jour des caps.
  - `wtm_role_deleted` ($slug) — après suppression d'un rôle.
  - `wtm_activated` — après activation simple.
  - `wtm_deactivated` — après désactivation.
  - `wtm_network_activated` — après activation réseau complète.
  - `wtm_multisite_blog_setup` ($blog_id) — après setup d'un blog.
  - `wtm_multisite_blog_cleanup` ($blog_id) — après cleanup d'un blog.

### Changed

- **Bootstrap** (`src/Bootstrap.php`) :
  - `on_activate($network_wide = false)` et `on_deactivate($network_wide = false)`
    acceptent le paramètre WordPress standard pour gérer l'activation réseau.
  - Instanciation de `Roles_Controller`, `Gutenberg_Blocks`, et 3 intégrations
    page-builders (Elementor et Bricks sont lazy-loaded selon leur disponibilité ;
    Oxygen est toujours instancié car les shortcodes sont utiles partout).
  - Hook `wpmu_new_blog` enregistré dans `register_hooks()`.

- **Webpack config** (`webpack.config.js`) : ajout d'un entry point
  `blocks: './blocks/index.js'` pour générer le bundle `build/blocks.js` (3.99 KiB)
  + `build/style-blocks.css` (334 o) dédiés à l'éditeur Gutenberg.

- **Plugin version** : bump `1.5.0` → `1.6.0` dans `woo-total-menu.php`
  (en-tête + constante `WTM_VERSION`) et `package.json`.

## [1.5.0] - 2026-06-26

### Added

- **Système de templates intégrés** (spec §1.4.2 — bibliothèque de templates) :
  - 12 templates prêts à l'emploi, répartis en 3 catégories :
    - **4 templates de menus** : Menu horizontal simple (blog/vitrine),
      Méga menu boutique de mode (2 méga containers + widgets produits),
      Méga menu électronique (3 méga containers + bannière promo),
      Menu vertical sidebar (catalogue + widget filtres).
    - **4 templates de headers** : Header e-commerce classique
      (Logo | Menu | Recherche + Panier + Compte), Header minimaliste
      (2 colonnes), Header promotionnel (top bar promo + ligne main),
      Header sticky centré (style éditorial / luxe).
    - **4 templates de footers** : Footer e-commerce 4 colonnes
      (about + 2 menus + newsletter), Footer minimaliste (copyright +
      social), Footer corporate 4 colonnes (logo + 3 menus), Footer
      sombre accessible WCAG AA.
  - Catégorisation métier : `ecommerce`, `blog`, `corporate`, `minimal`,
    `electronics` — filtrable côté UI.

- **Backend PHP — `Template_Registry`** (`src/Core/Template_Registry.php`,
  ~840 lignes) :
  - Catalogue statique lazy-built, mis en cache dans une propriété
    statique `$catalog`.
  - 4 méthodes publiques : `all()`, `get($id)`, `by_type($type)`,
    `categories()`, `apply_to_menu($menu_id, $template_id, $mode)`.
  - Validation systématique via `Schema_Validator::validate_config()`
    (mode menu) ou `Schema_Validator::validate_layout()` (mode
    header/footer) avant l'écriture de la meta.
  - Sélection automatique de la meta cible (`_wtm_config`,
    `_wtm_header_config`, `_wtm_footer_config`) selon le mode.
  - Cohérence type template <-> mode appliqué (refus si mismatch).
  - Filtre `wtm_templates_catalog` pour ajouter / modifier / supprimer
    des templates depuis une extension tierce.
  - Action `wtm_template_applied` déclenchée après application réussie.

- **API REST — `Templates_Controller`** (`src/Api/Templates_Controller.php`,
  ~330 lignes) :
  - `GET /wtm/v1/templates` — liste filtrable par `type`, `category`,
    `search` (recherche plein-texte sur name, description, preview, tags).
  - `GET /wtm/v1/templates/{id}` — détail d'un template avec `config`
    complète.
  - `POST /wtm/v1/templates/{id}/apply` — applique un template à un menu
    (body : `{ menu_id, mode }`).
  - Lecture publique du catalogue ; écriture réservée aux utilisateurs
    avec la capacité `edit_posts`.
  - Schéma JSON public exposé sur `/wp-json/wtm/v1/templates`.

- **Builder React — Galerie de templates** (spec §1.4.2) :
  - Nouveau bouton "Galerie de templates" dans le Header du Builder
    (icône `dashicons-layout`).
  - Modal `TemplateGallery` avec toolbar (tabs Tous / Menus / Headers /
    Footers), recherche plein-texte, filtre par catégorie.
  - `TemplateCard` : carte individuelle avec mini-aperçu CSS synthétique
    (10 variantes : menu-simple, menu-mega, menu-vertical, header-3cols,
    header-2cols, header-2rows, header-centered, footer-4cols,
    footer-minimal, footer-dark) + nom + description + tags + bouton
    "Appliquer".
  - Confirmation native (`window.confirm`) avant application (écrase la
    config actuelle).
  - Pré-filtre automatique selon le mode actif (Header/Footer/Menu) à
    l'ouverture de la galerie.
  - Fermeture au clavier (Escape) sauf si une application est en cours.
  - Sortie automatique de la galerie après application réussie.

- **Store Redux `wtm/templates`** (`builder/stores/templates.js`,
  ~260 lignes) :
  - Catalogue en cache (fetch paresseux via `fetchTemplates()`).
  - États de filtre : `filterType`, `filterCategory`, `filterSearch`.
  - États d'application : `isApplying`, `applyError`, `lastApplied`.
  - Sélecteur `getFilteredTemplates` combinant les 3 filtres.
  - Sélecteur `getCategories` retournant les catégories distinctes avec
    compte et libellés traduits.
  - Action async `applyTemplate(id)` qui : POST → reload menu → reload
    layout (si mode header/footer) → ferme la galerie.

- **Store `wtm/ui` étendu** :
  - Nouvel état `isTemplatesOpen` (boolean).
  - Actions `openTemplates()` / `closeTemplates()`.

- **Hook développeur** :
  - `wtm_templates_catalog` (filter) — modification du catalogue de
    templates intégrés (ajout, suppression, modification).

- **Action développeur** :
  - `wtm_template_applied` — déclenchée après application d'un template
    à un menu. Reçoit `(menu_id, template_id, mode, template)`.

- **Documentation** :
  - `versions/v1.5.0.md` — description complète (fichier dédié).
  - Mise à jour `docs/schema.md` — référence aux templates dans les
    exemples complets.

### Changed

- **`src/Bootstrap.php`** : instancie `Templates_Controller` sur chaque
  requête (les routes REST doivent être disponibles côté admin et
  frontend).
- **`builder/components/App.js`** : monte le composant `TemplateGallery`
  au plus haut niveau (au-dessus de `HistoryPanel`) ; importe le store
  `wtm/templates` pour effet de bord (enregistrement).
- **`builder/components/Header.js`** : ajoute le bouton "Galerie de
  templates" dans la barre d'historique.
- **`builder/stores/ui.js`** : nouvel état `isTemplatesOpen` + actions
  `openTemplates` / `closeTemplates`.
- **`builder/style.css`** : +480 lignes pour la galerie (modal, cards,
  mini-previews CSS pour 10 types de thumbnails, responsive mobile).
- **`src/Admin/Pages/About.php`** : roadmap v1.5.x marquée `done`.
- **`versions/README.md`** : entrée v1.5.0 dans le sommaire.
- **`readme.txt`** : section `= 1.5.0 =` ajoutée au changelog utilisateur.
- **`package.json`** : version bumpée à `1.5.0`.

### Fixed

- Aucun bug rapporté dans cette version (nouvelle fonctionnalité).

## [1.4.0] - 2026-06-26

### Added

- **Header & Footer Builder visuel** (spec §3.6, §3.7, §4.6.5) :
  - Nouveau mode "Header" et "Footer" dans le Builder React, accessible
    via 3 tabs Menu / Header / Footer dans l'en-tête.
  - Layout 3-colonnes mode-aware : ModulePalette (gauche) + LayoutCanvas
    (centre) + ModuleProperties (droite) en mode header/footer, layout
    classique TreePanel + PreviewPanel + PropertiesPanel en mode menu.
  - Grille visuelle rows → columns → modules avec resize des colonnes
    (largeur 1-12 sur grille 12), drag-and-drop HTML5 des modules depuis
    la palette, déplacement des modules entre colonnes.
  - 9 types de modules disponibles : `logo`, `menu`, `search`, `cart`,
    `button`, `html`, `social`, `newsletter`, `text`.
  - Inspecteur de propriétés contextuel pour chaque type de module
    (text/url/style/provider/list_id/etc.).

- **2 nouvelles classes PHP frontend** (spec §3.6, §3.7, §5.7, §5.8) :
  - `Header_Footer_Renderer` (~838 lignes) — walker PHP pur qui parcourt
    les configs `_wtm_header_config` / `_wtm_footer_config` (rows →
    columns → modules) et produit du HTML sémantique `<header>` /
    `<footer>`. Réutilise les renderers widgets v1.3 pour les modules
    `search`, `cart`, `social`, `newsletter` (data-wtm-live-search,
    data-wtm-cart-drawer, data-wtm-newsletter, etc.).
  - `Header_Footer_Injector` (~288 lignes) — hooks `wp_body_open`
    (priority 10) et `wp_footer` (priority 20) pour injecter
    automatiquement le header et le footer globaux sur toutes les pages.
    Supporte le masquage CSS du header/footer natif du theme via les
    options `hide_theme_header` / `hide_theme_footer`.

- **Settings `header_footer`** (spec §9.5.2) :
  - Section "Header & Footer" dans la page Réglages → Woo Total Menu.
  - 5 champs : `enabled` (master toggle, défaut false opt-in),
    `header_menu_id`, `footer_menu_id`, `hide_theme_header`,
    `hide_theme_footer`.

- **REST API étendue** :
  - `GET /wtm/v1/menus/{id}` retourne désormais `header_config` et
    `footer_config` décodés depuis les meta `_wtm_header_config` et
    `_wtm_footer_config`.
  - `POST /wtm/v1/menus/{id}` accepte `header_config` et `footer_config`
    validés via `Schema_Validator::validate_layout()`.
  - 2 meta post enregistrés via `register_meta()` et versionnés dans les
    révisions WordPress (filtre `_wp_post_revision_meta_keys`).

- **`Schema_Validator::validate_layout()`** — nouvelle méthode statique
  qui valide la structure complète d'un layout header/footer :
  `version` (entier), `rows` (tableau de row objects avec `id`, `settings`,
  `columns`), chaque column a `id`, `width` (1-12), `settings`, `modules`,
  chaque module a `id` (string), `type` (parmi `MODULE_TYPES` = 9 types),
  `settings`. Messages d'erreur précis avec chemin (ex:
  `rows[0].columns[1].modules[2] doit avoir un champ "type" dans: logo,
  menu, search, …`).

- **`Assets_Loader` étendu** : nouvelle méthode `is_header_footer_active()`
  qui inspecte les settings `header_footer` pour forcer l'enqueue des
  assets frontend même si aucun menu n'a encore été rendu (car
  `wp_body_open` / `wp_footer` se déclenchent après `wp_enqueue_scripts`).

- **Preview iframe étendu** : `Preview_Controller` gère désormais les
  modes header et footer avec `renderModule()`, `renderColumn()`,
  `renderHeader()` et `renderFooter()` qui produisent un aperçu compact
  de chaque module type.

- **Layout store Redux dédié** (`builder/stores/layout.js`, ~640 lignes) :
  store `wtm/layout` avec états séparés `state.header` et `state.footer`.
  Actions : `loadFromMenu`, `addRow`, `updateRow`, `removeRow`,
  `moveRow`, `addColumn`, `updateColumn`, `removeColumn`, `addModule`,
  `updateModule`, `removeModule`, `moveModule`, `selectElement`,
  `clearSelection`. Sélecteurs : `getLayout`, `getSelectedElementId`,
  `getSelectedElementType`, `isDirty`, `getHeaderConfig`,
  `getFooterConfig`.

- **5 hooks/filters pour développeurs** :
  - `wtm_header_inject_hook` (filter) — override du hook d'injection
    header (ex: `storefront_header` pour Storefront).
  - `wtm_footer_inject_hook` (filter) — override du hook d'injection
    footer.
  - `wtm_before_header` (action) — déclenchée avant le rendu header.
  - `wtm_before_footer` (action) — déclenchée avant le rendu footer.
  - `wtm_layout_module_html` (filter) — filtre le HTML rendu d'un module.

### Fixed

- **Header search module** : `render_module_search()` n'ajoutait pas les
  attributs `data-wtm-live-search` et `data-min-chars` sur l'input
  quand `live_suggestions: true`, ce qui empêchait la recherche live de
  fonctionner dans les headers/footers. Fix : ajout conditionnel des
  attributs + classe `wtm-search--live` + attribut `data-wtm-suggestions`
  sur la div suggestions.
- **Header newsletter module** : `render_module_newsletter()` ne
  générait pas les attributs attendus par `initNewsletter()` du JS
  frontend (`data-provider`, `data-list-id`, `data-wtm-newsletter-message`,
  `<script data-wtm-newsletter-config>`). Fix : alignement complet du
  rendu sur le widget v1.3.

### Changed

- `Bootstrap::init_services()` instancie désormais `hf_renderer` et
  `hf_injector` sur chaque requête (pas seulement `is_admin`).
- `Assets_Loader::__construct()` écoute les actions `wtm_before_header`
  et `wtm_before_footer` pour marquer les assets comme nécessaires.
- `Menu_Controller` persiste `_wtm_header_config` et `_wtm_footer_config`
  via `wp_slash(wp_json_encode())` et les supprime si null.
- Builder `App.js` est mode-aware et propage le dirty state du store
  `wtm/layout` vers le store `wtm` (menu) pour activer le bouton Save.
- Builder `Header.js` affiche 3 tabs Menu / Header / Footer.

## [1.3.0] - 2026-06-25

### Added

- **4 nouveaux widgets WooCommerce avancés** (spec §3.5, §5.7, §5.9) :
  - `recent_posts` — articles WordPress en grille (image, titre, date,
    extrait) avec tri (date, title, comment_count, rand), catégorie
    optionnelle, et transient cache filtrable via `wtm_widget_cache_duration`.
  - `social_icons` — icônes réseaux sociaux avec glyphes SVG inline
    (CSS mask) pour 7 réseaux prédéfinis (Facebook, Twitter/X, Instagram,
    LinkedIn, YouTube, Pinterest, GitHub) + couleurs hover par réseau.
    Taille configurable 12-64 px.
  - `newsletter` — formulaire d'abonnement email avec layout inline/stacked,
    providers `internal`/`mailchimp`/`none`, message de succès personnalisable.
    Submit via admin-ajax (action `wtm_newsletter_subscribe`) avec nonce.
  - `filters` — filtres WooCommerce layered nav (catégories en select,
    range de prix min/max, attributs en checkboxes multiples). Soumet
    vers la page Shop avec les query params WC standard.

- **Widget `mini_cart` upgraded — mode drawer** : nouveau `display_mode:
  'drawer'` qui rend un `<button data-wtm-cart-drawer>` au lieu d'un lien.
  Au clic, le JS frontend ouvre un panneau latéral fixe (right ou left,
  configurable) qui fetch le contenu du panier via la route REST
  `/wtm/v1/mini-cart-contents` et affiche : items (image, nom, quantité,
  prix), total, et boutons "Voir le panier" + "Commander". Refresh
  automatique quand les WC cart fragments sont mis à jour.

- **Widget `search` upgraded — suggestions live AJAX** : nouvelle option
  `live_suggestions: true` qui attache un listener sur l'input et, après
  debounce 250 ms, fetch la route REST `/wtm/v1/search-suggest?s=…` et
  affiche un dropdown avec jusqu'à 5 produits (image thumbnail, titre,
  price_html, badge "Promo" si `on_sale`). Navigation clavier complète
  (ArrowDown/ArrowUp/Escape) + role="listbox".

- **2 routes REST publiques** :
  - `GET /wtm/v1/search-suggest?s=<query>&limit=5` — recherche produits
    par relevance via `wc_get_products()`. Retourne `{ query, count,
    products: [{id, title, permalink, price_html, thumbnail, on_sale}] }`.
    Filter `wtm_search_suggest_query`.
  - `GET /wtm/v1/mini-cart-contents` — calcule les totaux via
    `WC()->cart->calculate_totals()` et retourne `{ count, total, subtotal,
    items: [{key, product_id, name, permalink, thumbnail, quantity,
    price_html}], cart_url, checkout_url, is_empty }`.

- **1 admin-ajax handler** — `action=wtm_newsletter_subscribe` (double
  hook `wp_ajax_*` + `wp_ajax_nopriv_*` pour visiteurs non connectés) :
  vérifie le nonce `wtm_newsletter`, valide l'email via `is_email()`,
  stocke dans l'option `wtm_newsletter_subscribers` (array d'entrées
  `{email, list_id, subscribed, ip}`) avec déduplication. L'IP est
  anonymisée (dernier octet IPv4 à 0, IPv6 tronquée à /64) pour
  conformité GDPR.

- **`WIDGET_SUBTYPES` picker dans AddItemButton.js** : quand l'utilisateur
  choisit "Widget" dans le dropdown d'ajout d'item, un panneau secondaire
  s'ouvre avec les 12 sous-types de widgets (html, banner, custom_link,
  product_grid, category_grid, mini_cart, search, recent_posts,
  social_icons, newsletter, filters, title), chacun avec icône dashicon
  + description courte + defaults spécifiques au type. L'utilisateur
  n'a plus à changer le widget_type manuellement après ajout.

- **Inspecteurs PropertiesPanel.js pour les 7 nouveaux widgets** :
  - `custom_link` : label, url, couleurs fond/texte
  - `mini_cart` : mode d'affichage (link/drawer), position drawer
    (right/left), show_subtotal, show_checkout_button, show_thumbnail
  - `search` : placeholder, live_suggestions (bool), min_chars (2-5),
    show_category_filter
  - `recent_posts` : columns (1-4), limit (1-12), orderby
    (date/title/comment_count/rand), show_image, show_date, show_excerpt
  - `social_icons` : size (12-64 px), liste dynamique de réseaux avec
    boutons ajouter/supprimer
  - `newsletter` : placeholder, button_label, provider
    (internal/mailchimp/none), list_id, layout (inline/stacked),
    success_message
  - `filters` : show_categories, show_price, show_attributes + slugs
    d'attributs séparés par virgule

- **~570 lignes CSS frontend** pour les nouveaux composants : drawer
  latéral (header/body/footer/overlay + position right/left), dropdown
  de suggestions (media+body+badge promo + loading/empty states), cards
  articles récents (media 16:9 + title 2-line clamp + date + excerpt),
  icônes sociales (CSS mask SVG inline + 8 couleurs hover par réseau),
  formulaire newsletter (inline/stacked + états success/error),
  filtres WooCommerce (select/price range/checkboxes/actions) + section
  responsive 768px.

- **~430 lignes JS frontend** pour les 3 nouveaux modules :
  - `initCartDrawer()` — création dynamique du drawer + overlay si non
    présent dans le DOM, fetch REST, render items/total/actions, focus
    management, Escape close, refresh auto sur `wc_fragments_refreshed`.
  - `initLiveSearch()` — debounce 250 ms, fetch REST, render dropdown
    avec image+title+price, navigation clavier ArrowUp/Down/Escape,
    blur delay 200 ms pour permettre le clic sur suggestion.
  - `initNewsletter()` — FormData POST vers admin-ajax, validation
    email client-side (regex), gestion états success/error, reset form,
    bouton disabled + label "Inscription…" pendant la requête.

- **4 nouveaux hooks filters pour développeurs** :
  - `wtm_search_suggest_query` — filtre les args `wc_get_products()`
    pour la recherche live (permet d'exclure des catégories, etc.).
  - `wtm_newsletter_subscription_handled` — court-circuite le stockage
    internal : un plugin tiers Mailchimp peut retourner `true` après
    un appel API et bypasser la table `wtm_newsletter_subscribers`.
  - `wtm_newsletter_subscribed` — action déclenchée après succès,
    reçoit `(email, provider, list_id)` pour sync CRM/webhooks.
  - `wtm_widget_cache_duration` étendu pour les nouveaux widgets
    `recent_posts` (en plus de `product_grid`, `category_grid`).

- **`wp_localize_script('wtmFrontend', ...)` étendu** : passe désormais
  `restUrl`, `restNonce`, `newsletterNonce` au JS frontend (en plus de
  `ajaxUrl`, `breakpoint`, `i18n`, `wooCartFragments`). 9 nouvelles
  clés i18n : `openCart`, `closeCart`, `cartEmpty`, `viewCart`,
  `checkout`, `noResults`, `searching`, `subscribing`, `invalidEmail`.

### Changed

- **`Schema_Validator::WIDGET_TYPES`** étendu de 8 à 12 types. Validation
  stricte par widget_type pour les 4 nouveaux + extension des validators
  existants pour `mini_cart` (`display_mode`, `drawer_position`) et
  `search` (`live_suggestions`, `min_chars`).

- **`Menu_Renderer.php`** étendu (~430 lignes) : 4 nouvelles méthodes
  `render_widget_recent_posts`, `render_widget_social_icons`,
  `render_widget_newsletter`, `render_widget_filters` + helpers
  `render_post_card`. Upgrade de `render_widget_mini_cart` (mode drawer
  génère un `<button data-wtm-cart-drawer>`) et `render_widget_search`
  (option `live_suggestions` ajoute `data-wtm-live-search` + conteneur
  `<div data-wtm-suggestions>`).

- **`Bootstrap.php`** instancie le nouveau `Frontend_Controller`
  (`src/Api/Frontend_Controller.php`) sur chaque requête — REST routes
  + admin-ajax handler.

- **`Assets_Loader.php`** enrichit le `wp_localize_script` avec
  `restUrl`, `restNonce`, `newsletterNonce` + 9 nouvelles clés i18n.

- **`AddItemButton.js`** refactorisé : extraction de `WIDGET_SUBTYPES`
  (12 entrées avec defaults), nouveau state `widgetPanelFor` qui bascule
  vers le panneau de sélection du sous-type quand l'utilisateur clique
  sur "Widget". Bouton "Retour" pour revenir au panneau principal.

- **`PropertiesPanel.js`** étendu avec les inspecteurs pour les 7
  nouveaux types de widgets (custom_link, mini_cart mode drawer, search
  live, recent_posts, social_icons avec items dynamiques, newsletter,
  filters).

- **Bump version** 1.2.0 → 1.3.0 dans `woo-total-menu.php` (header +
  `WTM_VERSION`), `package.json`, `readme.txt` (Stable tag + nouvelle
  section Changelog), `CHANGELOG.md`, `versions/README.md`, `About.php`
  (roadmap : v1.2.0 → done, v1.3.0 → current/done).

### Security

- **Nonce `wtm_newsletter`** vérifié sur le handler admin-ajax
  `wtm_newsletter_subscribe` (mitigue spam cross-site).
- **IP anonymisée** (dernier octet IPv4 à 0, IPv6 tronquée à /64)
  lors du stockage des abonnés newsletter — conformité GDPR.
- **Échappement systématique** sur tout le rendu : `esc_url`,
  `esc_html`, `esc_attr`, `wp_kses_post`. Les slugs d'attributs
  passent par `sanitize_title()`. Les réseaux sociaux par
  `sanitize_html_class()`.
- **REST endpoints** : `permission_callback => '__return_true'` pour
  que les visiteurs non connectés puissent utiliser le drawer et la
  recherche (le panier WooCommerce est lié à la session WC, pas à
  l'utilisateur WP).
- **Validation stricte** dans `Schema_Validator` pour les nouveaux
  widgets : limit (1-12), columns (1-6), size (12-64), provider/layout
  enums, etc.

## [1.2.0] - 2026-06-25

### Added

- **Rendu frontend complet** (spec §2.4, §5, §7.5) : les menus `wtm_menu`
  s'affichent désormais côté visiteur. Auparavant (v1.0.0 → v1.1.5), le plugin
  ne faisait que *stocker* les configurations ; il pouvait uniquement les
  prévisualiser dans le Builder via une iframe. À partir de la v1.2.0, le
  rendu est production-ready pour les 4 types de menus : horizontal (méga
  menu), vertical (sidebar), off-canvas (mobile), footer (multi-colonnes).

- **Classe `Menu_Renderer`** (`src/Frontend/Menu_Renderer.php`, ~800 lignes) :
  walker PHP pur qui parcourt l'arbre JSON `_wtm_config` et produit du HTML
  sémantique `<nav><ul>` pour les 6 types d'items (`link`, `mega_container`,
  `column`, `widget`, `title`, `separator`) et les 7 types de widgets
  (`html`, `banner`, `product_grid`, `category_grid`, `mini_cart`, `search`,
  `custom_link`). Quatre dispatchers par type de menu (`render_horizontal`,
  `render_vertical`, `render_offcanvas`, `render_footer`) pour coller aux
  maquettes spec §5.3 → §5.7. Tout le HTML échappé via `esc_url`, `esc_html`,
  `esc_attr`, `wp_kses_post`.

- **Classe `Location_Interceptor`** : hook `wp_nav_menu_args` (priority 20)
  qui détecte les appels à `wp_nav_menu(['theme_location' => 'wtm_primary'])`
  (ou n'importe quelle location WTM) et remplace le walker natif WordPress
  par le rendu `Menu_Renderer`. Cache objet 5 min sur la résolution
  `location → menu_id`. Hook de mapping `wtm_map_theme_location` pour
  extension à des locations de thème personnalisées.

- **Classe `Dynamic_CSS`** (spec §2.4.3) : compile un CSS unique à partir des
  réglages globaux (`WTM_OPTION_SETTINGS` — couleurs, typo, breakpoint) et
  des paramètres par menu (sticky, align, fullwidth_mega). Sauvegarde dans
  `uploads/wtm-cache/dynamic-{hash}.css` où `hash` = md5 du contenu, utilisé
  comme query string `?ver=` pour cache-busting. Régénéré automatiquement
  sur `save_post_wtm_menu`, `wtm_settings_saved`, `wp_restore_post_revision`.
  Répertoire protégé par `.htaccess` + `index.php` (pas d'exécution PHP
  directe). Filter `wtm_dynamic_css` pour extensions.

- **Classe `Shortcode`** (spec §2.8.2) : `[wtm_menu id="123"]` ou
  `[wtm_menu location="primary"]` insère un menu n'importe où (pages,
  articles, Elementor, blocs Gutenberg). Déclenche `wtm_rendered_location`
  pour que `Assets_Loader` enqueuue les CSS/JS.

- **Assets frontend conditionnels** : `Assets_Loader` remplace le stub
  v1.0.0 et n'enqueue CSS/JS que si un menu WTM a effectivement été rendu
  sur la page (via écoute de l'action `wtm_rendered_location`). Filter
  `wtm_force_enqueue_assets` pour forcer le chargement (utile pour
  shortcodes dans contenu AJAX).

- **Fichier `assets/front/wtm-frontend.css`** (~15 Ko) : styles de base
  pour les 4 types de menus + méga panel + sub-menus flyout + badges +
  icônes + widgets (product cards, category cards, mini-cart, search,
  banners) + responsive (mobile breakpoint 768px par défaut, footer
  accordion en mobile) + `prefers-reduced-motion` + screen-reader-text.

- **Fichier `assets/front/wtm-frontend.js`** (~5 Ko, vanilla JS, pas de
  jQuery — spec §2.6.1) : gère
  - Off-canvas : ouverture/fermeture, clic overlay, ESC, focus trap
    (Tab/Shift+Tab cycle à l'intérieur du drawer).
  - Click-trigger mega containers : sur mobile ou en mode `trigger=click`,
    un clic bascule `is-open` sur le `.wtm-menu__item--mega`.
  - Mobile accordion : sur mobile, un clic sur un item parent avec
    `href="#"` bascule ses enfants visibles.
  - Footer accordion : sur mobile, les titres de colonnes deviennent
    cliquables pour replier/déplier la liste.
  - Outside-click + Escape pour fermer les mega panels ouverts.
  - WC mini-cart : écoute `wc_fragments_refreshed` et rejoue une animation
    "bounce" sur le compteur.
  - Localisation via `window.wtmFrontend` (breakpoint, i18n, ajaxUrl).

- **4 widgets WooCommerce rendus côté frontend** :
  - `product_grid` : grille de produits WC avec image, nom, prix, bouton
    "Ajouter" (URL `add-to-cart`). Cache transient 12h filtrable via
    `wtm_widget_cache_duration`. Ordres supportés : date, price-asc,
    price-desc, popularity, rating. Filter `wtm_product_grid_query` pour
    customiser la requête.
  - `category_grid` : grille de catégories WC avec thumbnail (via
    `get_term_meta('thumbnail_id')`).
  - `mini_cart` : lien vers `/cart` avec compteur + total synchronisés via
    WC cart fragments. Bounce animation sur update.
  - `search` : formulaire de recherche produits WC (hidden input
    `post_type=product`).

- **3 widgets non-WC** : `html` (rendu via `wp_kses_post` — spec §7.10),
  `banner` (bloc CTA coloré avec bg/color/url), `custom_link` (bouton
  stylisé).

- **8 hooks filters pour développeurs** (spec §2.8.4) :
  - `wtm_menu_config` — filtre la config avant rendu
  - `wtm_render_item` — court-circuit le rendu d'un item individuel
  - `wtm_menu_classes` — filtre les classes CSS du `<nav>`
  - `wtm_dynamic_css` — filtre le CSS généré
  - `wtm_map_theme_location` — map des locations de thème custom
  - `wtm_product_grid_query` — filtre la WP_Query du widget product_grid
  - `wtm_widget_cache_duration` — filtre la durée du transient cache
  - `wtm_force_enqueue_assets` — force le chargement des assets
  - Action `wtm_rendered_location` — émise à chaque rendu de menu

- **Localisation FR** du JS frontend via `wp_localize_script('wtmFrontend',
  ...)` avec `breakpoint`, `mobileBehavior`, `i18n` (openMenu, closeMenu,
  openSub, closeSub), `ajaxUrl`, `wooCartFragments`.

### Changed

- `Bootstrap.php` instancie les 4 nouveaux services frontend (Dynamic_CSS,
  Menu_Renderer, Location_Interceptor, Shortcode) sur chaque requête (pas
  seulement `is_admin()`) pour que REST + AJAX + wp-admin partagent les
  mêmes hooks. `Assets_Loader` reçoit maintenant une dépendance
  `Dynamic_CSS` injectée. Nouveau hook `purge_dynamic_css` sur
  `save_post_wtm_menu`, `wtm_settings_saved`, `wp_restore_post_revision`.

- Bump version 1.1.5 → 1.2.0 dans `woo-total-menu.php` (header + constante
  `WTM_VERSION`), `package.json`, `readme.txt` (Stable tag + nouvelle
  section Changelog).

### Security

- Tout le HTML rendu échappe les URLs (`esc_url`), labels (`esc_html`),
  attributs (`esc_attr`), couleurs (regex whitelist hex/rgb), HTML
  personnalisé (`wp_kses_post`).
- Le répertoire de cache `uploads/wtm-cache/` contient un `.htaccess`
  (`Options -Indexes` + `Deny from all` sur `*.php`) et un `index.php`
  vide — empêche l'exécution PHP directe et le listage de répertoire.
- Les widgets WC utilisent les fonctions API WooCommerce standard
  (`wc_get_product`, `wc_get_cart_url`, `WC()->cart`) — pas de requêtes
  SQL brutes.

## [1.1.5] - 2026-06-25

### Added

- **Historique des révisions WordPress** (spec §6.6, §7.6, §9.9) : chaque
  sauvegarde du Builder crée désormais une révision WordPress du CPT
  `wtm_menu`, capturant à la fois les champs du post (titre, statut) et les
  6 métadonnées WTM (`_wtm_config`, `_wtm_menu_type`, `_wtm_location`,
  `_wtm_header_config`, `_wtm_footer_config`, `_wtm_version`). L'utilisateur
  peut lister, prévisualiser et restaurer n'importe quelle révision passée
  depuis un nouveau modal « Historique » dans le Builder.
  - Nouveau controller REST `Revisions_Controller.php` exposant 3 routes :
    - `GET /wtm/v1/menus/{id}/revisions` — liste paginée avec `X-WP-Total` /
      `X-WP-TotalPages` ; chaque item contient l'auteur, la date relative,
      le nombre d'items (comptage récursif) et le snapshot complet du config
      décodé en JSON.
    - `GET /wtm/v1/menus/{id}/revisions/{revision_id}` — récupère une
      révision spécifique avec son snapshot.
    - `POST /wtm/v1/menus/{id}/revisions/{revision_id}/restore` — restaure
      la révision : appelle `wp_restore_post_revision()` (qui ne restaure
      que les post fields) puis copie manuellement les 6 meta WTM depuis
      la révision vers le post parent via `update_post_meta()`. Le hook
      `wp_restore_post_revision` (déclaré dans `Meta_Boxes`) fait la même
      chose pour les restaurations effectuées en dehors du Builder.
  - Nouveau filtre `wtm_max_revisions` (défaut 10, spec §7.6) appliqué via
    le hook `wp_revisions_to_keep` sur le CPT `wtm_menu`. Les
    administrateurs peuvent l'ajuster via un `add_filter()` classique.
  - Les 6 meta WTM sont désormais enregistrées avec
    `revisions_enabled => true` (WP 6.4+) ET hookées via le filtre
    `_wp_post_revision_meta_keys` pour double robustesse.
  - Nouveau composant `HistoryPanel.js` : modal centré avec overlay,
    fermeture par Escape / clic backdrop / bouton Close, liste scrollable
    des révisions. Chaque ligne affiche : avatar de l'auteur (32px,
    Gravatar), nom, date relative (« il y a 5 min »), nombre d'items,
    et un bouton « Restaurer » qui déclenche un inline confirm.
  - **Prévisualisation de révision** : cliquer une ligne du modal envoie
    la config de la révision à l'iframe de preview via `postMessage`,
    sans toucher à l'état live du menu. Un pill « Révision #N » jaune
    s'affiche dans le header du panneau de preview pour indiquer qu'on
    est en mode aperçu révision. Recliquer la ligne revient à la config
    live.
  - **Flow de restauration** : bouton « Restaurer » → bannière de
    confirmation inline (jaune « Restaurer cette révision ? » avec
    boutons Confirmer / Annuler) → appel `POST .../restore` → le menu
    est remplacé par la version restaurée → `clearHistory()` est appelé
    pour vider les piles undo/redo locales (la timeline a changé
    serveur-side) → fermeture du modal après 600 ms.
  - Store `wtm/menu` étendu avec `revisions: []`, `isLoadingRevisions:
    false`, `isRestoring: false` + actions `loadRevisions(menuId)`,
    `restoreRevision(menuId, revisionId)`, `setRevisions()`,
    `setIsLoadingRevisions()`, `setIsRestoring()` + sélecteurs associés.
  - Store `wtm/ui` étendu avec `isHistoryOpen: false`,
    `previewRevisionId: null` + actions `openHistory()`, `closeHistory()`,
    `setPreviewRevision(revisionId)` + sélecteurs associés.

### Changed

- **`Menu_Controller::update_menu()`** — désormais, chaque save écrit une
  signature dérivée du config (`wtm:<md5>:<timestamp>`) dans le champ
  `post_content`. C'est nécessaire car WordPress ne crée une révision que
  si un *post field* change ; les modifications meta-only n'en créaient
  aucune. Cette astuce garantit qu'une révision est toujours créée sur
  chaque save du Builder, ce qui rend l'historique utilisable.
- **`Header.js`** — un 3e bouton « Historique » (icône
  `dashicons-backup`) est ajouté dans le group undo/redo, désactivé pour
  les nouveaux menus non sauvegardés (`!menu?.id`) ou pendant le
  chargement. Cliquer ouvre le modal et déclenche
  `loadRevisions(menu.id)`.
- **`PreviewPanel.js`** — étendu pour calculer `effectiveConfig` (config
  live OU config de la révision prévisualisée) et l'envoyer à l'iframe
  via `postMessage`. Le debounce 250 ms est conservé.
- **`Meta_Boxes.php`** — `register_meta()` ajoute
  `revisions_enabled => true` sur les 6 meta WTM (pour WP 6.4+) ; deux
  nouveaux hooks sont enregistrés dans le constructor :
  `_wp_post_revision_meta_keys` (déclare les meta comme
  revision-persisted) et `wp_restore_post_revision` (restaure les meta
  WTM après une restauration de révision côté WP core).
- **`CPT_Manager.php`** — nouveau hook `wp_revisions_to_keep` applique
  le filtre `wtm_max_revisions` (défaut 10, spec §7.6) sur le CPT
  `wtm_menu`.
- **`Bootstrap.php`** — instancie `Revisions_Controller` sur chaque
  requête (REST doit être disponible même quand `is_admin()` est false).
- **`Requires at least`** — 6.3 → 6.4 dans `readme.txt` et
  `woo-total-menu.php`. Nécessaire pour le paramètre `revisions_enabled`
  de `register_post_meta()` (ajouté dans WP 6.4), aligné avec spec §7.6
  qui mentionne explicitement WP 6.4.
- Bump version 1.1.4 → 1.1.5 (`woo-total-menu.php`, `package.json`,
  `readme.txt`).

### Security

- Les routes REST `revisions/*` requièrent toutes la capacité
  `wtm_manage_menus` via `permission_callback`. Un utilisateur non
  authentifié ou sans la capacité obtient une erreur 401/403.
- `restore_revision` vérifie que la révision appartient bien au menu
  parent (`post_parent === $post_id`) avant toute restauration.
- Le hook `wp_restore_post_revision` ne restaure les meta WTM que si le
  post parent est bien de type `wtm_menu` (pas de fuite vers d'autres
  CPT).

## [1.1.4] - 2026-06-25

### Added

- **Live preview via iframe + postMessage** (spec §6.3, §6.6, §15.4) : le panneau
  central du Builder n'est plus un placeholder statique. Il affiche désormais un
  iframe qui restitue en direct la configuration du menu.
  - Nouveau endpoint REST `GET /wtm/v1/preview-frame` (`Preview_Controller.php`)
    qui sert un document HTML auto-contenu (CSS + JS inline, ~10 Ko) avec un
    listener `postMessage`. Le rendu du menu est fait côté JS dans l'iframe à
    partir de la config envoyée par le parent.
  - `PreviewPanel.js` entièrement réécrit : iframe `<iframe ref src=...>` +
    listener `wtm-preview-ready` (signal de l'iframe vers le parent) +
    `postMessage` parent → iframe avec `{ type: 'wtm-render', config, device }`.
  - **Debounce 250 ms** sur les messages (spec §6.6 — "postMessage debounced
    200-300 ms") pour éviter de flooder l'iframe pendant un drag.
  - **Rendu immédiat sur changement de device** (pas de debounce) pour que le
    changement de résolution soit instantané pour l'utilisateur.
  - Support des 6 types d'items (link, mega_container, column, widget, title,
    separator) + 7 types de widgets (html, banner, product_grid, category_grid,
    mini_cart, search, custom_link) dans le rendu preview.
  - Modes responsive : desktop (100%), tablet (768px avec bordure sombre), mobile
    (375px avec bordure type smartphone).

### Changed

- `builder/index.js` : `initialState.previewFrameUrl` ajouté au fallback
  `wtmBuilderData`.
- `builder/components/App.js` : `setRestConfig` reçoit maintenant
  `previewFrameUrl` en plus de `restUrl`/`restNonce`.
- `builder/stores/ui.js` : nouvel état `previewFrameUrl`, nouvelle action
  passée à `setRestConfig`, nouveau sélecteur `getPreviewFrameUrl`.
- `src/Admin/Admin_Menu.php` : `wp_localize_script('wtmBuilderData')` inclut
  maintenant `previewFrameUrl` (URL REST du endpoint preview-frame).
- `src/Bootstrap.php` : instancie `Frontend\Preview_Controller` sur chaque
  requête (l'iframe charge via l'URL REST, pas via l'admin).
- `builder/components/PreviewPanel.js` : réécriture complète (placeholder
  statique → iframe + postMessage).
- `builder/style.css` : ajout des classes `.wtm-preview__iframe`,
  `.wtm-preview__iframe-loading`, `.wtm-preview__footer`,
  `.wtm-preview__device-pill`, et des bordures "device" pour mobile/tablet.

### Security

- L'endpoint `preview-frame` requiert la capacité `wtm_manage_menus`
  (permission_callback `current_user_can`).
- `X-Frame-Options: SAMEORIGIN` envoyé sur la réponse — l'iframe ne peut être
  embarquée que sur le même domaine admin.
- Le sandbox attribute `allow-same-origin allow-scripts` permet à l'iframe de
  recevoir les messages tout en bloquant les formulaires et la navigation
  externe.
- Validation `event.source !== window.parent` côté iframe pour n'accepter que
  les messages venant du builder.

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
