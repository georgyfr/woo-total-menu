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

                // v1.4.0 — Header/Footer builder (spec §3.6, §3.7, §4.6.5, §5.7, §5.8).
                //   - Header_Footer_Renderer: walks layout config (rows → columns →
                //     modules) → HTML. Reuses Menu_Renderer for `menu` modules.
                //   - Header_Footer_Injector: hooks into wp_body_open / wp_footer to
                //     inject the rendered HTML. Enabled by setting
                //     wtm_global_settings → header_footer → enabled = true.
                $this->services['hf_renderer'] = new \WooTotalMenu\Frontend\Header_Footer_Renderer( $this->services['menu_renderer'] );
                $this->services['hf_injector'] = new \WooTotalMenu\Frontend\Header_Footer_Injector( $this->services['hf_renderer'] );

                // v1.5.0 — Templates library (spec §1.4.2 — bibliothèque de templates intégrés).
                //   - Template_Registry: catalogue statique (12 templates intégrés).
                //     Lazy-built puis filtré par `wtm_templates_catalog`. Pas besoin
                //     d'instanciation explicite (classe 100% statique) mais on garde
                //     une entrée dans le container pour symmetry / debug / tests.
                //   - Templates_Controller: 3 routes REST /wtm/v1/templates,
                //     /templates/{id}, /templates/{id}/apply. Le contrôleur instancie
                //     ses hooks lui-même via son constructeur.
                $this->services['rest_templates'] = new \WooTotalMenu\Api\Templates_Controller();

                // v1.6.0 — Roles Controller (5 routes REST /wtm/v1/roles).
                //   - Liste / détail / création / maj caps / suppression de rôles.
                //   - Gère aussi bien les rôles WordPress standards que les rôles
                //     personnalisés créés via le plugin (préfixe `wtm_`).
                $this->services['rest_roles'] = new \WooTotalMenu\Api\Roles_Controller();

                // v1.7.0 — Conditions Controller (4 routes REST /wtm/v1/menus/{id}/conditions…).
                //   - GET / PUT / DELETE pour lire, remplacer ou vider les conditions
                //     de visibilité attachées à un menu.
                //   - POST /conditions/test : évalue les conditions fournies contre la
                //     requête courante (preview dans le Builder).
                $this->services['rest_conditions'] = new \WooTotalMenu\Api\Conditions_Controller();

                // v1.7.0 — Analytics Controller (2 routes REST /wtm/v1/analytics…).
                //   - POST /analytics/track : endpoint public (nonce-gated) pour
                //     enregistrer un événement view/click/hover.
                //   - GET  /analytics/stats  : agrégats journaliers pour le dashboard.
                $this->services['rest_analytics'] = new \WooTotalMenu\Api\Analytics_Controller();

                // v1.7.1 — WP Native Menus Controller (1 route REST /wtm/v1/wp-menus).
                //   - GET /wp-menus : liste les nav_menus WordPress natifs (taxonomy=nav_menu)
                //     créés via /wp-admin/nav-menus.php, pour le dropdown du module `menu`
                //     du Header/Footer Builder.
                $this->services['rest_wp_menus'] = new \WooTotalMenu\Api\WP_Menus_Controller();

                // v1.6.0 — Gutenberg blocks (3 blocs server-render : menu, header, footer).
                //   - register_block_type() appelé sur `init` par Gutenberg_Blocks.
                //   - Editor JS minimaliste (placeholder + sidebar controls) — le rendu
                //     est fait côté serveur via Menu_Renderer / Header_Footer_Renderer.
                $this->services['gutenberg_blocks'] = new \WooTotalMenu\Integration\Gutenberg_Blocks(
                        $this->services['menu_renderer'],
                        $this->services['hf_renderer']
                );

                // v1.6.0 — Page builders integrations (Elementor / Bricks / Oxygen).
                //   - Elementor : widget custom "Woo Total Menu" (si \Elementor\Widget_Base exists).
                //   - Bricks    : élément custom "wtm-menu" (si BRICKS_VERSION defined).
                //   - Oxygen    : 3 shortcodes additionnels [wtm_header], [wtm_footer],
                //                 [wtm_oxygen_menu] + helper wtm_oxygen_render_menu().
                // Tous sont lazy-initialized : si le page builder n'est pas actif,
                // l'intégration ne s'instancie pas, économisant des hooks inutiles.
                if ( \WooTotalMenu\Integration\Elementor_Integration::is_active() ) {
                        $this->services['elementor_integration'] = new \WooTotalMenu\Integration\Elementor_Integration(
                                $this->services['menu_renderer']
                        );
                }
                if ( \WooTotalMenu\Integration\Bricks_Integration::is_active() ) {
                        $this->services['bricks_integration'] = new \WooTotalMenu\Integration\Bricks_Integration(
                                $this->services['menu_renderer']
                        );
                }
                // Oxygen est toujours "activé" car les shortcodes additionnels sont
                // utiles même sans Oxygen (peuvent être appelés depuis n'importe quel
                // shortcode-aware context).
                $this->services['oxygen_integration'] = new \WooTotalMenu\Integration\Oxygen_Integration(
                        $this->services['menu_renderer'],
                        $this->services['hf_renderer']
                );

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

                // v1.6.0 — Multisite support : initialise les nouveaux blogs créés
                // après activation réseau. Le hook n'est déclenché qu'en multisite.
                add_action( 'wpmu_new_blog', array( '\\WooTotalMenu\\Core\\Multisite_Manager', 'on_new_blog' ) );
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
         * In multisite network-activation, sets up every blog via Multisite_Manager.
         *
         * @param bool $network_wide True si activation réseau (multisite).
         * @return void
         */
        public static function on_activate( $network_wide = false ) {
                // v1.6.0 — Multisite : si activation réseau, initialiser tous les blogs.
                if ( $network_wide && is_multisite() ) {
                        \WooTotalMenu\Core\Multisite_Manager::on_network_activate( $network_wide );
                        return;
                }

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

                /**
                 * Fires after Woo Total Menu is activated on a single site.
                 *
                 * @since 1.6.0
                 */
                do_action( 'wtm_activated' );
        }

        /**
         * Plugin deactivation handler.
         *
         * En multisite réseau, flush les rewrite rules de tous les blogs.
         *
         * @param bool $network_wide True si désactivation réseau.
         * @return void
         */
        public static function on_deactivate( $network_wide = false ) {
                if ( $network_wide && is_multisite() ) {
                        \WooTotalMenu\Core\Multisite_Manager::for_each_blog(
                                static function () {
                                        flush_rewrite_rules( false );
                                }
                        );
                        return;
                }
                flush_rewrite_rules( false );

                /**
                 * Fires after Woo Total Menu is deactivated.
                 *
                 * @since 1.6.0
                 */
                do_action( 'wtm_deactivated' );
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
                        // v1.4.0 — Header/Footer builder settings (spec §3.6, §3.7).
                        'header_footer' => array(
                                'enabled'           => false,
                                'header_menu_id'    => 0,
                                'footer_menu_id'    => 0,
                                'hide_theme_header' => false,
                                'hide_theme_footer' => false,
                        ),
                        'version' => WTM_VERSION,
                        'db_version' => WTM_DB_VERSION,
                );
        }
}
