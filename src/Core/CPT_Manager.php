<?php
/**
 * Custom Post Type manager for wtm_menu.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class CPT_Manager
 *
 * Registers the `wtm_menu` Custom Post Type that stores every menu,
 * header and footer configuration as JSON metadata.
 *
 * The CPT is:
 * - NOT public (no single/archive views)
 * - REST-enabled (so /wp-json/wp/v2/wtm_menu works)
 * - Revision-enabled (for undo/redo)
 * - Block-editor disabled (we'll use our own builder)
 * - Excluded from search and sitemaps
 */
class CPT_Manager {

        /**
         * Hook suffix used by WP to identify the CPT edit screen.
         *
         * @var string
         */
        const POST_TYPE = WTM_CPT_MENU;

        /**
         * Supported menu types.
         *
         * @var array<string,string>
         */
        const MENU_TYPES = array(
                'horizontal' => 'Méga menu horizontal',
                'vertical'   => 'Menu vertical (sidebar)',
                'offcanvas'  => 'Menu off-canvas',
                'footer'     => 'Menu de pied de page',
        );

        /**
         * Default theme locations registered by the plugin.
         *
         * @var array<string,string>
         */
        const LOCATIONS = array(
                'primary' => 'Menu principal (header)',
                'footer'  => 'Pied de page',
                'sidebar' => 'Barre latérale',
                'mobile'  => 'Menu mobile dédié',
        );

        /**
         * Constructor — registers hooks.
         *
         * v1.1.5: hooks `wp_revisions_to_keep` to apply the `wtm_max_revisions`
         * filter (default 10, per spec §7.6) on the wtm_menu CPT.
         */
        public function __construct() {
                add_action( 'init', array( $this, 'register_post_type' ), 5 );
                add_action( 'init', array( $this, 'register_locations' ), 10 );
                add_filter( 'wp_insert_post_data', array( $this, 'default_menu_type_on_insert' ), 10, 2 );
                add_filter( 'wp_revisions_to_keep', array( $this, 'filter_revisions_to_keep' ), 10, 2 );
        }

        /**
         * Limit the number of revisions kept for wtm_menu posts.
         *
         * Spec §7.6 — default 10 revisions, filterable via `wtm_max_revisions`.
         *
         * @param int      $num  Number of revisions to keep (default WP_SETTING).
         * @param \WP_Post $post Post being saved.
         * @return int
         */
        public function filter_revisions_to_keep( $num, $post ) {
                if ( $post && self::POST_TYPE === $post->post_type ) {
                        /**
                         * Filter the maximum number of revisions kept for WTM menus.
                         *
                         * @since 1.1.5
                         * @param int $num Default 10.
                         * @param \WP_Post $post Menu post.
                         */
                        return (int) apply_filters( 'wtm_max_revisions', 10, $post );
                }
                return $num;
        }

        /**
         * Register the wtm_menu Custom Post Type.
         *
         * @return void
         */
        public function register_post_type() {
                $labels = array(
                        'name'                  => __( 'Menus Woo Total', 'woo-total-menu' ),
                        'singular_name'         => __( 'Menu Woo Total', 'woo-total-menu' ),
                        'add_new'               => __( 'Ajouter un menu', 'woo-total-menu' ),
                        'add_new_item'          => __( 'Ajouter un nouveau menu', 'woo-total-menu' ),
                        'edit_item'             => __( 'Modifier le menu', 'woo-total-menu' ),
                        'new_item'              => __( 'Nouveau menu', 'woo-total-menu' ),
                        'view_item'             => __( 'Voir le menu', 'woo-total-menu' ),
                        'view_items'            => __( 'Voir les menus', 'woo-total-menu' ),
                        'search_items'          => __( 'Rechercher des menus', 'woo-total-menu' ),
                        'not_found'             => __( 'Aucun menu trouvé.', 'woo-total-menu' ),
                        'not_found_in_trash'    => __( 'Aucun menu dans la corbeille.', 'woo-total-menu' ),
                        'all_items'             => __( 'Tous les menus', 'woo-total-menu' ),
                        'archives'              => __( 'Archives des menus', 'woo-total-menu' ),
                        'attributes'            => __( 'Attributs du menu', 'woo-total-menu' ),
                        'insert_into_item'      => __( 'Insérer dans le menu', 'woo-total-menu' ),
                        'uploaded_to_this_item' => __( 'Téléversé vers ce menu', 'woo-total-menu' ),
                        'filter_items_list'     => __( 'Filtrer la liste des menus', 'woo-total-menu' ),
                        'items_list_navigation' => __( 'Navigation dans la liste des menus', 'woo-total-menu' ),
                        'items_list'            => __( 'Liste des menus', 'woo-total-menu' ),
                        'menu_name'             => __( 'Menus WTM', 'woo-total-menu' ),
                );

                $args = array(
                        'labels'              => $labels,
                        'description'         => __( 'Stocke les menus, headers et footers créés avec Woo Total Menu.', 'woo-total-menu' ),
                        'public'              => false,
                        'publicly_queryable'  => false,
                        'show_ui'             => true,
                        'show_in_menu'        => false, // Hidden — we'll expose it under our own admin page (v1.0.2).
                        'show_in_nav_menus'   => false,
                        'show_in_rest'        => true,  // Enable REST at /wp-json/wp/v2/wtm_menu.
                        'rest_base'           => 'wtm_menus',
                        'rest_controller_class' => 'WP_REST_Posts_Controller',
                        'has_archive'         => false,
                        'rewrite'             => false,
                        'exclude_from_search' => true,
                        'capability_type'     => array( 'wtm_menu', 'wtm_menus' ), // Pluralized primitives.
                        'map_meta_cap'        => true,
                        'hierarchical'        => false,
                        'supports'            => array(
                                'title',
                                'author',
                                'revisions',
                                'custom-fields',
                        ),
                        'menu_position'       => 58,
                        'menu_icon'           => 'dashicons-menu',
                        'show_in_admin_bar'   => false,
                );

                register_post_type( self::POST_TYPE, $args );
        }

        /**
         * Register the default menu locations.
         *
         * These locations are also exposed as nav_menu_locations so that themes
         * that want to display WTM menus through standard locations can do so.
         *
         * @return void
         */
        public function register_locations() {
                $existing = get_theme_support( 'menus' );
                if ( false === $existing ) {
                        add_theme_support( 'menus' );
                }

                $locations = (array) get_registered_nav_menus();
                foreach ( self::LOCATIONS as $slug => $desc ) {
                        if ( ! isset( $locations[ 'wtm_' . $slug ] ) ) {
                                $locations[ 'wtm_' . $slug ] = sprintf( '[WTM] %s', $desc );
                        }
                }
                register_nav_menus( $locations );
        }

        /**
         * Set default menu_type and config when a new wtm_menu is created.
         *
         * @param array $data    Sanitized post data.
         * @param array $postarr Raw post data.
         * @return array
         */
        public function default_menu_type_on_insert( $data, $postarr ) {
                if ( self::POST_TYPE !== $data['post_type'] ) {
                        return $data;
                }
                // Default title if empty.
                if ( empty( trim( $data['post_title'] ) ) ) {
                        $data['post_title'] = sprintf(
                                /* translators: %d post ID */
                                __( 'Menu sans titre #%d', 'woo-total-menu' ),
                                isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0
                        );
                }
                return $data;
        }

        /**
         * Helper: get all menu types.
         *
         * @return array<string,string>
         */
        public static function get_menu_types() {
                /**
                 * Filter the list of available menu types.
                 *
                 * @since 1.0.1
                 * @param array $types type_slug => Human label.
                 */
                return apply_filters( 'wtm_menu_types', self::MENU_TYPES );
        }

        /**
         * Helper: get all locations.
         *
         * @return array<string,string>
         */
        public static function get_locations() {
                /**
                 * Filter the list of available menu locations.
                 *
                 * @since 1.0.1
                 * @param array $locations location_slug => Human label.
                 */
                return apply_filters( 'wtm_locations', self::LOCATIONS );
        }
}
