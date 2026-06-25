<?php
/**
 * Main bootstrap class for Woo Total Menu.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Bootstrap
 *
 * Orchestrates plugin initialization, registers hooks and instantiates
 * the various subsystems (Admin, API, Frontend, Core).
 */
class Bootstrap {

        /**
         * Container of instantiated subsystems.
         *
         * @var array<string,object>
         */
        private $services = array();

        /**
         * Whether the plugin has been booted.
         *
         * @var bool
         */
        private $booted = false;

        /**
         * Boot the plugin.
         *
         * @return void
         */
        public function boot() {
                if ( $this->booted ) {
                        return;
                }
                $this->booted = true;

                // Load translations.
                load_plugin_textdomain( 'woo-total-menu', false, dirname( WTM_PLUGIN_BASENAME ) . '/languages' );

                // Check WooCommerce dependency.
                if ( ! $this->check_dependencies() ) {
                        return;
                }

                // Initialize subsystems.
                $this->init_services();

                // Register hooks.
                $this->register_hooks();

                /**
                 * Fires once Woo Total Menu has fully booted.
                 *
                 * @since 1.0.0
                 */
                do_action( 'wtm_loaded' );
        }

        /**
         * Check that required dependencies (WooCommerce) are active.
         *
         * @return bool True if dependencies are met.
         */
        private function check_dependencies() {
                if ( ! class_exists( 'WooCommerce' ) ) {
                        add_action( 'admin_notices', array( $this, 'notice_woocommerce_required' ) );
                        return false;
                }
                return true;
        }

        /**
         * Admin notice when WooCommerce is not active.
         *
         * @return void
         */
        public function notice_woocommerce_required() {
                echo '<div class="notice notice-error"><p>';
                esc_html_e( 'Woo Total Menu requires WooCommerce to be active. Please install and activate WooCommerce first.', 'woo-total-menu' );
                echo '</p></div>';
        }

        /**
         * Initialize plugin services.
         *
         * Each service is registered in the container for later use.
         *
         * @return void
         */
        private function init_services() {
                // Core services.
                $this->services['cache']       = new \WooTotalMenu\Core\Cache_Manager();
                $this->services['permissions'] = new \WooTotalMenu\Core\Permissions();
                $this->services['cpt']         = new \WooTotalMenu\Core\CPT_Manager();

                // API REST (loaded on every request including AJAX).
                $this->services['rest_menus']  = new \WooTotalMenu\Api\Menu_Controller();

                // v1.1.5 — Revisions REST controller (/wtm/v1/menus/{id}/revisions…).
                $this->services['rest_revisions'] = new \WooTotalMenu\Api\Revisions_Controller();

                // v1.3.0 — Frontend AJAX controller (search-suggest, mini-cart-contents,
                // newsletter-subscribe). Public endpoints + admin-ajax handler.
                $this->services['rest_frontend'] = new \WooTotalMenu\Api\Frontend_Controller();

                // v1.1.4 — Preview iframe controller (REST /wtm/v1/preview-frame).
                // Loaded on every request so the iframe document is reachable
                // via REST even when is_admin() is false (the iframe loads
                // through the REST URL, not the admin dashboard).
                $this->services['preview']     = new \WooTotalMenu\Frontend\Preview_Controller();

                // v1.2.0 — Frontend rendering (spec §2.4, §5).
                //   - Dynamic_CSS: per-site stylesheet cached to uploads/wtm-cache/.
                //   - Menu_Renderer: walks JSON config → HTML.
                //   - Location_Interceptor: replaces theme wp_nav_menu() calls.
                //   - Shortcode: [wtm_menu id="…"] for arbitrary embeds.
                //   - Assets_Loader: conditional CSS/JS enqueue.
                // Instantiated on every request so that REST + AJAX + wp-admin all
                // share the same hooks (preview iframe renders via REST too).
                $this->services['dynamic_css']      = new \WooTotalMenu\Frontend\Dynamic_CSS();
                $this->services['menu_renderer']    = new \WooTotalMenu\Frontend\Menu_Renderer();
                $this->services['location_interceptor'] = new \WooTotalMenu\Frontend\Location_Interceptor( $this->services['menu_renderer'] );
                $this->services['shortcode']        = new \WooTotalMenu\Frontend\Shortcode( $this->services['menu_renderer'] );

                // Admin (only in wp-admin context).
                if ( is_admin() ) {
                        $this->services['admin_menu']  = new \WooTotalMenu\Admin\Admin_Menu();
                }

                // Meta_Boxes is instantiated on every request (not just is_admin)
                // because it is responsible for:
                //   - register_meta() — must run on every request so the REST API
                //     knows the meta schema.
                //   - _wp_post_revision_meta_keys filter — must be registered on
                //     every request so revisions created via REST have the WTM
                //     meta copied over (v1.1.5 — spec §7.6).
                //   - wp_restore_post_revision action — must fire on every request
                //     so REST restore properly restores the WTM meta.
                // The admin-only hooks (add_meta_boxes, save_post_*) are simply
                // never triggered outside wp-admin, so they are harmless.
                $this->services['meta_boxes']  = new \WooTotalMenu\Admin\Meta_Boxes();

                // Frontend assets loader (replaces the v1.0.0 stub).
                // Instantiated on every request so it can listen for the
                // `wtm_rendered_location` action in REST/AJAX contexts too.
                if ( ! is_admin() || wp_doing_ajax() ) {
                        $this->services['assets_loader'] = new \WooTotalMenu\Frontend\Assets_Loader( $this->services['dynamic_css'] );
                }
        }

        /**
         * Register global hooks.
         *
         * @return void
         */
        private function register_hooks() {
                // Init action — used by subsystems to register their own hooks.
                add_action( 'init', array( $this, 'on_init' ), 20 );

                // v1.2.0 — Purge the dynamic CSS cache when a menu or settings change.
                add_action( 'save_post_wtm_menu', array( $this, 'purge_dynamic_css' ) );
                add_action( 'wtm_settings_saved', array( $this, 'purge_dynamic_css' ) );
                // Also purge on revision restore (spec §7.6).
                add_action( 'wp_restore_post_revision', array( $this, 'purge_dynamic_css' ) );
        }

        /**
         * Purge the frontend dynamic CSS cache.
         *
         * Called on save_post_wtm_menu, wtm_settings_saved, wp_restore_post_revision.
         *
         * @return void
         */
        public function purge_dynamic_css() {
                if ( isset( $this->services['dynamic_css'] ) ) {
                        $this->services['dynamic_css']->purge();
                }
        }

        /**
         * WordPress init hook.
         *
         * @return void
         */
        public function on_init() {
                // Re-register capabilities on every init (idempotent, picks up new roles).
                if ( isset( $this->services['permissions'] ) ) {
                        $this->services['permissions']->register_caps();
                }

                /**
                 * Fires on WordPress init after Woo Total Menu is loaded.
                 *
                 * @since 1.0.0
                 */
                do_action( 'wtm_init' );
        }

        /**
         * Get a service by name.
         *
         * @param string $name Service identifier.
         * @return object|null
         */
        public function get( $name ) {
                return $this->services[ $name ] ?? null;
        }

        /**
         * Plugin activation handler.
         *
         * Creates default options, marks DB version, registers capabilities.
         *
         * @return void
         */
        public static function on_activate() {
                // Default settings.
                if ( false === get_option( WTM_OPTION_SETTINGS ) ) {
                        add_option( WTM_OPTION_SETTINGS, self::default_settings() );
                }
                // Mark DB version.
                update_option( WTM_OPTION_DB_VERSION, WTM_DB_VERSION );

                // Register custom capabilities.
                $permissions = new \WooTotalMenu\Core\Permissions();
                $permissions->register_caps();

                // Flush rewrite rules after activation.
                flush_rewrite_rules( false );
        }

        /**
         * Plugin deactivation handler.
         *
         * @return void
         */
        public static function on_deactivate() {
                flush_rewrite_rules( false );
        }

        /**
         * Default global settings.
         *
         * @return array
         */
        public static function default_settings() {
                return array(
                        'general' => array(
                                'enabled'         => true,
                                'default_location' => 'primary',
                        ),
                        'styles' => array(
                                'primary_color'   => '#6C5CE7',
                                'background'      => '#FFFFFF',
                                'text_color'      => '#2D3436',
                                'success_color'   => '#00B894',
                                'error_color'     => '#FF7675',
                                'border_radius'   => 6,
                        ),
                        'typography' => array(
                                'font_family'    => 'Inter',
                                'base_size'      => 14,
                                'heading_size'   => 18,
                        ),
                        'responsive' => array(
                                'mobile_breakpoint'  => 768,
                                'tablet_breakpoint'  => 1024,
                                'mobile_behavior'    => 'offcanvas',
                                'hamburger_position' => 'right',
                        ),
                        'performance' => array(
                                'cache_enabled'   => true,
                                'lazy_load_widgets' => true,
                                'minify_css'      => true,
                        ),
                        'analytics' => array(
                                'enabled'      => false,
                                'track_logged' => false,
                        ),
                        'permissions' => array(
                                'admin_default' => 'administrator',
                                'editor_default' => 'editor',
                        ),
                        'version' => WTM_VERSION,
                        'db_version' => WTM_DB_VERSION,
                );
        }
}
