<?php
/**
 * Oxygen + Shortcode Integration — Woo Total Menu.
 *
 * Oxygen Builder n'a pas d'API officielle d'enregistrement d'éléments custom
 * aussi simple qu'Elementor. La méthode recommandée est :
 *
 *   1. Enregistrer un shortcode universel `[wtm_oxygen_menu id="42"]` ;
 *   2. Fournir une fonction helper `wtm_oxygen_render_menu()` ;
 *   3. Les utilisateurs Oxygen insèrent un bloc "Shortcode" dans leur layout
 *      et collent `[wtm_oxygen_menu id="42"]`.
 *
 * Cette intégration fournit aussi un composant PHP pour le template builder
 * Oxygen via `oxygen_add_plus_themes` (filter).
 *
 * Shortcodes disponibles :
 *
 *   - `[wtm_menu id="42"]`                 — alias de Shortcode (v1.2.0+).
 *   - `[wtm_header id="42"]`               — affiche un header.
 *   - `[wtm_footer id="42"]`               — affiche un footer.
 *   - `[wtm_oxygen_menu id="42"]`          — alias pour compat Oxygen.
 *
 * @package WooTotalMenu\Integration
 * @since 1.6.0
 */

namespace WooTotalMenu\Integration;

use WooTotalMenu\Frontend\Menu_Renderer;
use WooTotalMenu\Frontend\Header_Footer_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Oxygen_Integration.
 *
 * Instanciée par Bootstrap. Enregistre les shortcodes additionnels
 * `[wtm_header]`, `[wtm_footer]` et `[wtm_oxygen_menu]`.
 */
final class Oxygen_Integration {

        /**
         * Menu_Renderer instance.
         *
         * @var Menu_Renderer
         */
        private $menu_renderer;

        /**
         * Header_Footer_Renderer instance.
         *
         * @var Header_Footer_Renderer
         */
        private $hf_renderer;

        /**
         * Constructeur.
         *
         * @param Menu_Renderer           $menu_renderer Pour le rendu des menus.
         * @param Header_Footer_Renderer  $hf_renderer   Pour le rendu des headers/footers.
         */
        public function __construct( Menu_Renderer $menu_renderer, Header_Footer_Renderer $hf_renderer ) {
                $this->menu_renderer = $menu_renderer;
                $this->hf_renderer   = $hf_renderer;

                // Shortcodes additionnels (header/footer + alias Oxygen).
                add_shortcode( 'wtm_header', array( $this, 'render_header_shortcode' ) );
                add_shortcode( 'wtm_footer', array( $this, 'render_footer_shortcode' ) );
                add_shortcode( 'wtm_oxygen_menu', array( $this, 'render_oxygen_menu_shortcode' ) );

                // Helper public pour Oxygen (peut être appelé depuis un template PHP).
                if ( ! function_exists( 'wtm_oxygen_render_menu' ) ) {
                        /**
                         * Render a WTM menu by ID. Helper for Oxygen templates.
                         *
                         * @since 1.6.0
                         *
                         * @param int $menu_id Menu post ID.
                         * @return string HTML.
                         */
                        function wtm_oxygen_render_menu( $menu_id ) {
                                global $wtm_oxygen_integration;
                                if ( ! $wtm_oxygen_integration instanceof Oxygen_Integration ) {
                                        return '';
                                }
                                return $wtm_oxygen_integration->menu_renderer->render_by_id( (int) $menu_id, '' );
                        }
                }

                // Exposer l'instance globalement pour le helper.
                global $wtm_oxygen_integration;
                $wtm_oxygen_integration = $this;
        }

        /**
         * Détecte si Oxygen est actif.
         *
         * @return bool
         */
        public static function is_active() {
                return defined( 'CT_VERSION' ) || class_exists( 'OxyElite' ) || defined( 'OXYGEN_VERSION' );
        }

        /**
         * Shortcode `[wtm_header id="42"]` — affiche un header.
         *
         * @param array $atts Attributs.
         * @return string HTML.
         */
        public function render_header_shortcode( $atts ) {
                $atts = shortcode_atts(
                        array(
                                'id' => 0,
                        ),
                        $atts,
                        'wtm_header'
                );

                $menu_id = (int) $atts['id'];
                if ( $menu_id <= 0 ) {
                        return '';
                }

                $html = $this->hf_renderer->render_header_by_id( $menu_id );
                if ( '' === $html ) {
                        return '';
                }

                return sprintf( '<div class="wtm-header-shortcode">%s</div>', $html );
        }

        /**
         * Shortcode `[wtm_footer id="42"]` — affiche un footer.
         *
         * @param array $atts Attributs.
         * @return string HTML.
         */
        public function render_footer_shortcode( $atts ) {
                $atts = shortcode_atts(
                        array(
                                'id' => 0,
                        ),
                        $atts,
                        'wtm_footer'
                );

                $menu_id = (int) $atts['id'];
                if ( $menu_id <= 0 ) {
                        return '';
                }

                $html = $this->hf_renderer->render_footer_by_id( $menu_id );
                if ( '' === $html ) {
                        return '';
                }

                return sprintf( '<div class="wtm-footer-shortcode">%s</div>', $html );
        }

        /**
         * Shortcode `[wtm_oxygen_menu id="42"]` — alias de `[wtm_menu]`.
         *
         * Permet aux utilisateurs Oxygen d'avoir un shortcode explicite.
         *
         * @param array $atts Attributs.
         * @return string HTML.
         */
        public function render_oxygen_menu_shortcode( $atts ) {
                $atts = shortcode_atts(
                        array(
                                'id' => 0,
                        ),
                        $atts,
                        'wtm_oxygen_menu'
                );

                $menu_id = (int) $atts['id'];
                if ( $menu_id <= 0 ) {
                        return '';
                }

                $html = $this->menu_renderer->render_by_id( $menu_id, '' );

                /**
                 * Fires when a WTM menu is rendered via Oxygen shortcode.
                 *
                 * @since 1.6.0
                 *
                 * @param int $menu_id Menu post ID.
                 */
                do_action( 'wtm_rendered_location', $menu_id, '' );

                return $html;
        }
}
