<?php
/**
 * Template Registry — Woo Total Menu.
 *
 * Catalogue centralisé des templates intégrés du plugin. Chaque template
 * décrit une configuration prête à l'emploi pour un menu, un header ou un
 * footer. Les templates sont consommés par :
 *  - `Templates_Controller` (REST GET /wtm/v1/templates) pour la liste ;
 *  - le Builder React via la galerie de templates (modal "Modèles") ;
 *  - l'action `apply_template()` qui fusionne le template dans la config
 *    d'un menu existant (header_config / footer_config / config).
 *
 * Catalogue v1.5.0 — 12 templates intégrés :
 *   Menus (4)   : menu-simple-horizontal, menu-mega-mode,
 *                 menu-mega-electronics, menu-vertical-sidebar
 *   Headers (4) : header-ecommerce-classic, header-minimal,
 *                 header-promo-banner, header-sticky-centered
 *   Footers (4) : footer-ecommerce-4cols, footer-minimal,
 *                 footer-corporate-4cols, footer-dark-accessible
 *
 * @package WooTotalMenu\Core
 */

namespace WooTotalMenu\Core;

/**
 * Class Template_Registry.
 *
 * Registre des templates intégrés. Singleton paresseux : le catalogue est
 * construit une seule fois par requête (cache statique).
 */
final class Template_Registry {

        /**
         * Cache statique du catalogue complet.
         *
         * @var array|null
         */
        private static $catalog = null;

        /**
         * Renvoie le catalogue complet des templates (12 entrées).
         *
         * Le catalogue est construit une seule fois par requête puis mis en cache
         * dans la propriété statique `$catalog`. Chaque entrée expose :
         *  - `id`           : slug unique du template
         *  - `name`         : libellé affiché dans la galerie
         *  - `description`  : description courte (1-2 phrases)
         *  - `type`         : `menu` | `header` | `footer`
         *  - `category`     : catégorie métier (ecommerce, blog, corporate,
         *                      minimal, restaurant, electronics)
         *  - `thumbnail`    : aperçu CSS synthétique (mapping -> classe CSS)
         *  - `preview`      : aperçu rapide textuel (utilisé par l'API)
         *  - `config`       : payload JSON à appliquer (structure rows / items)
         *  - `tags`         : mots-clés (pour la recherche)
         *
         * @return array<int,array<string,mixed>> Catalogue complet.
         */
        public static function all() {
                if ( null !== self::$catalog ) {
                        return self::$catalog;
                }

                self::$catalog = array(
                        // ───────────────────────────────────────────────────────────
                        // MENUS (4 templates).
                        // ───────────────────────────────────────────────────────────
                        self::menu_simple_horizontal(),
                        self::menu_mega_mode(),
                        self::menu_mega_electronics(),
                        self::menu_vertical_sidebar(),

                        // ───────────────────────────────────────────────────────────
                        // HEADERS (4 templates).
                        // ───────────────────────────────────────────────────────────
                        self::header_ecommerce_classic(),
                        self::header_minimal(),
                        self::header_promo_banner(),
                        self::header_sticky_centered(),

                        // ───────────────────────────────────────────────────────────
                        // FOOTERS (4 templates).
                        // ───────────────────────────────────────────────────────────
                        self::footer_ecommerce_4cols(),
                        self::footer_minimal(),
                        self::footer_corporate_4cols(),
                        self::footer_dark_accessible(),
                );

                /**
                 * Filtre les templates intégrés avant retour.
                 *
                 * Permet à une extension d'ajouter ses propres templates ou de
                 * modifier / supprimer les templates intégrés.
                 *
                 * @since 1.5.0
                 *
                 * @param array $catalog Catalogue complet (12 entrées par défaut).
                 */
                self::$catalog = apply_filters( 'wtm_templates_catalog', self::$catalog );

                return self::$catalog;
        }

        /**
         * Récupère un template par son identifiant.
         *
         * @param string $id Identifiant (slug) du template.
         * @return array|null Template ou null si absent.
         */
        public static function get( $id ) {
                foreach ( self::all() as $template ) {
                        if ( isset( $template['id'] ) && $template['id'] === $id ) {
                                return $template;
                        }
                }
                return null;
        }

        /**
         * Liste filtrée par type (`menu` | `header` | `footer`).
         *
         * @param string $type Type souhaité.
         * @return array<int,array<string,mixed>> Templates filtrés.
         */
        public static function by_type( $type ) {
                return array_values(
                        array_filter(
                                self::all(),
                                static function ( $t ) use ( $type ) {
                                        return isset( $t['type'] ) && $t['type'] === $type;
                                }
                        )
                );
        }

        /**
         * Renvoie la liste des catégories distinctes (avec compte).
         *
         * @return array<string,int> Catégorie => nombre de templates.
         */
        public static function categories() {
                $out = array();
                foreach ( self::all() as $t ) {
                        $cat = isset( $t['category'] ) ? $t['category'] : 'general';
                        if ( ! isset( $out[ $cat ] ) ) {
                                $out[ $cat ] = 0;
                        }
                        ++$out[ $cat ];
                }
                return $out;
        }

        /**
         * Applique un template à un menu : fusionne le payload dans la meta
         * ciblée (`config`, `header_config` ou `footer_config`).
         *
         * Étapes :
         *  1. Chargement du template par ID.
         *  2. Validation via `Schema_Validator::validate()` ou `validate_layout()`
         *     selon le type.
         *  3. Écriture de la meta concernée via `update_post_meta()`.
         *  4. Déclenchement de l'action `wtm_template_applied`.
         *
         * @param int    $menu_id     ID du post `wtm_menu` cible.
         * @param string $template_id Identifiant du template.
         * @param string $mode        Mode ciblé : `menu` | `header` | `footer`.
         *                            Pour un template `menu`, `mode` doit valoir
         *                            `menu` (et écrase `_wtm_config`). Pour un
         *                            template `header`/`footer`, le mode doit
         *                            correspondre au type du template.
         * @return true|\WP_Error True en cas de succès, WP_Error sinon.
         */
        public static function apply_to_menu( $menu_id, $template_id, $mode = 'menu' ) {
                $menu_id = absint( $menu_id );
                if ( ! $menu_id || 'wtm_menu' !== get_post_type( $menu_id ) ) {
                        return new \WP_Error( 'wtm_template_invalid_menu', __( 'Menu invalide.', 'woo-total-menu' ), array( 'status' => 404 ) );
                }

                $template = self::get( $template_id );
                if ( ! $template ) {
                        return new \WP_Error( 'wtm_template_not_found', __( 'Template introuvable.', 'woo-total-menu' ), array( 'status' => 404 ) );
                }

                // Cohérence type template <-> mode demandé.
                if ( isset( $template['type'] ) && $template['type'] !== $mode ) {
                        return new \WP_Error(
                                'wtm_template_mode_mismatch',
                                /* translators: 1: type template, 2: mode demandé */
                                sprintf( __( 'Type de template "%1$s" incompatible avec le mode "%2$s".', 'woo-total-menu' ), $template['type'], $mode ),
                                array( 'status' => 400 )
                        );
                }

                $config = isset( $template['config'] ) ? $template['config'] : array();

                // Validation selon le type (menu = validate_config ; header/footer = validate_layout).
                // Les méthodes sont statiques sur Schema_Validator.
                if ( 'menu' === $mode ) {
                        $valid = Schema_Validator::validate_config( $config );
                } else {
                        $valid = Schema_Validator::validate_layout( $config );
                }
                if ( is_wp_error( $valid ) ) {
                        return new \WP_Error(
                                'wtm_template_invalid_config',
                                sprintf( __( 'Template invalide : %s', 'woo-total-menu' ), $valid->get_error_message() ),
                                array( 'status' => 500 )
                        );
                }

                // Sélection de la meta ciblée.
                $meta_key = '_wtm_config';
                if ( 'header' === $mode ) {
                        $meta_key = '_wtm_header_config';
                } elseif ( 'footer' === $mode ) {
                        $meta_key = '_wtm_footer_config';
                }

                $result = update_post_meta( $menu_id, $meta_key, wp_slash( wp_json_encode( $config ) ) );
                if ( false === $result ) {
                        return new \WP_Error( 'wtm_template_save_failed', __( 'Échec de l\'enregistrement du template.', 'woo-total-menu' ), array( 'status' => 500 ) );
                }

                /**
                 * Action déclenchée après application d'un template à un menu.
                 *
                 * @since 1.5.0
                 *
                 * @param int    $menu_id     ID du menu cible.
                 * @param string $template_id Identifiant du template appliqué.
                 * @param string $mode        Mode (`menu` | `header` | `footer`).
                 * @param array  $template    Template complet.
                 */
                do_action( 'wtm_template_applied', $menu_id, $template_id, $mode, $template );

                return true;
        }

        // ───────────────────────────────────────────────────────────────────
        // Templates intégrés — MENUS.
        // ───────────────────────────────────────────────────────────────────

        /**
         * M1 — Menu horizontal simple (4 liens).
         *
         * Idéal pour un site vitrine ou un blog.
         *
         * @return array<string,mixed>
         */
        private static function menu_simple_horizontal() {
                return array(
                        'id'          => 'menu-simple-horizontal',
                        'name'        => __( 'Menu horizontal simple', 'woo-total-menu' ),
                        'description' => __( '4 liens plats (Accueil, Boutique, Blog, Contact) pour un site vitrine ou un blog.', 'woo-total-menu' ),
                        'type'        => 'menu',
                        'category'    => 'blog',
                        'thumbnail'   => 'menu-simple',
                        'preview'     => 'Accueil | Boutique | Blog | Contact',
                        'tags'        => array( 'simple', 'horizontal', 'blog', 'vitrine' ),
                        'config'      => array(
                                'version' => 1,
                                'items'   => array(
                                        array( 'id' => 'm1-home', 'type' => 'link', 'label' => 'Accueil', 'url' => '/' ),
                                        array( 'id' => 'm1-shop', 'type' => 'link', 'label' => 'Boutique', 'url' => '/shop/' ),
                                        array( 'id' => 'm1-blog', 'type' => 'link', 'label' => 'Blog', 'url' => '/blog/' ),
                                        array( 'id' => 'm1-contact', 'type' => 'link', 'label' => 'Contact', 'url' => '/contact/' ),
                                ),
                                'settings' => array(
                                        'sticky'            => false,
                                        'mobile_behavior'   => 'offcanvas',
                                        'mobile_breakpoint' => 768,
                                ),
                        ),
                );
        }

        /**
         * M2 — Méga menu boutique de mode (2 mega containers + widgets).
         *
         * @return array<string,mixed>
         */
        private static function menu_mega_mode() {
                return array(
                        'id'          => 'menu-mega-mode',
                        'name'        => __( 'Méga menu boutique de mode', 'woo-total-menu' ),
                        'description' => __( '2 méga conteneurs (Femmes, Hommes) avec colonnes liens + grille produits + bannière promo.', 'woo-total-menu' ),
                        'type'        => 'menu',
                        'category'    => 'ecommerce',
                        'thumbnail'   => 'menu-mega',
                        'preview'     => 'Femmes ▾ | Hommes ▾ | Blog | Contact',
                        'tags'        => array( 'mega', 'mode', 'ecommerce', 'femmes', 'hommes' ),
                        'config'      => array(
                                'version' => 1,
                                'items'   => array(
                                        array(
                                                'id'       => 'm2-femmes',
                                                'type'     => 'mega_container',
                                                'label'    => 'Femmes',
                                                'trigger'  => 'hover',
                                                'width'    => 1000,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm2-col-f1',
                                                                'type'     => 'column',
                                                                'width'    => 3,
                                                                'children' => array(
                                                                        array( 'id' => 'm2-t1', 'type' => 'title', 'label' => 'Vêtements' ),
                                                                        array( 'id' => 'm2-l1', 'type' => 'link', 'label' => 'Robes', 'url' => '/categorie/robes/' ),
                                                                        array( 'id' => 'm2-l2', 'type' => 'link', 'label' => 'Jupes', 'url' => '/categorie/jupes/' ),
                                                                        array( 'id' => 'm2-l3', 'type' => 'link', 'label' => 'Pantalons', 'url' => '/categorie/pantalons/' ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'       => 'm2-col-f2',
                                                                'type'     => 'column',
                                                                'width'    => 3,
                                                                'children' => array(
                                                                        array( 'id' => 'm2-t2', 'type' => 'title', 'label' => 'Accessoires' ),
                                                                        array( 'id' => 'm2-l4', 'type' => 'link', 'label' => 'Sacs', 'url' => '/categorie/sacs/' ),
                                                                        array( 'id' => 'm2-l5', 'type' => 'link', 'label' => 'Ceintures', 'url' => '/categorie/ceintures/' ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'       => 'm2-col-f3',
                                                                'type'     => 'column',
                                                                'width'    => 6,
                                                                'children' => array(
                                                                        array(
                                                                                'id'             => 'm2-w1',
                                                                                'type'           => 'widget',
                                                                                'widget_type'    => 'product_grid',
                                                                                'widget_settings' => array(
                                                                                        'columns'       => 2,
                                                                                        'product_source' => 'featured',
                                                                                        'limit'         => 2,
                                                                                ),
                                                                                'label'          => 'Sélection de la semaine',
                                                                        ),
                                                                        array(
                                                                                'id'             => 'm2-w2',
                                                                                'type'           => 'widget',
                                                                                'widget_type'    => 'banner',
                                                                                'widget_settings' => array(
                                                                                        'image_url' => '/wp-content/uploads/2026/promo-femmes.jpg',
                                                                                        'link_url'  => '/promotions/femmes/',
                                                                                        'alt'       => 'Promo Femmes -25%',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'm2-hommes',
                                                'type'     => 'mega_container',
                                                'label'    => 'Hommes',
                                                'trigger'  => 'hover',
                                                'width'    => 800,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm2-col-h1',
                                                                'type'     => 'column',
                                                                'width'    => 6,
                                                                'children' => array(
                                                                        array( 'id' => 'm2-t3', 'type' => 'title', 'label' => 'Vêtements' ),
                                                                        array( 'id' => 'm2-l6', 'type' => 'link', 'label' => 'Chemises', 'url' => '/categorie/chemises/' ),
                                                                        array( 'id' => 'm2-l7', 'type' => 'link', 'label' => 'T-shirts', 'url' => '/categorie/t-shirts/' ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'       => 'm2-col-h2',
                                                                'type'     => 'column',
                                                                'width'    => 6,
                                                                'children' => array(
                                                                        array(
                                                                                'id'              => 'm2-w3',
                                                                                'type'            => 'widget',
                                                                                'widget_type'     => 'category_grid',
                                                                                'widget_settings' => array(
                                                                                        'columns'     => 2,
                                                                                        'categories'  => array( 18, 19, 20, 21 ),
                                                                                        'show_images' => true,
                                                                                        'show_counts' => true,
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array( 'id' => 'm2-blog', 'type' => 'link', 'label' => 'Blog', 'url' => '/blog/' ),
                                        array( 'id' => 'm2-contact', 'type' => 'link', 'label' => 'Contact', 'url' => '/contact/' ),
                                ),
                                'settings' => array(
                                        'sticky'            => true,
                                        'mobile_behavior'   => 'offcanvas',
                                        'mobile_breakpoint' => 768,
                                ),
                        ),
                );
        }

        /**
         * M3 — Méga menu électronique (catégories + produits phares + banner).
         *
         * @return array<string,mixed>
         */
        private static function menu_mega_electronics() {
                return array(
                        'id'          => 'menu-mega-electronics',
                        'name'        => __( 'Méga menu électronique', 'woo-total-menu' ),
                        'description' => __( 'Catégories Smartphones / Ordinateurs / TV + grille produits + bannière promo high-tech.', 'woo-total-menu' ),
                        'type'        => 'menu',
                        'category'    => 'electronics',
                        'thumbnail'   => 'menu-mega',
                        'preview'     => 'Smartphones ▾ | Ordinateurs ▾ | TV ▾ | Promos',
                        'tags'        => array( 'mega', 'electronic', 'high-tech', 'ecommerce' ),
                        'config'      => array(
                                'version' => 1,
                                'items'   => array(
                                        array(
                                                'id'       => 'm3-smartphones',
                                                'type'     => 'mega_container',
                                                'label'    => 'Smartphones',
                                                'trigger'  => 'hover',
                                                'width'    => 900,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm3-col-s1',
                                                                'type'     => 'column',
                                                                'width'    => 4,
                                                                'children' => array(
                                                                        array( 'id' => 'm3-ts1', 'type' => 'title', 'label' => 'Marques' ),
                                                                        array( 'id' => 'm3-s1', 'type' => 'link', 'label' => 'Apple', 'url' => '/categorie/smartphones/apple/' ),
                                                                        array( 'id' => 'm3-s2', 'type' => 'link', 'label' => 'Samsung', 'url' => '/categorie/smartphones/samsung/' ),
                                                                        array( 'id' => 'm3-s3', 'type' => 'link', 'label' => 'Xiaomi', 'url' => '/categorie/smartphones/xiaomi/' ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'       => 'm3-col-s2',
                                                                'type'     => 'column',
                                                                'width'    => 8,
                                                                'children' => array(
                                                                        array(
                                                                                'id'              => 'm3-ws1',
                                                                                'type'            => 'widget',
                                                                                'widget_type'     => 'product_grid',
                                                                                'widget_settings' => array(
                                                                                        'columns'       => 4,
                                                                                        'product_source' => 'recent',
                                                                                        'limit'         => 4,
                                                                                ),
                                                                                'label'          => 'Nouveautés',
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'm3-ordinateurs',
                                                'type'     => 'mega_container',
                                                'label'    => 'Ordinateurs',
                                                'trigger'  => 'hover',
                                                'width'    => 700,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm3-col-o1',
                                                                'type'     => 'column',
                                                                'width'    => 6,
                                                                'children' => array(
                                                                        array( 'id' => 'm3-to1', 'type' => 'title', 'label' => 'Portables' ),
                                                                        array( 'id' => 'm3-o1', 'type' => 'link', 'label' => 'Ultrabooks', 'url' => '/categorie/ordinateurs/ultrabooks/' ),
                                                                        array( 'id' => 'm3-o2', 'type' => 'link', 'label' => 'Gaming', 'url' => '/categorie/ordinateurs/gaming/' ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'       => 'm3-col-o2',
                                                                'type'     => 'column',
                                                                'width'    => 6,
                                                                'children' => array(
                                                                        array( 'id' => 'm3-to2', 'type' => 'title', 'label' => 'Bureautique' ),
                                                                        array( 'id' => 'm3-o3', 'type' => 'link', 'label' => 'Tour', 'url' => '/categorie/ordinateurs/tour/' ),
                                                                        array( 'id' => 'm3-o4', 'type' => 'link', 'label' => 'Tout-en-un', 'url' => '/categorie/ordinateurs/tout-en-un/' ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'm3-tv',
                                                'type'     => 'mega_container',
                                                'label'    => 'TV & Son',
                                                'trigger'  => 'hover',
                                                'width'    => 800,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm3-col-t1',
                                                                'type'     => 'column',
                                                                'width'    => 12,
                                                                'children' => array(
                                                                        array(
                                                                                'id'              => 'm3-wt1',
                                                                                'type'            => 'widget',
                                                                                'widget_type'     => 'banner',
                                                                                'widget_settings' => array(
                                                                                        'image_url' => '/wp-content/uploads/2026/promo-tv.jpg',
                                                                                        'link_url'  => '/promotions/tv/',
                                                                                        'alt'       => 'TV 4K -30%',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'sticky'            => true,
                                        'mobile_behavior'   => 'accordion',
                                        'mobile_breakpoint' => 768,
                                ),
                        ),
                );
        }

        /**
         * M4 — Menu vertical sidebar (catalogue + filtres).
         *
         * @return array<string,mixed>
         */
        private static function menu_vertical_sidebar() {
                return array(
                        'id'          => 'menu-vertical-sidebar',
                        'name'        => __( 'Menu vertical sidebar', 'woo-total-menu' ),
                        'description' => __( 'Menu vertical pour sidebar : 3 catégories + sous-catégories + widget filtres WooCommerce.', 'woo-total-menu' ),
                        'type'        => 'menu',
                        'category'    => 'ecommerce',
                        'thumbnail'   => 'menu-vertical',
                        'preview'     => '☰ Catégories (Femmes / Hommes / Enfants) + Filtres',
                        'tags'        => array( 'vertical', 'sidebar', 'filtres', 'catalogue' ),
                        'config'      => array(
                                'version' => 1,
                                'items'   => array(
                                        array(
                                                'id'       => 'm4-femmes',
                                                'type'     => 'mega_container',
                                                'label'    => 'Femmes',
                                                'trigger'  => 'click',
                                                'width'    => 300,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm4-col-f1',
                                                                'type'     => 'column',
                                                                'width'    => 12,
                                                                'children' => array(
                                                                        array( 'id' => 'm4-l1', 'type' => 'link', 'label' => 'Robes', 'url' => '/categorie/robes/' ),
                                                                        array( 'id' => 'm4-l2', 'type' => 'link', 'label' => 'Jupes', 'url' => '/categorie/jupes/' ),
                                                                        array( 'id' => 'm4-l3', 'type' => 'link', 'label' => 'Pantalons', 'url' => '/categorie/pantalons/' ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'm4-hommes',
                                                'type'     => 'mega_container',
                                                'label'    => 'Hommes',
                                                'trigger'  => 'click',
                                                'width'    => 300,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm4-col-h1',
                                                                'type'     => 'column',
                                                                'width'    => 12,
                                                                'children' => array(
                                                                        array( 'id' => 'm4-l4', 'type' => 'link', 'label' => 'Chemises', 'url' => '/categorie/chemises/' ),
                                                                        array( 'id' => 'm4-l5', 'type' => 'link', 'label' => 'T-shirts', 'url' => '/categorie/t-shirts/' ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'm4-enfants',
                                                'type'     => 'mega_container',
                                                'label'    => 'Enfants',
                                                'trigger'  => 'click',
                                                'width'    => 300,
                                                'children' => array(
                                                        array(
                                                                'id'       => 'm4-col-e1',
                                                                'type'     => 'column',
                                                                'width'    => 12,
                                                                'children' => array(
                                                                        array( 'id' => 'm4-l6', 'type' => 'link', 'label' => 'Bébés', 'url' => '/categorie/enfants/bebes/' ),
                                                                        array( 'id' => 'm4-l7', 'type' => 'link', 'label' => 'Garçons', 'url' => '/categorie/enfants/garcons/' ),
                                                                        array( 'id' => 'm4-l8', 'type' => 'link', 'label' => 'Filles', 'url' => '/categorie/enfants/filles/' ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'              => 'm4-filtres',
                                                'type'            => 'widget',
                                                'widget_type'     => 'filters',
                                                'widget_settings' => array(
                                                        'show_price'    => true,
                                                        'show_categories' => true,
                                                        'show_attributes' => true,
                                                ),
                                                'label'           => 'Filtrer',
                                        ),
                                ),
                                'settings' => array(
                                        'sticky'            => false,
                                        'mobile_behavior'   => 'accordion',
                                        'mobile_breakpoint' => 768,
                                ),
                        ),
                );
        }

        // ───────────────────────────────────────────────────────────────────
        // Templates intégrés — HEADERS.
        // ───────────────────────────────────────────────────────────────────

        /**
         * H1 — Header e-commerce classique (3 colonnes).
         *
         * @return array<string,mixed>
         */
        private static function header_ecommerce_classic() {
                return array(
                        'id'          => 'header-ecommerce-classic',
                        'name'        => __( 'Header e-commerce classique', 'woo-total-menu' ),
                        'description' => __( 'Header 3 colonnes : Logo | Menu centré | Recherche + Panier + Compte. Le standard des boutiques WooCommerce.', 'woo-total-menu' ),
                        'type'        => 'header',
                        'category'    => 'ecommerce',
                        'thumbnail'   => 'header-3cols',
                        'preview'     => 'Logo | Menu | Recherche + Panier + Compte',
                        'tags'        => array( 'header', 'ecommerce', 'logo', 'menu', 'panier', 'recherche' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'      => 'h1-row-main',
                                                'columns' => array(
                                                        array(
                                                                'id'      => 'h1-col-logo',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h1-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array(
                                                                                        'max_width' => 180,
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'h1-col-menu',
                                                                'width'   => 6,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h1-menu',
                                                                                'type'     => 'menu',
                                                                                'settings' => array(
                                                                                        'location' => 'primary',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'h1-col-actions',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h1-search',
                                                                                'type'     => 'search',
                                                                                'settings' => array(
                                                                                        'placeholder'      => 'Rechercher un produit…',
                                                                                        'live_suggestions' => true,
                                                                                        'min_chars'        => 3,
                                                                                ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'h1-cart',
                                                                                'type'     => 'cart',
                                                                                'settings' => array(
                                                                                        'show_total' => true,
                                                                                        'behavior'   => 'drawer',
                                                                                ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'h1-account',
                                                                                'type'     => 'button',
                                                                                'settings' => array(
                                                                                        'text'  => 'Mon compte',
                                                                                        'url'   => '/mon-compte/',
                                                                                        'style' => 'ghost',
                                                                                        'icon'  => 'user',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'sticky' => true,
                                ),
                        ),
                );
        }

        /**
         * H2 — Header minimaliste (2 colonnes).
         *
         * @return array<string,mixed>
         */
        private static function header_minimal() {
                return array(
                        'id'          => 'header-minimal',
                        'name'        => __( 'Header minimaliste', 'woo-total-menu' ),
                        'description' => __( 'Header épuré 2 colonnes : Logo à gauche, menu à droite. Idéal pour un portfolio ou un blog.', 'woo-total-menu' ),
                        'type'        => 'header',
                        'category'    => 'minimal',
                        'thumbnail'   => 'header-2cols',
                        'preview'     => 'Logo | Menu',
                        'tags'        => array( 'header', 'minimal', 'blog', 'portfolio' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'      => 'h2-row-main',
                                                'columns' => array(
                                                        array(
                                                                'id'      => 'h2-col-logo',
                                                                'width'   => 4,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h2-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array(
                                                                                        'max_width' => 140,
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'h2-col-menu',
                                                                'width'   => 8,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h2-menu',
                                                                                'type'     => 'menu',
                                                                                'settings' => array(
                                                                                        'location' => 'primary',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'sticky' => false,
                                ),
                        ),
                );
        }

        /**
         * H3 — Header promotionnel (2 rows : top bar + main).
         *
         * @return array<string,mixed>
         */
        private static function header_promo_banner() {
                return array(
                        'id'          => 'header-promo-banner',
                        'name'        => __( 'Header promotionnel', 'woo-total-menu' ),
                        'description' => __( 'Header 2 lignes : top bar promo + ligne principale (logo, menu, recherche, panier). Idéal pour les périodes de soldes.', 'woo-total-menu' ),
                        'type'        => 'header',
                        'category'    => 'ecommerce',
                        'thumbnail'   => 'header-2rows',
                        'preview'     => 'Livraison offerte dès 50€ | Logo + Menu + Recherche + Panier',
                        'tags'        => array( 'header', 'promo', 'banner', 'ecommerce', 'solde' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'      => 'h3-row-top',
                                                'settings' => array(
                                                        'background' => '#6C5CE7',
                                                        'height'     => 36,
                                                        'align'      => 'center',
                                                ),
                                                'columns' => array(
                                                        array(
                                                                'id'      => 'h3-col-top',
                                                                'width'   => 12,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h3-promo',
                                                                                'type'     => 'text',
                                                                                'settings' => array(
                                                                                        'content' => '🚚 Livraison offerte dès 50€ d\'achat — Profitez-en !',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'      => 'h3-row-main',
                                                'columns' => array(
                                                        array(
                                                                'id'      => 'h3-col-logo',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h3-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array( 'max_width' => 180 ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'h3-col-menu',
                                                                'width'   => 6,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h3-menu',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'primary' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'h3-col-actions',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h3-search',
                                                                                'type'     => 'search',
                                                                                'settings' => array(
                                                                                        'placeholder'      => 'Rechercher…',
                                                                                        'live_suggestions' => true,
                                                                                ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'h3-cart',
                                                                                'type'     => 'cart',
                                                                                'settings' => array( 'show_total' => true, 'behavior' => 'drawer' ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'sticky' => true,
                                ),
                        ),
                );
        }

        /**
         * H4 — Header sticky centré (logo centré, menu en dessous).
         *
         * @return array<string,mixed>
         */
        private static function header_sticky_centered() {
                return array(
                        'id'          => 'header-sticky-centered',
                        'name'        => __( 'Header sticky centré', 'woo-total-menu' ),
                        'description' => __( 'Header 2 lignes : logo centré + menu centré en dessous. Style éditorial / marque de luxe.', 'woo-total-menu' ),
                        'type'        => 'header',
                        'category'    => 'minimal',
                        'thumbnail'   => 'header-centered',
                        'preview'     => 'Logo (centré) / Menu (centré)',
                        'tags'        => array( 'header', 'centered', 'luxe', 'editorial', 'sticky' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'       => 'h4-row-logo',
                                                'settings' => array(
                                                        'align'  => 'center',
                                                        'padding_y' => 16,
                                                ),
                                                'columns'  => array(
                                                        array(
                                                                'id'      => 'h4-col-logo',
                                                                'width'   => 12,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h4-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array( 'max_width' => 220 ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'h4-row-menu',
                                                'settings' => array(
                                                        'align'      => 'center',
                                                        'sticky'     => true,
                                                        'background' => '#FFFFFF',
                                                        'border'     => 'bottom',
                                                ),
                                                'columns'  => array(
                                                        array(
                                                                'id'      => 'h4-col-menu',
                                                                'width'   => 12,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'h4-menu',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'primary' ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'sticky' => true,
                                ),
                        ),
                );
        }

        // ───────────────────────────────────────────────────────────────────
        // Templates intégrés — FOOTERS.
        // ───────────────────────────────────────────────────────────────────

        /**
         * F1 — Footer e-commerce 4 colonnes (about + 2 menus + newsletter).
         *
         * @return array<string,mixed>
         */
        private static function footer_ecommerce_4cols() {
                return array(
                        'id'          => 'footer-ecommerce-4cols',
                        'name'        => __( 'Footer e-commerce 4 colonnes', 'woo-total-menu' ),
                        'description' => __( 'Footer 4 colonnes : À propos + 2 menus de liens + bloc newsletter. Le footer e-commerce universel.', 'woo-total-menu' ),
                        'type'        => 'footer',
                        'category'    => 'ecommerce',
                        'thumbnail'   => 'footer-4cols',
                        'preview'     => 'À propos | Liens 1 | Liens 2 | Newsletter',
                        'tags'        => array( 'footer', 'ecommerce', 'newsletter', '4-colonnes' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'      => 'f1-row-main',
                                                'columns' => array(
                                                        array(
                                                                'id'      => 'f1-col-about',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f1-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array( 'max_width' => 120 ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'f1-about',
                                                                                'type'     => 'text',
                                                                                'settings' => array(
                                                                                        'content' => 'Boutique créée en 2026, spécialisée dans la mode éthique et responsable.',
                                                                                ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'f1-social',
                                                                                'type'     => 'social',
                                                                                'settings' => array(
                                                                                        'networks' => array( 'facebook', 'instagram', 'twitter' ),
                                                                                        'size'     => 24,
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f1-col-links-1',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f1-links-1',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-1' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f1-col-links-2',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f1-links-2',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-2' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f1-col-newsletter',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f1-newsletter',
                                                                                'type'     => 'newsletter',
                                                                                'settings' => array(
                                                                                        'title'          => 'Newsletter',
                                                                                        'placeholder'    => 'Votre email',
                                                                                        'button_text'    => 'S\'inscrire',
                                                                                        'provider'       => 'internal',
                                                                                        'success_message' => 'Merci ! Votre inscription a bien été prise en compte.',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'background' => '#F8F9FA',
                                ),
                        ),
                );
        }

        /**
         * F2 — Footer minimaliste (1 row : copyright + social).
         *
         * @return array<string,mixed>
         */
        private static function footer_minimal() {
                return array(
                        'id'          => 'footer-minimal',
                        'name'        => __( 'Footer minimaliste', 'woo-total-menu' ),
                        'description' => __( 'Footer 1 colonne : copyright + icônes sociales. Idéal pour un blog ou un portfolio minimaliste.', 'woo-total-menu' ),
                        'type'        => 'footer',
                        'category'    => 'minimal',
                        'thumbnail'   => 'footer-minimal',
                        'preview'     => '© 2026 Mon Site | Facebook · Twitter · Instagram',
                        'tags'        => array( 'footer', 'minimal', 'blog', 'social' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'       => 'f2-row-main',
                                                'settings' => array(
                                                        'align'      => 'center',
                                                        'padding_y'  => 24,
                                                        'background' => '#FFFFFF',
                                                        'border'     => 'top',
                                                ),
                                                'columns'  => array(
                                                        array(
                                                                'id'      => 'f2-col-main',
                                                                'width'   => 12,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f2-copy',
                                                                                'type'     => 'text',
                                                                                'settings' => array(
                                                                                        'content' => '© [year] Mon Site — Tous droits réservés.',
                                                                                ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'f2-social',
                                                                                'type'     => 'social',
                                                                                'settings' => array(
                                                                                        'networks' => array( 'facebook', 'twitter', 'instagram' ),
                                                                                        'size'     => 20,
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                        ),
                );
        }

        /**
         * F3 — Footer corporate 4 colonnes (logo+desc + 3 menus).
         *
         * @return array<string,mixed>
         */
        private static function footer_corporate_4cols() {
                return array(
                        'id'          => 'footer-corporate-4cols',
                        'name'        => __( 'Footer corporate 4 colonnes', 'woo-total-menu' ),
                        'description' => __( 'Footer corporate : logo + description + 3 menus (Société, Légal, Ressources). Pour sites institutionnels.', 'woo-total-menu' ),
                        'type'        => 'footer',
                        'category'    => 'corporate',
                        'thumbnail'   => 'footer-4cols',
                        'preview'     => 'Logo + desc | Société | Légal | Ressources',
                        'tags'        => array( 'footer', 'corporate', 'institutionnel', '4-colonnes' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'      => 'f3-row-main',
                                                'columns' => array(
                                                        array(
                                                                'id'      => 'f3-col-brand',
                                                                'width'   => 4,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f3-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array( 'max_width' => 160 ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'f3-desc',
                                                                                'type'     => 'text',
                                                                                'settings' => array(
                                                                                        'content' => 'Société fondée en 2026. Notre mission : fournir des solutions innovantes pour le commerce en ligne.',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f3-col-company',
                                                                'width'   => 2,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f3-company',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-company' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f3-col-legal',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f3-legal',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-legal' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f3-col-resources',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f3-resources',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-resources' ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                                'settings' => array(
                                        'background' => '#1A1A2E',
                                        'color'      => '#FFFFFF',
                                ),
                        ),
                );
        }

        /**
         * F4 — Footer sombre accessible (WCAG AA contrast).
         *
         * @return array<string,mixed>
         */
        private static function footer_dark_accessible() {
                return array(
                        'id'          => 'footer-dark-accessible',
                        'name'        => __( 'Footer sombre accessible', 'woo-total-menu' ),
                        'description' => __( 'Footer sombre contraste WCAG AA : 4 colonnes (logo + liens + liens + newsletter) sur fond #0F1419.', 'woo-total-menu' ),
                        'type'        => 'footer',
                        'category'    => 'minimal',
                        'thumbnail'   => 'footer-dark',
                        'preview'     => 'Logo + social | Liens | Liens | Newsletter',
                        'tags'        => array( 'footer', 'dark', 'accessible', 'wcag', 'newsletter' ),
                        'config'      => array(
                                'version' => 1,
                                'rows'    => array(
                                        array(
                                                'id'       => 'f4-row-main',
                                                'settings' => array(
                                                        'background' => '#0F1419',
                                                        'color'      => '#F5F5F5',
                                                        'padding_y'  => 48,
                                                ),
                                                'columns'  => array(
                                                        array(
                                                                'id'      => 'f4-col-brand',
                                                                'width'   => 4,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f4-logo',
                                                                                'type'     => 'logo',
                                                                                'settings' => array( 'max_width' => 140 ),
                                                                        ),
                                                                        array(
                                                                                'id'       => 'f4-social',
                                                                                'type'     => 'social',
                                                                                'settings' => array(
                                                                                        'networks' => array( 'facebook', 'twitter', 'instagram', 'linkedin' ),
                                                                                        'size'     => 22,
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f4-col-links-1',
                                                                'width'   => 2,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f4-links-1',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-1' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f4-col-links-2',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f4-links-2',
                                                                                'type'     => 'menu',
                                                                                'settings' => array( 'location' => 'footer-2' ),
                                                                        ),
                                                                ),
                                                        ),
                                                        array(
                                                                'id'      => 'f4-col-newsletter',
                                                                'width'   => 3,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f4-newsletter',
                                                                                'type'     => 'newsletter',
                                                                                'settings' => array(
                                                                                        'title'           => 'Restez informé',
                                                                                        'placeholder'     => 'Votre adresse email',
                                                                                        'button_text'     => 'S\'abonner',
                                                                                        'provider'        => 'internal',
                                                                                        'success_message' => 'Inscription confirmée. Merci !',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                        array(
                                                'id'       => 'f4-row-bottom',
                                                'settings' => array(
                                                        'background' => '#070A0D',
                                                        'color'      => '#A0A0A0',
                                                        'padding_y'  => 12,
                                                        'align'      => 'center',
                                                ),
                                                'columns'  => array(
                                                        array(
                                                                'id'      => 'f4-col-bottom',
                                                                'width'   => 12,
                                                                'modules' => array(
                                                                        array(
                                                                                'id'       => 'f4-copy',
                                                                                'type'     => 'text',
                                                                                'settings' => array(
                                                                                        'content' => '© [year] Mon Site — Conformité RGPD · Mentions légales · Politique de confidentialité',
                                                                                ),
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                        ),
                );
        }
}
