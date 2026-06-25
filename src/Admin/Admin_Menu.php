<?php
/**
 * Admin menu orchestrator.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

use WooTotalMenu\Admin\Pages\Dashboard;
use WooTotalMenu\Admin\Pages\Menus_List;
use WooTotalMenu\Admin\Pages\Settings;
use WooTotalMenu\Admin\Pages\About;

/**
 * Class Admin_Menu
 *
 * Registers the top-level menu and all submenus of Woo Total Menu:
 *
 *   Woo Total Menu
 *     ├─ Tableau de bord      (wtm-dashboard)
 *     ├─ Menus                (wtm-menus)
 *     ├─ Réglages             (wtm-settings)
 *     └─ À propos             (wtm-about)
 *
 * The "About" page is also the default subpage when the plugin
 * is first activated, so the user lands on something useful.
 */
class Admin_Menu {

        const CAPABILITY = 'wtm_manage_menus';

        /**
         * Slug of the top-level menu.
         *
         * @var string
         */
        const SLUG_ROOT = 'wtm-dashboard';

        /**
         * Constructor — registers hooks.
         */
        public function __construct() {
                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
                add_action( 'admin_init', array( $this, 'handle_actions' ) );
        }

        /**
         * Register the top-level menu and submenus.
         *
         * @return void
         */
        public function register_menu() {
                // Top-level: Dashboard.
                $hook = add_menu_page(
                        __( 'Woo Total Menu', 'woo-total-menu' ),
                        __( 'Woo Total Menu', 'woo-total-menu' ),
                        self::CAPABILITY,
                        self::SLUG_ROOT,
                        array( Dashboard::class, 'render' ),
                        'dashicons-menu',
                        58
                );

                // Submenu: Dashboard (duplicate of root, but with explicit label).
                add_submenu_page(
                        self::SLUG_ROOT,
                        __( 'Tableau de bord', 'woo-total-menu' ),
                        __( 'Tableau de bord', 'woo-total-menu' ),
                        self::CAPABILITY,
                        self::SLUG_ROOT,
                        array( Dashboard::class, 'render' )
                );

                // Submenu: Menus list.
                add_submenu_page(
                        self::SLUG_ROOT,
                        __( 'Menus', 'woo-total-menu' ),
                        __( 'Menus', 'woo-total-menu' ),
                        self::CAPABILITY,
                        'wtm-menus',
                        array( Menus_List::class, 'render' )
                );

                // Submenu: Settings.
                add_submenu_page(
                        self::SLUG_ROOT,
                        __( 'Réglages', 'woo-total-menu' ),
                        __( 'Réglages', 'woo-total-menu' ),
                        'wtm_manage_settings',
                        'wtm-settings',
                        array( Settings::class, 'render' )
                );

                // Submenu: Builder (full-screen, no parent in menu).
                add_submenu_page(
                        self::SLUG_ROOT,
                        __( 'Builder', 'woo-total-menu' ),
                        __( 'Builder', 'woo-total-menu' ),
                        self::CAPABILITY,
                        'wtm-builder',
                        array( \WooTotalMenu\Admin\Pages\Builder::class, 'render' )
                );

                // Submenu: About.
                add_submenu_page(
                        self::SLUG_ROOT,
                        __( 'À propos', 'woo-total-menu' ),
                        __( 'À propos', 'woo-total-menu' ),
                        self::CAPABILITY,
                        'wtm-about',
                        array( About::class, 'render' )
                );
        }

        /**
         * Handle action redirects (e.g. "create menu", "delete menu", "duplicate menu").
         *
         * These are routed here via admin_init so we can safely redirect
         * after the action is performed.
         *
         * @return void
         */
        public function handle_actions() {
                if ( ! isset( $_GET['page'] ) || ! isset( $_GET['wtm_action'] ) ) {
                        return;
                }

                $page   = sanitize_key( wp_unslash( $_GET['page'] ) );
                $action = sanitize_key( wp_unslash( $_GET['wtm_action'] ) );

                if ( strpos( $page, 'wtm-' ) !== 0 ) {
                        return;
                }

                // Capability check.
                if ( ! current_user_can( self::CAPABILITY ) ) {
                        wp_die( esc_html__( 'You do not have permission to perform this action.', 'woo-total-menu' ), 403 );
                }

                // Verify nonce.
                if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wtm_action_' . $action ) ) {
                        wp_die( esc_html__( 'Invalid nonce. Please go back and try again.', 'woo-total-menu' ), 403 );
                }

                switch ( $action ) {
                        case 'create_menu':
                                $this->action_create_menu();
                                break;
                        case 'delete_menu':
                                $this->action_delete_menu();
                                break;
                        case 'duplicate_menu':
                                $this->action_duplicate_menu();
                                break;
                        case 'toggle_status':
                                $this->action_toggle_status();
                                break;
                }
        }

        /**
         * Action: create a new menu and redirect to its edit screen.
         *
         * @return void
         */
        private function action_create_menu() {
                $menu_type = isset( $_GET['menu_type'] ) ? sanitize_key( wp_unslash( $_GET['menu_type'] ) ) : 'horizontal';
                $location  = isset( $_GET['location'] ) ? sanitize_key( wp_unslash( $_GET['location'] ) ) : 'primary';
                $title     = isset( $_GET['menu_title'] ) ? sanitize_text_field( wp_unslash( $_GET['menu_title'] ) ) : '';

                if ( empty( $title ) ) {
                        $title = sprintf(
                                /* translators: %s date */
                                __( 'Menu du %s', 'woo-total-menu' ),
                                wp_date( 'd/m/Y H:i' )
                        );
                }

                $post_id = wp_insert_post(
                        array(
                                'post_type'   => WTM_CPT_MENU,
                                'post_title'  => $title,
                                'post_status' => 'publish',
                        ),
                        true
                );

                if ( is_wp_error( $post_id ) ) {
                        wp_die( esc_html( $post_id->get_error_message() ) );
                }

                update_post_meta( $post_id, '_wtm_menu_type', $menu_type );
                update_post_meta( $post_id, '_wtm_location',  $location );
                update_post_meta( $post_id, '_wtm_version',   WTM_DB_VERSION );
                update_post_meta( $post_id, '_wtm_config',    wp_slash( wp_json_encode( array(
                        'version' => 1,
                        'items'   => array(),
                ) ) ) );

                // Invalidate cache.
                $cache = wtm()->get( 'cache' );
                if ( $cache ) {
                        $cache->invalidate_menu( $post_id );
                }

                // Redirect to the post edit screen.
                $edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit&wtm_created=1' );
                wp_safe_redirect( $edit_url );
                exit;
        }

        /**
         * Action: delete a menu.
         *
         * @return void
         */
        private function action_delete_menu() {
                $menu_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0;
                if ( ! $menu_id ) {
                        wp_die( esc_html__( 'Invalid menu ID.', 'woo-total-menu' ) );
                }

                // Check ownership.
                $post = get_post( $menu_id );
                if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
                        wp_die( esc_html__( 'Menu not found.', 'woo-total-menu' ) );
                }

                wp_delete_post( $menu_id, true );

                $cache = wtm()->get( 'cache' );
                if ( $cache ) {
                        $cache->invalidate_menu( $menu_id );
                }

                wp_safe_redirect(
                        add_query_arg(
                                array(
                                        'page'        => 'wtm-menus',
                                        'wtm_deleted' => 1,
                                ),
                                admin_url( 'admin.php' )
                        )
                );
                exit;
        }

        /**
         * Action: duplicate a menu.
         *
         * @return void
         */
        private function action_duplicate_menu() {
                $menu_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0;
                if ( ! $menu_id ) {
                        wp_die( esc_html__( 'Invalid menu ID.', 'woo-total-menu' ) );
                }

                $src = get_post( $menu_id );
                if ( ! $src || WTM_CPT_MENU !== $src->post_type ) {
                        wp_die( esc_html__( 'Menu not found.', 'woo-total-menu' ) );
                }

                $new_id = wp_insert_post(
                        array(
                                'post_type'   => WTM_CPT_MENU,
                                'post_title'  => sprintf(
                                        /* translators: %s original menu title */
                                        __( '%s (copie)', 'woo-total-menu' ),
                                        $src->post_title
                                ),
                                'post_status' => 'publish',
                        ),
                        true
                );

                if ( is_wp_error( $new_id ) ) {
                        wp_die( esc_html( $new_id->get_error_message() ) );
                }

                // Copy meta.
                foreach ( array( '_wtm_location', '_wtm_menu_type', '_wtm_config', '_wtm_header_config', '_wtm_footer_config', '_wtm_version' ) as $key ) {
                        $val = get_post_meta( $menu_id, $key, true );
                        if ( '' !== $val && null !== $val ) {
                                update_post_meta( $new_id, $key, $val );
                        }
                }

                $cache = wtm()->get( 'cache' );
                if ( $cache ) {
                        $cache->invalidate_menu( $new_id );
                }

                wp_safe_redirect(
                        add_query_arg(
                                array(
                                        'page'         => 'wtm-menus',
                                        'wtm_duplicated' => 1,
                                ),
                                admin_url( 'admin.php' )
                        )
                );
                exit;
        }

        /**
         * Action: toggle publish/draft status.
         *
         * @return void
         */
        private function action_toggle_status() {
                $menu_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0;
                if ( ! $menu_id ) {
                        wp_die( esc_html__( 'Invalid menu ID.', 'woo-total-menu' ) );
                }

                $post = get_post( $menu_id );
                if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
                        wp_die( esc_html__( 'Menu not found.', 'woo-total-menu' ) );
                }

                $new_status = ( 'publish' === $post->post_status ) ? 'draft' : 'publish';
                wp_update_post(
                        array(
                                'ID'          => $menu_id,
                                'post_status' => $new_status,
                        )
                );

                wp_safe_redirect(
                        add_query_arg(
                                array(
                                        'page'           => 'wtm-menus',
                                        'wtm_toggled'    => 1,
                                        'wtm_new_status' => $new_status,
                                ),
                                admin_url( 'admin.php' )
                        )
                );
                exit;
        }

        /**
         * Enqueue minimal admin styles for all WTM pages.
         *
         * @param string $hook Current admin page hook.
         * @return void
         */
        public function enqueue_admin_styles( $hook ) {
                // Only on our pages.
                if ( false === strpos( $hook, 'wtm-' ) && 'toplevel_page_wtm-dashboard' !== $hook ) {
                        return;
                }

                // Builder page has its own asset bundle.
                if ( false !== strpos( $hook, 'wtm-builder' ) ) {
                        $this->enqueue_builder_assets();
                        return;
                }

                wp_register_style( 'wtm-admin', false, array(), WTM_VERSION );
                wp_enqueue_style( 'wtm-admin' );
                wp_add_inline_style( 'wtm-admin', $this->get_admin_css() );
        }

        /**
         * Enqueue the React builder assets (compiled by @wordpress/scripts).
         *
         * Looks for build/index.js and build/style-index.css.
         * If they don't exist (dev hasn't run `npm run build`), shows an admin notice.
         *
         * @return void
         */
        private function enqueue_builder_assets() {
                $build_dir   = WTM_PLUGIN_DIR . 'build/';
                $index_js    = $build_dir . 'index.js';
                $style_css   = $build_dir . 'style-index.css';

                if ( ! file_exists( $index_js ) ) {
                        add_action( 'admin_notices', function () {
                                echo '<div class="notice notice-error"><p>';
                                echo '<strong>' . esc_html__( 'Woo Total Menu Builder', 'woo-total-menu' ) . '</strong>: ';
                                esc_html_e( 'Les assets React ne sont pas compilés. Exécutez `npm install && npm run build` dans le dossier du plugin.', 'woo-total-menu' );
                                echo '</p></div>';
                        } );
                        return;
                }

                // Register & enqueue the JS bundle.
                $asset_file = $build_dir . 'index.asset.php';
                $asset_data = file_exists( $asset_file ) ? include $asset_file : array(
                        'dependencies' => array(
                                'wp-element',
                                'wp-data',
                                'wp-api-fetch',
                                'wp-i18n',
                                'wp-url',
                        ),
                        'version' => WTM_VERSION,
                );

                wp_register_script(
                        'wtm-builder',
                        WTM_PLUGIN_URL . 'build/index.js',
                        $asset_data['dependencies'],
                        $asset_data['version'],
                        true
                );
                wp_enqueue_script( 'wtm-builder' );

                // Register & enqueue the CSS bundle (if exists).
                if ( file_exists( $style_css ) ) {
                        wp_register_style(
                                'wtm-builder-style',
                                WTM_PLUGIN_URL . 'build/style-index.css',
                                array(),
                                WTM_VERSION
                        );
                        wp_enqueue_style( 'wtm-builder-style' );
                }

                // Pass initial data to JS.
                $rest_url           = esc_url_raw( rest_url( 'wtm/v1' ) );
                $rest_nonce         = wp_create_nonce( 'wp_rest' );
                // v1.1.4 — preview iframe URL (admin-ajax endpoint serving a
                // self-contained HTML document driven by postMessage). We use
                // admin-ajax rather than REST because <iframe src> cannot send
                // the X-WP-Nonce header required by REST cookie auth.
                $preview_frame_url  = esc_url_raw(
                        \WooTotalMenu\Frontend\Preview_Controller::get_endpoint_url()
                );

                wp_localize_script(
                        'wtm-builder',
                        'wtmBuilderData',
                        array(
                                'restUrl'          => $rest_url,
                                'restNonce'        => $rest_nonce,
                                'previewFrameUrl'  => $preview_frame_url,
                        )
                );
        }

        /**
         * Get shared admin CSS (used across all WTM admin pages).
         *
         * @return string
         */
        private function get_admin_css() {
                return <<<'CSS'
                /* WTM admin — shared styles */
                .wtm-page { max-width: 1200px; margin: 20px 20px 40px 2px; }
                .wtm-page > h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; font-size: 23px; }
                .wtm-page > h1 .dashicons { color: #6C5CE7; }
                .wtm-page-subtitle { color: #6b7280; font-size: 13px; margin: 0 0 20px 40px; }

                /* Cards & grids */
                .wtm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 16px 0; }
                .wtm-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
                .wtm-card h2, .wtm-card h3 { margin-top: 0; display: flex; align-items: center; gap: 6px; }
                .wtm-card h2 .dashicons, .wtm-card h3 .dashicons { color: #6C5CE7; }
                .wtm-card .wtm-card-stat { font-size: 36px; font-weight: 600; color: #6C5CE7; line-height: 1.1; margin: 8px 0; }
                .wtm-card .wtm-card-label { color: #6b7280; font-size: 13px; }

                /* Notices */
                .wtm-notice { background: #fff; border-left: 4px solid #6C5CE7; padding: 12px 16px; margin: 16px 0; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
                .wtm-notice.is-success { border-left-color: #00B894; }
                .wtm-notice.is-error   { border-left-color: #FF7675; }
                .wtm-notice.is-warning { border-left-color: #FDCB6E; }
                .wtm-notice p { margin: 0; }

                /* Buttons */
                .wtm-btn { display: inline-flex; align-items: center; gap: 4px; background: #6C5CE7; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; transition: background 0.15s; }
                .wtm-btn:hover { background: #5A4BD1; color: #fff; }
                .wtm-btn.is-secondary { background: #f3f4f6; color: #374151; }
                .wtm-btn.is-secondary:hover { background: #e5e7eb; color: #374151; }
                .wtm-btn.is-danger { background: #FF7675; }
                .wtm-btn.is-danger:hover { background: #E66463; }
                .wtm-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }

                /* Tables */
                .wtm-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
                .wtm-table thead { background: #f9fafb; }
                .wtm-table th { text-align: left; padding: 12px 16px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
                .wtm-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
                .wtm-table tr:last-child td { border-bottom: none; }
                .wtm-table tr:hover td { background: #fafbfc; }

                /* Badges */
                .wtm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }
                .wtm-badge.is-horizontal { background: #ede9fe; color: #5b21b6; }
                .wtm-badge.is-vertical   { background: #dbeafe; color: #1e40af; }
                .wtm-badge.is-offcanvas  { background: #fef3c7; color: #92400e; }
                .wtm-badge.is-footer     { background: #fce7f3; color: #9d174d; }
                .wtm-badge.is-active     { background: #d1fae5; color: #065f46; }
                .wtm-badge.is-inactive   { background: #fee2e2; color: #991b1b; }

                /* Tabs (settings page) */
                .wtm-tabs { display: flex; gap: 2px; border-bottom: 1px solid #e5e7eb; margin-bottom: 20px; flex-wrap: wrap; }
                .wtm-tabs a { padding: 10px 16px; text-decoration: none; color: #6b7280; font-size: 13px; font-weight: 500; border-bottom: 2px solid transparent; transition: all 0.15s; }
                .wtm-tabs a:hover { color: #6C5CE7; }
                .wtm-tabs a.is-active { color: #6C5CE7; border-bottom-color: #6C5CE7; }

                /* Form fields */
                .wtm-form-row { margin-bottom: 16px; }
                .wtm-form-row label { display: block; font-weight: 600; margin-bottom: 4px; color: #374151; }
                .wtm-form-row .description { color: #6b7280; font-size: 12px; margin-top: 2px; }
                .wtm-form-row input[type="text"],
                .wtm-form-row input[type="number"],
                .wtm-form-row input[type="color"],
                .wtm-form-row select,
                .wtm-form-row textarea { width: 100%; max-width: 480px; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
                .wtm-form-row input[type="color"] { padding: 2px; height: 36px; }
                .wtm-form-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-bottom: 16px; }
                .wtm-form-section h3 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #f3f4f6; }

                /* Empty state */
                .wtm-empty-state { text-align: center; padding: 60px 20px; background: #fff; border: 1px dashed #d1d5db; border-radius: 8px; }
                .wtm-empty-state .dashicons { font-size: 48px; width: 48px; height: 48px; color: #d1d5db; margin-bottom: 16px; }
                .wtm-empty-state h3 { color: #374151; margin: 0 0 8px; }
                .wtm-empty-state p { color: #6b7280; margin: 0 0 20px; }
CSS;
        }

        /**
         * Build the URL to trigger a WTM action.
         *
         * @param string $action  Action name.
         * @param array  $args    Additional query args.
         * @return string
         */
        public static function action_url( $action, $args = array() ) {
                $args['wtm_action'] = $action;
                $args['_wpnonce']   = wp_create_nonce( 'wtm_action_' . $action );
                return add_query_arg( $args, admin_url( 'admin.php' ) );
        }
}
