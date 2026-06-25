<?php
/**
 * Header & Footer injector.
 *
 * Hooks into wp_body_open (header) and wp_footer (footer) to inject the
 * rendered HTML for the layouts attached to the active wtm_menu.
 *
 * Spec reference: §3.6 (Header), §3.7 (Footer), §4.6.5 (Header/Footer Builder),
 * §5.7 (footer frontend), §5.8 (header frontend).
 *
 * Theme compatibility:
 *   - Header is injected via the `wp_body_open` action (WP 5.2+). Themes that
 *     don't fire `wp_body_open` won't get the header — this is documented in
 *     the spec (§2.8.1). A filter `wtm_header_inject_hook` lets developers
 *     override the hook (e.g. 'storefront_header' for Storefront).
 *   - Footer is injected via the `wp_footer` action, fired by all themes.
 *
 * Settings (under _wtm_settings → header_footer):
 *   - enabled          (bool)  Master toggle. Default false (opt-in).
 *   - header_menu_id   (int)   Post ID of the wtm_menu carrying the header config.
 *   - footer_menu_id   (int)   Post ID of the wtm_menu carrying the footer config.
 *   - hide_theme_header(bool)  Attempt to hide the theme's own header via CSS.
 *   - hide_theme_footer(bool)  Attempt to hide the theme's own footer via CSS.
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Header_Footer_Injector
 *
 * Listens to wp_body_open / wp_footer and outputs the rendered header/footer
 * HTML when the user has enabled the feature in settings.
 */
class Header_Footer_Injector {

        /**
         * Header_Footer_Renderer instance (injected).
         *
         * @var Header_Footer_Renderer
         */
        private $renderer;

        /**
         * Condition_Evaluator instance (lazy).
         *
         * @var \WooTotalMenu\Core\Condition_Evaluator|null
         */
        private $conditions = null;

        /**
         * Cached settings (computed once per request).
         *
         * @var array|null
         */
        private $settings = null;

        /**
         * Constructor.
         *
         * @param Header_Footer_Renderer $renderer Layout renderer.
         */
        public function __construct( Header_Footer_Renderer $renderer ) {
                $this->renderer   = $renderer;
                $this->conditions = new \WooTotalMenu\Core\Condition_Evaluator();

                // Header injection — uses wp_body_open by default (WP 5.2+).
                // Themes that don't fire wp_body_open need to use the filter below.
                add_action( 'wp_body_open', array( $this, 'inject_header' ), 10 );

                // Footer injection — wp_footer is fired by all themes.
                add_action( 'wp_footer', array( $this, 'inject_footer' ), 20 );

                // Optional: hide theme header/footer via CSS (priority late, after theme styles).
                add_action( 'wp_enqueue_scripts', array( $this, 'maybe_inject_hide_css' ), 99 );
        }

        /**
         * Get the header/footer settings section.
         *
         * Stored under the global WTM_OPTION_SETTINGS option, in the
         * 'header_footer' subsection. Merged with defaults.
         *
         * @return array
         */
        private function get_settings() {
                if ( null !== $this->settings ) {
                        return $this->settings;
                }

                $option = get_option( WTM_OPTION_SETTINGS );
                $hf     = is_array( $option ) ? ( $option['header_footer'] ?? array() ) : array();

                $defaults = array(
                        'enabled'           => false,
                        'header_menu_id'    => 0,
                        'footer_menu_id'    => 0,
                        'hide_theme_header' => false,
                        'hide_theme_footer' => false,
                );

                $this->settings = wp_parse_args( $hf, $defaults );
                return $this->settings;
        }

        /**
         * Inject the header HTML.
         *
         * Hooked into wp_body_open by default. Themes that don't fire
         * wp_body_open can use the `wtm_header_inject_hook` filter to use a
         * different hook (e.g. 'storefront_before_header').
         *
         * @return void Outputs HTML.
         */
        public function inject_header() {
                $settings = $this->get_settings();
                if ( empty( $settings['enabled'] ) ) {
                        return;
                }
                if ( empty( $settings['header_menu_id'] ) ) {
                        return;
                }

                // Skip in admin, REST, AJAX, feed, etc.
                if ( $this->is_skippable_request() ) {
                        return;
                }

                /**
                 * Filter the header menu ID at runtime.
                 *
                 * Useful for conditional headers (e.g. different header on shop pages).
                 *
                 * @since 1.4.0
                 *
                 * @param int $menu_id Header menu post ID.
                 */
                $menu_id = (int) apply_filters( 'wtm_header_menu_id', (int) $settings['header_menu_id'] );
                if ( $menu_id <= 0 ) {
                        return;
                }

                // v1.7.0 — Conditional header: skip if conditions don't match.
                if ( ! $this->conditions->should_render( $menu_id ) ) {
                        return;
                }

                $html = $this->renderer->render_header_by_id( $menu_id );
                if ( '' === $html ) {
                        return;
                }

                /**
                 * Fires before the WTM header is output.
                 *
                 * @since 1.4.0
                 *
                 * @param int $menu_id Source menu post ID.
                 */
                do_action( 'wtm_before_header', $menu_id );

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped in renderer.
                echo $html;

                /**
                 * Fires after the WTM header is output.
                 *
                 * @since 1.4.0
                 *
                 * @param int $menu_id Source menu post ID.
                 */
                do_action( 'wtm_after_header', $menu_id );
        }

        /**
         * Inject the footer HTML.
         *
         * Hooked into wp_footer.
         *
         * @return void Outputs HTML.
         */
        public function inject_footer() {
                $settings = $this->get_settings();
                if ( empty( $settings['enabled'] ) ) {
                        return;
                }
                if ( empty( $settings['footer_menu_id'] ) ) {
                        return;
                }

                if ( $this->is_skippable_request() ) {
                        return;
                }

                /**
                 * Filter the footer menu ID at runtime.
                 *
                 * @since 1.4.0
                 *
                 * @param int $menu_id Footer menu post ID.
                 */
                $menu_id = (int) apply_filters( 'wtm_footer_menu_id', (int) $settings['footer_menu_id'] );
                if ( $menu_id <= 0 ) {
                        return;
                }

                // v1.7.0 — Conditional footer: skip if conditions don't match.
                if ( ! $this->conditions->should_render( $menu_id ) ) {
                        return;
                }

                $html = $this->renderer->render_footer_by_id( $menu_id );
                if ( '' === $html ) {
                        return;
                }

                /**
                 * Fires before the WTM footer is output.
                 *
                 * @since 1.4.0
                 *
                 * @param int $menu_id Source menu post ID.
                 */
                do_action( 'wtm_before_footer', $menu_id );

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped in renderer.
                echo $html;

                /**
                 * Fires after the WTM footer is output.
                 *
                 * @since 1.4.0
                 *
                 * @param int $menu_id Source menu post ID.
                 */
                do_action( 'wtm_after_footer', $menu_id );
        }

        /**
         * Determine whether the current request should skip header/footer injection.
         *
         * Skip in: admin, AJAX, REST, feed, embed, preview.
         *
         * @return bool
         */
        private function is_skippable_request() {
                if ( is_admin() ) {
                        return true;
                }
                if ( wp_doing_ajax() ) {
                        return true;
                }
                if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                        return true;
                }
                if ( is_feed() ) {
                        return true;
                }
                if ( is_embed() ) {
                        return true;
                }
                // Skip in the Customizer preview (the customizer re-renders the page
                // and a duplicated header/footer would break the layout).
                if ( is_customize_preview() ) {
                        return true;
                }
                return false;
        }

        /**
         * Inject inline CSS to hide the theme's own header/footer when the
         * corresponding setting is on.
         *
         * Uses generic selectors — themes may need to add their own CSS to hide
         * custom markup. The selectors cover the most common patterns (Twenty
         * family, Storefront, Underscores).
         *
         * @return void
         */
        public function maybe_inject_hide_css() {
                $settings = $this->get_settings();
                if ( empty( $settings['enabled'] ) ) {
                        return;
                }

                $css = '';
                if ( ! empty( $settings['hide_theme_header'] ) ) {
                        $css .= 'header.site-header, header#masthead, .site-header, #masthead { display: none !important; }';
                }
                if ( ! empty( $settings['hide_theme_footer'] ) ) {
                        $css .= 'footer.site-footer, footer#colophon, .site-footer, #colophon { display: none !important; }';
                }

                if ( '' === $css ) {
                        return;
                }

                wp_add_inline_style( 'wtm-frontend', $css );
        }
}
