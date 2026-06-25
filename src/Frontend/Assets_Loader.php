<?php
/**
 * Frontend assets loader.
 *
 * Spec §2.6.1: "Le plugin n'enqueue ses CSS/JS que lorsqu'un menu wtm_menu
 * est actif sur la page. Pour le frontend, un fichier CSS minifié (~15 Ko)
 * est chargé. Le JavaScript frontend est optionnel et très léger (~5 Ko,
 * vanilla JS, pas de jQuery)."
 *
 * @package WooTotalMenu
 * @since 1.2.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Assets_Loader
 *
 * Conditionally enqueues:
 *   - Dynamic CSS (global settings + per-menu)
 *   - Base frontend CSS (wtm-frontend.css)
 *   - Frontend JS (wtm-frontend.js) — only when a menu is rendered
 *
 * Listens to `wtm_rendered_location` to know whether a menu was rendered.
 */
class Assets_Loader {

        /**
         * Dynamic_CSS instance (injected).
         *
         * @var Dynamic_CSS
         */
        private $dynamic_css;

        /**
         * Whether at least one WTM menu was rendered on this request.
         *
         * @var bool
         */
        private $has_rendered_menu = false;

        /**
         * Whether a header/footer layout is enabled (forces asset loading).
         *
         * @var bool|null Cached lookup result.
         */
        private $hf_enabled = null;

        /**
         * Constructor.
         *
         * @param Dynamic_CSS $dynamic_css Dynamic CSS generator.
         */
        public function __construct( Dynamic_CSS $dynamic_css ) {
                $this->dynamic_css = $dynamic_css;

                // Listen for menus being rendered (via shortcode or location interceptor).
                add_action( 'wtm_rendered_location', array( $this, 'mark_rendered' ), 10, 2 );

                // v1.4.0 — also listen for header/footer module events (the `menu`
                // module inside a header/footer fires wtm_rendered_location too, but
                // we add an explicit hook for non-menu modules so assets load).
                add_action( 'wtm_before_header', array( $this, 'mark_hf_rendered' ) );
                add_action( 'wtm_before_footer', array( $this, 'mark_hf_rendered' ) );

                // Enqueue assets — late, after we know whether a menu was rendered.
                add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 100 );
        }

        /**
         * Mark that a header/footer layout is being rendered.
         *
         * Hooked into wtm_before_header / wtm_before_footer.
         *
         * @return void
         */
        public function mark_hf_rendered() {
                $this->has_rendered_menu = true;
        }

        /**
         * Mark that a menu was rendered on this request.
         *
         * @param int    $menu_id  Menu post ID.
         * @param string $location Location slug.
         * @return void
         */
        public function mark_rendered( $menu_id, $location ) {
                $this->has_rendered_menu = true;
        }

        /**
         * Enqueue frontend assets if a WTM menu is active on the page.
         *
         * @return void
         */
        public function maybe_enqueue() {
                /**
                 * Allow forcing asset loading even when no menu was rendered
                 * (useful for shortcodes in AJAX-loaded content).
                 *
                 * @since 1.2.0
                 *
                 * @param bool $force Whether to force enqueue.
                 */
                $force = (bool) apply_filters( 'wtm_force_enqueue_assets', false );

                // v1.4.0 — also load when the Header/Footer injector is enabled. The
                // actual injection happens on wp_body_open / wp_footer, which fire
                // AFTER wp_enqueue_scripts, so we peek at the settings here.
                $hf_active = $this->is_header_footer_active();

                if ( ! $this->has_rendered_menu && ! $force && ! $hf_active ) {
                        return;
                }

                // 1. Dynamic CSS (per-site, regenerated on settings/menu save).
                $dynamic_url = $this->dynamic_css->get_url();
                if ( $dynamic_url ) {
                        wp_enqueue_style(
                                'wtm-dynamic',
                                $dynamic_url,
                                array(),
                                null // Cache-busting hash already in URL.
                        );
                }

                // 2. Base frontend CSS.
                wp_enqueue_style(
                        'wtm-frontend',
                        WTM_PLUGIN_URL . 'assets/front/wtm-frontend.css',
                        array( 'wtm-dynamic' ),
                        WTM_VERSION
                );

                // 3. Frontend JS (vanilla, no jQuery — spec §2.6.1).
                wp_enqueue_script(
                        'wtm-frontend',
                        WTM_PLUGIN_URL . 'assets/front/wtm-frontend.js',
                        array(),
                        WTM_VERSION,
                        true // In footer.
                );

                // Localize script with breakpoint + i18n strings.
                $settings    = get_option( WTM_OPTION_SETTINGS );
                $responsive  = is_array( $settings ) ? ( $settings['responsive'] ?? array() ) : array();
                $breakpoint  = (int) ( $responsive['mobile_breakpoint'] ?? 768 );
                $mobile_bhvr = $responsive['mobile_behavior'] ?? 'offcanvas';

                wp_localize_script(
                        'wtm-frontend',
                        'wtmFrontend',
                        array(
                                'breakpoint'       => $breakpoint,
                                'mobileBehavior'   => $mobile_bhvr,
                                'i18n'             => array(
                                        'openMenu'     => __( 'Ouvrir le menu', 'woo-total-menu' ),
                                        'closeMenu'    => __( 'Fermer le menu', 'woo-total-menu' ),
                                        'openSub'      => __( 'Ouvrir le sous-menu', 'woo-total-menu' ),
                                        'closeSub'     => __( 'Fermer le sous-menu', 'woo-total-menu' ),
                                        'openCart'     => __( 'Ouvrir le panier', 'woo-total-menu' ),
                                        'closeCart'    => __( 'Fermer le panier', 'woo-total-menu' ),
                                        'cartEmpty'    => __( 'Votre panier est vide.', 'woo-total-menu' ),
                                        'viewCart'     => __( 'Voir le panier', 'woo-total-menu' ),
                                        'checkout'     => __( 'Commander', 'woo-total-menu' ),
                                        'noResults'    => __( 'Aucun produit trouvé.', 'woo-total-menu' ),
                                        'searching'    => __( 'Recherche…', 'woo-total-menu' ),
                                        'subscribing'  => __( 'Inscription…', 'woo-total-menu' ),
                                        'invalidEmail' => __( 'Adresse email invalide.', 'woo-total-menu' ),
                                ),
                                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                                'restUrl'          => esc_url_raw( rest_url( WTM_REST_NAMESPACE ) ),
                                'restNonce'        => wp_create_nonce( 'wp_rest' ),
                                'newsletterNonce'  => wp_create_nonce( 'wtm_newsletter' ),
                                'wooCartFragments' => class_exists( 'WooCommerce' ) ? 'yes' : 'no',
                        )
                );
        }

        /**
         * Check whether the Header/Footer injector is enabled and a header or
         * footer menu is configured.
         *
         * Used to decide whether to enqueue frontend assets even before the
         * injector actually fires (wp_body_open / wp_footer happen after
         * wp_enqueue_scripts).
         *
         * @since 1.4.0
         *
         * @return bool
         */
        private function is_header_footer_active() {
                if ( null !== $this->hf_enabled ) {
                        return $this->hf_enabled;
                }
                $option = get_option( WTM_OPTION_SETTINGS );
                $hf     = is_array( $option ) ? ( $option['header_footer'] ?? array() ) : array();
                $enabled         = ! empty( $hf['enabled'] );
                $header_menu_id  = ! empty( $hf['header_menu_id'] );
                $footer_menu_id  = ! empty( $hf['footer_menu_id'] );
                $this->hf_enabled = $enabled && ( $header_menu_id || $footer_menu_id );
                return $this->hf_enabled;
        }
}
