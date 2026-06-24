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

                // Admin (only in wp-admin context).
                if ( is_admin() ) {
                        $this->services['admin_menu']  = new \WooTotalMenu\Admin\Admin_Menu();
                        $this->services['meta_boxes']  = new \WooTotalMenu\Admin\Meta_Boxes();
                }

                // Frontend rendering.
                if ( ! is_admin() || wp_doing_ajax() ) {
                        $this->services['assets_loader'] = new \WooTotalMenu\Frontend\Assets_Loader();
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
