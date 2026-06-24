=== Woo Total Menu ===
Contributors: woo-total-menu-team
Tags: menu, mega menu, header, footer, woocommerce, navigation, builder
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.3
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
